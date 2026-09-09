<?php

namespace App\Services;

use App\Enums\DiceRoller;
use App\Enums\ProtectionKind;
use App\Enums\RunConsequence;
use App\Enums\RunDeparture;
use App\Enums\RunnerSkill;
use App\Enums\RunStatus;
use App\Enums\RunStep;
use App\Enums\Tracker;
use App\Jobs\SyncRunChannelAccess;
use App\Models\Character;
use App\Models\Facility;
use App\Models\FacilityCardActivation;
use App\Models\FacilityProtectionCard;
use App\Models\Gang;
use App\Models\Run;
use App\Models\RunDiceRoll;
use App\Models\RunEvent;
use App\Models\RunParticipant;
use App\Models\Turn;
use App\Models\User;
use App\Support\Runs\ActivationCost;
use App\Support\Runs\AlertSchedule;
use App\Support\Runs\ChallengeOutcome;
use App\Support\Runs\ChallengeStrength;
use App\Support\Runs\DicePool;
use App\Support\Runs\RunCursor;
use App\Support\Runs\RunGroup;
use App\Support\Runs\RunOrdering;
use App\Support\Runs\RunRewards;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Run loop and every consequence of it (rulebook 3.4.1, 3.4.2, 3.4.4).
 *
 * Each public method here is one *act* - Security activating a card, the
 * Runners throwing dice at it, somebody taking the consequence, a Runner
 * walking away at the Breather. Who is allowed to perform which act is a
 * policy question and lives elsewhere; this class knows only the rules.
 *
 * Three things are load-bearing about the shape of it:
 *
 * **Failing a challenge does not stop the Runners.** 3.4.2 says the Runners
 * move to the Breather "unless a Protection Card has an 'End the Run'
 * consequence" - so losing a check costs you the consequence and you still get
 * past the card. A Facility is attrition rather than a wall, which is why
 * Retry is one of the worst things a card can do to you and why Alerts matter
 * so much: they make everything still ahead of you harder.
 *
 * **Every roll is server-side and kept.** A browser that rolls its own dice is
 * a browser that can decide it won. {@see Dice} is injected so tests can say
 * what the dice did, and every roll writes a `run_dice_rolls` row with its
 * faces.
 *
 * **Nothing here writes a tracker directly.** Wounds, Tags and Credits all go
 * through {@see TrackerService}. The one apparent exception is Credits spent
 * from a security budget, which move no tracker at all because
 * {@see FacilityDefenceService::setSecurityBudget()} already took them off the
 * Corporation when the budget was placed - spending only records how much of
 * that escrow has gone, and the remainder is handed back when the phase ends.
 *
 * Alerts are the other thing that is deliberately not a tracker: they are a
 * pool for the duration of one run and then gone, so there is no ledger to
 * write and nothing outside the run can see them.
 */
class RunEngine
{
    public function __construct(
        private readonly TrackerService $trackers,
        private readonly Dice $dice,
    ) {}

    /**
     * Put in for a run against a Facility (rulebook 3.4.1).
     *
     * The group is named here, in Setup or at the top of the Action phase, and
     * only the Run Leader submits for it. Nothing is rolled and no Alerts are
     * generated yet: 3.4.1 generates those "at the beginning of the Run", from
     * the Tags held at that moment, so a Tag taken between submitting and going
     * in counts and one taken during the run does not.
     *
     * @param  array<int, int>  $memberCharacterIds  the rest of the group, in Breather order
     */
    public function submit(
        Turn $turn,
        Facility $facility,
        Character $leader,
        array $memberCharacterIds = [],
        ?User $actor = null,
    ): Run {
        if ($facility->game_id !== $turn->game_id || $leader->game_id !== $turn->game_id) {
            throw ValidationException::withMessages([
                'facility_id' => 'That Facility and that Runner are not in the same game as this turn.',
            ]);
        }

        if (! $facility->isAvailableOnTurn($turn->number)) {
            throw ValidationException::withMessages([
                'facility_id' => sprintf('%s is still being built.', $facility->name),
            ]);
        }

        // The Leader is the first Runner rather than a separate thing: 3.4 makes
        // a solo Runner their own Run Leader, so the group is never empty.
        $ids = collect([$leader->id])
            ->concat($memberCharacterIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        $runners = Character::query()
            ->whereIn('id', $ids)
            ->where('game_id', $turn->game_id)
            ->get()
            ->keyBy('id');

        if ($runners->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'member_character_ids' => 'One of those Runners is not in this game.',
            ]);
        }

        // One run each per turn. A Runner in two groups at once would be in two
        // dice pools at once, and would have to be in two places to take a
        // consequence.
        $alreadyRunning = RunParticipant::query()
            ->whereIn('character_id', $ids)
            ->whereHas('run', fn ($query) => $query->where('turn_id', $turn->id))
            ->with('character')
            ->first();

        if ($alreadyRunning !== null) {
            throw ValidationException::withMessages([
                'member_character_ids' => sprintf(
                    '%s is already on a run this turn.',
                    $alreadyRunning->character->name,
                ),
            ]);
        }

        return DB::transaction(function () use ($turn, $facility, $leader, $ids, $actor): Run {
            /** @var Run $run */
            $run = Run::create([
                'game_id' => $turn->game_id,
                'turn_id' => $turn->id,
                'facility_id' => $facility->id,
                'run_leader_character_id' => $leader->id,
                'status' => RunStatus::Submitted,
            ]);

            foreach ($ids as $position => $characterId) {
                RunParticipant::create([
                    'run_id' => $run->id,
                    'character_id' => $characterId,
                    'position' => $position + 1,
                ]);
            }

            $this->record(
                $run,
                RunEvent::TYPE_SUBMITTED,
                sprintf(
                    '%s submitted a run of %d against %s.',
                    $leader->name,
                    $ids->count(),
                    $facility->name,
                ),
                actor: $actor,
                character: $leader,
                payload: ['character_ids' => $ids->all()],
            );

            return $run->refresh();
        });
    }

    /**
     * Order the groups queueing at one Facility (rulebook 3.4.1).
     *
     * Runs the seven tiebreakers and writes the answer down, because the last
     * of them is a d8 and so the order cannot be recomputed afterwards. Runs
     * already under way keep their place - the queue is settled before anybody
     * goes in.
     *
     * Groups may cede to each other or be "otherwise monetarily convinced", and
     * footnote 10 sends anything unusual to Control, so this is a starting
     * position rather than the last word: Control may set `order_index` by hand.
     *
     * @return Collection<int, Run>
     */
    public function orderRuns(Facility $facility, Turn $turn, ?User $actor = null): Collection
    {
        $runs = Run::query()
            ->where('facility_id', $facility->id)
            ->where('turn_id', $turn->id)
            ->whereIn('status', [RunStatus::Submitted, RunStatus::Running])
            ->with('participants.character')
            ->get();

        if ($runs->isEmpty()) {
            return collect();
        }

        $notorious = $this->mostNotoriousGangIds($turn);

        $groups = $runs->map(function (Run $run) use ($notorious): RunGroup {
            $members = $run->activeParticipants()
                ->map(fn (RunParticipant $participant): array => $this->profile($participant->character))
                ->all();

            $leader = $run->leader !== null
                ? $this->profile($run->leader)
                : ($members[0] ?? ['gang_id' => null, 'brawn' => 0, 'hack' => 0]);

            return RunGroup::of($run->id, $members, $leader, $notorious);
        })->all();

        $places = (new RunOrdering($this->dice))->order($groups);

        return DB::transaction(function () use ($runs, $places, $facility, $actor): Collection {
            $ordered = collect();

            foreach ($places as $place) {
                /** @var Run $run */
                $run = $runs->firstWhere('id', $place->runId);

                $run->forceFill([
                    'order_index' => $place->position,
                    'order_reason' => $place->explain(),
                ])->save();

                $this->record(
                    $run,
                    RunEvent::TYPE_ORDERED,
                    sprintf(
                        'Going %s at %s: %s.',
                        $this->ordinal($place->position),
                        $facility->name,
                        $place->explain(),
                    ),
                    actor: $actor,
                    payload: [
                        'position' => $place->position,
                        'rule' => $place->rule,
                        'roll' => $place->roll,
                    ],
                );

                $ordered->push($run->refresh());
            }

            return $ordered;
        });
    }

    /**
     * Go in (rulebook 3.4.1).
     *
     * Generates the Alerts the run opens with, from the Tags the group is
     * carrying right now plus a bonus for its size. Past six Runners the
     * rulebook sends Control to a help sheet, so the group bonus takes an
     * override and {@see AlertSchedule::groupBonusIsExtrapolated()} says when
     * the number the application proposed is ours rather than the book's.
     */
    public function begin(Run $run, ?User $actor = null, ?int $groupAlertOverride = null): Run
    {
        if ($run->status !== RunStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'That run has already begun.',
            ]);
        }

        $runners = $run->activeParticipants();

        if ($runners->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => 'That run has nobody on it.',
            ]);
        }

        $tags = $runners
            ->mapWithKeys(fn (RunParticipant $p): array => [$p->character_id => $p->character->tags])
            ->all();

        $fromTags = AlertSchedule::forTags($tags);
        $fromSize = AlertSchedule::forGroupSize(count($tags), $groupAlertOverride);

        return DB::transaction(function () use ($run, $runners, $fromTags, $fromSize, $actor): Run {
            $run->forceFill([
                'status' => RunStatus::Running,
                'alerts' => $fromTags + $fromSize,
                'started_at' => now(),
            ])->save();

            $this->record(
                $run,
                RunEvent::TYPE_BEGAN,
                sprintf(
                    '%d Runner%s went in at %s, raising %d Alert%s: %d for their Tags and %d for the size of the group.',
                    $runners->count(),
                    $runners->count() === 1 ? '' : 's',
                    $run->facility->name,
                    $fromTags + $fromSize,
                    $fromTags + $fromSize === 1 ? '' : 's',
                    $fromTags,
                    $fromSize,
                ),
                actor: $actor,
                payload: [
                    'alerts_from_tags' => $fromTags,
                    'alerts_from_group_size' => $fromSize,
                    'group_bonus_extrapolated' => AlertSchedule::groupBonusIsExtrapolated($runners->count()),
                ],
            );

            // Into the Facility's own Discord channels, and not a moment
            // earlier: the target is Secret until the group goes in, so a
            // Runner appearing in the channel beforehand would tell the whole
            // server who was hitting what.
            SyncRunChannelAccess::dispatch($run->id, granting: true);

            return $run->refresh();
        });
    }

    /**
     * Where the run has got to: which card, which pass, which step.
     */
    public function cursor(Run $run): RunCursor
    {
        $cards = $this->encounterOrder($run->facility);
        $card = $cards[$run->cards_passed] ?? null;

        $activation = $card === null
            ? null
            : FacilityCardActivation::query()
                ->where('turn_id', $run->turn_id)
                ->where('facility_protection_card_id', $card->id)
                ->first();

        // A pass ends when the Runners move on from a card or consume a Retry,
        // so counting those is counting the passes that have finished. Derived
        // rather than stored: a cursor kept beside an append-only log is a
        // second source of truth that can disagree with it.
        $finished = $run->events
            ->whereIn('type', [RunEvent::TYPE_CARD_PASSED, RunEvent::TYPE_RETRIED])
            ->count();

        return new RunCursor(
            pass: $finished + 1,
            step: $this->stepFor($run, $finished + 1, $card, $activation),
            card: $card,
            activation: $activation,
            cardsRemaining: max(0, count($cards) - $run->cards_passed),
        );
    }

    /**
     * Security's go at the card in front of the Runners (rulebook 3.4.2).
     *
     * Physical cards are free and cyber cards cost the number of cyber cards
     * already Active, so a deep cyber stack is expensive to defend as well as
     * expensive to build.
     *
     * The Directing player *chooses*; anybody else only *attempts*, and a card
     * they cannot pay for stays Inactive and gets skipped. An explicit choice
     * to activate that cannot be paid for is recorded as the same failure
     * rather than refused, because that is what happens at the table - Control
     * says "you can't afford it, it stays off" - and refusing would leave the
     * Runners unable to go anywhere until Security found money it did not have.
     *
     * @param  bool|null  $activating  false only where Security is Directing here
     * @param  int  $alertsToSpend  Alerts to put towards the cost as temporary Credits
     */
    public function activate(
        Run $run,
        ?bool $activating = null,
        int $alertsToSpend = 0,
        ?User $actor = null,
    ): FacilityCardActivation {
        $cursor = $this->requireCard($run);

        if ($cursor->activationSettled()) {
            throw ValidationException::withMessages([
                'card' => 'That card has already been dealt with this phase.',
            ]);
        }

        $directing = $run->facility->stateForTurn($run->turn)->security_directed;

        if ($activating === false && ! $directing) {
            throw ValidationException::withMessages([
                'activating' => 'Only a Security player Directing Security here may leave a card switched off.',
            ]);
        }

        /** @var FacilityProtectionCard $card */
        $card = $cursor->card;
        $cost = ActivationCost::for($card->kind, $this->activeCyberCards($run));

        return DB::transaction(function () use ($run, $cursor, $card, $cost, $activating, $alertsToSpend, $actor): FacilityCardActivation {
            $declined = $activating === false;
            $affordable = $this->affordable($run, $cost, $alertsToSpend);
            $activated = ! $declined && $affordable;

            $paid = ['alerts' => 0, 'budget' => 0];

            if ($activated) {
                $paid = $this->pay(
                    $run,
                    $cost,
                    $alertsToSpend,
                    sprintf('Activating %s', $card->cardType->name),
                    $actor,
                );
            }

            /** @var FacilityCardActivation $activation */
            $activation = FacilityCardActivation::create([
                'turn_id' => $run->turn_id,
                'facility_protection_card_id' => $card->id,
                'activated_at' => $activated ? now() : null,
                'activation_cost' => $cost,
            ]);

            [$type, $description] = match (true) {
                $activated => [
                    RunEvent::TYPE_ACTIVATED,
                    sprintf(
                        '%s came on%s.',
                        $card->cardType->name,
                        $cost > 0 ? sprintf(' for %d Credit%s', $cost, $cost === 1 ? '' : 's') : ' for nothing',
                    ),
                ],
                $declined => [
                    RunEvent::TYPE_ACTIVATION_DECLINED,
                    sprintf('Security left %s switched off.', $card->cardType->name),
                ],
                default => [
                    RunEvent::TYPE_ACTIVATION_FAILED,
                    sprintf(
                        'Security could not cover the %d Credit%s to activate %s, so it stayed off.',
                        $cost,
                        $cost === 1 ? '' : 's',
                        $card->cardType->name,
                    ),
                ],
            };

            $this->record($run, $type, $description, actor: $actor, card: $card, pass: $cursor->pass, step: RunStep::Activate, payload: [
                'cost' => $cost,
                'paid_with_alerts' => $paid['alerts'],
                'paid_from_budget' => $paid['budget'],
            ]);

            return $activation->refresh();
        });
    }

    /**
     * Make a card harder for the rest of the phase (rulebook 3.4.2).
     *
     * Directing only, and Active cards only. Cumulative per card - the first
     * Boost costs 1 Credit, the second 2 - and it lasts the phase, so a later
     * group walks into whatever the first group's Security paid for.
     *
     * @param  int  $times  how many Boosts to buy at once
     */
    public function boost(Run $run, int $times = 1, int $alertsToSpend = 0, ?User $actor = null): FacilityCardActivation
    {
        $cursor = $this->requireCard($run);

        if ($times < 1) {
            throw ValidationException::withMessages(['times' => 'Boost at least once.']);
        }

        if (! $run->facility->stateForTurn($run->turn)->security_directed) {
            throw ValidationException::withMessages([
                'boost' => 'Boosting needs a Security player Directing Security from this Facility.',
            ]);
        }

        if (! $cursor->cardIsActive()) {
            throw ValidationException::withMessages([
                'boost' => 'A card has to be Active before it can be Boosted.',
            ]);
        }

        /** @var FacilityCardActivation $activation */
        $activation = $cursor->activation;
        /** @var FacilityProtectionCard $card */
        $card = $cursor->card;

        $cost = 0;

        for ($boost = 0; $boost < $times; $boost++) {
            $cost += $activation->boosts + $boost + 1;
        }

        return DB::transaction(function () use ($run, $cursor, $card, $activation, $times, $cost, $alertsToSpend, $actor): FacilityCardActivation {
            $this->pay($run, $cost, $alertsToSpend, sprintf('Boosting %s', $card->cardType->name), $actor);

            $activation->forceFill([
                'boosts' => $activation->boosts + $times,
                'boost_credits_spent' => $activation->boost_credits_spent + $cost,
            ])->save();

            $this->record(
                $run,
                RunEvent::TYPE_BOOSTED,
                sprintf(
                    '%s Boosted %d time%s for %d Credit%s, and is now +%d for the rest of the phase.',
                    $card->cardType->name,
                    $times,
                    $times === 1 ? '' : 's',
                    $cost,
                    $cost === 1 ? '' : 's',
                    $activation->boosts,
                ),
                actor: $actor,
                card: $card,
                pass: $cursor->pass,
                step: RunStep::Activate,
                payload: ['times' => $times, 'cost' => $cost, 'boosts' => $activation->boosts],
            );

            return $activation->refresh();
        });
    }

    /**
     * Pay a card's Charge cost to add its extra consequences (rulebook 3.4.2).
     *
     * Directing only. What the extra consequences *are* is the sentence the
     * card prints, so this records the payment and whoever is running the card
     * then applies them through {@see self::applyConsequence()} like any other.
     */
    public function charge(Run $run, int $alertsToSpend = 0, ?User $actor = null): RunEvent
    {
        $cursor = $this->requireCard($run);

        if (! $run->facility->stateForTurn($run->turn)->security_directed) {
            throw ValidationException::withMessages([
                'charge' => 'A Charge needs a Security player Directing Security from this Facility.',
            ]);
        }

        /** @var FacilityProtectionCard $card */
        $card = $cursor->card;

        if (! $card->cardType->hasCharge()) {
            throw ValidationException::withMessages([
                'charge' => sprintf('%s has no Charge ability.', $card->cardType->name),
            ]);
        }

        $cost = (int) $card->cardType->charge_cost;

        return DB::transaction(function () use ($run, $cursor, $card, $cost, $alertsToSpend, $actor): RunEvent {
            $this->pay($run, $cost, $alertsToSpend, sprintf('Charging %s', $card->cardType->name), $actor);

            return $this->record(
                $run,
                RunEvent::TYPE_CHARGED,
                sprintf(
                    'Security paid the %d Credit Charge on %s: %s',
                    $cost,
                    $card->cardType->name,
                    $card->cardType->charge_consequence ?? 'see the card.',
                ),
                actor: $actor,
                card: $card,
                pass: $cursor->pass,
                step: RunStep::Consequence,
                payload: ['cost' => $cost],
            );
        });
    }

    /**
     * Throw the dice at the card (rulebook 3.4.2).
     *
     * The Run Leader rolls their full skill and everybody else adds half of
     * theirs, or a quarter if they are Wounded - and the die size comes from
     * the Leader alone, so a Wounded Leader handing over before a hard card is
     * a real tactic. Security rolls d8s equal to the challenge strength. Both
     * sides need 5 or better, and a tie goes to Security.
     *
     * The printed strength is a parameter because there is nothing to read it
     * off: a challenge is the sentence the card prints - "Brute (6)",
     * "Brute/Hack (2)", "Hack (4+N) - where N is the number of cards underneath
     * this" - so whoever is running the card names the number and this says
     * what happens to it.
     */
    public function challenge(
        Run $run,
        RunnerSkill $skill,
        int $printedStrength,
        ?User $actor = null,
        ?int $alertStrengthOverride = null,
    ): ChallengeOutcome {
        $cursor = $this->requireCard($run);

        if (! $cursor->cardIsActive()) {
            throw ValidationException::withMessages([
                'challenge' => 'An Inactive card is walked straight past - there is nothing to break.',
            ]);
        }

        if ($this->passEvents($run, $cursor->pass)->contains('type', RunEvent::TYPE_CHALLENGE)) {
            throw ValidationException::withMessages([
                'challenge' => 'That card has already been challenged this pass.',
            ]);
        }

        $leader = $run->leader;
        $runners = $run->activeParticipants();

        if ($leader === null || ! $runners->contains('character_id', $leader->id)) {
            throw ValidationException::withMessages([
                'challenge' => 'The Run Leader has to be on the run to roll for it.',
            ]);
        }

        $others = $runners
            ->reject(fn (RunParticipant $p): bool => $p->character_id === $leader->id)
            ->mapWithKeys(fn (RunParticipant $p): array => [$p->character_id => [
                'skill' => (int) $p->character->getAttribute($skill->column()),
                'wounded' => $p->character->wounds > 0,
            ]])
            ->all();

        $pool = DicePool::for(
            leaderSkill: (int) $leader->getAttribute($skill->column()),
            leaderWounded: $leader->wounds > 0,
            others: $others,
        );

        /** @var FacilityCardActivation $activation */
        $activation = $cursor->activation;

        $strength = ChallengeStrength::for(
            printed: $printedStrength,
            cardsPassed: $run->active_cards_passed,
            alerts: $run->alertsAvailable(),
            boosts: $activation->boosts,
            alertOverride: $alertStrengthOverride,
        );

        /** @var FacilityProtectionCard $card */
        $card = $cursor->card;

        return DB::transaction(function () use ($run, $cursor, $card, $skill, $pool, $strength, $actor): ChallengeOutcome {
            $runnerFaces = $this->dice->roll($pool->total(), $pool->dieFaces);
            $securityFaces = $this->dice->roll($strength->total(), DicePool::HEALTHY_DIE);

            $runnerSuccesses = DicePool::countSuccesses($runnerFaces);
            $securitySuccesses = DicePool::countSuccesses($securityFaces);
            $won = DicePool::runnersWin($runnerSuccesses, $securitySuccesses);

            $event = $this->record(
                $run,
                RunEvent::TYPE_CHALLENGE,
                sprintf(
                    '%s (%s): Runners rolled %d success%s against Security\'s %d — %s.',
                    $card->cardType->name,
                    $skill->label(),
                    $runnerSuccesses,
                    $runnerSuccesses === 1 ? '' : 'es',
                    $securitySuccesses,
                    $won ? 'through' : 'held',
                ),
                actor: $actor,
                card: $card,
                pass: $cursor->pass,
                step: RunStep::Challenge,
                payload: [
                    'skill' => $skill->value,
                    'strength' => $strength->toArray(),
                    'pool' => $pool->toArray(),
                    'runner_successes' => $runnerSuccesses,
                    'security_successes' => $securitySuccesses,
                    'runners_won' => $won,
                ],
            );

            $runnersRoll = $this->recordRoll(
                $run,
                $event,
                DiceRoller::Runners,
                $pool->total(),
                $pool->dieFaces,
                $runnerFaces,
                $runnerSuccesses,
                sprintf('%s against %s', $skill->label(), $card->cardType->name),
            );

            $securityRoll = $this->recordRoll(
                $run,
                $event,
                DiceRoller::Security,
                $strength->total(),
                DicePool::HEALTHY_DIE,
                $securityFaces,
                $securitySuccesses,
                sprintf('%s defending, strength %s', $card->cardType->name, $strength->explain()),
            );

            return new ChallengeOutcome(
                strength: $strength,
                pool: $pool,
                runnersRoll: $runnersRoll,
                securityRoll: $securityRoll,
                runnersWon: $won,
                event: $event,
            );
        });
    }

    /**
     * Apply one consequence (rulebook 3.4.2).
     *
     * A card's consequence is the sentence it prints, and a card may carry
     * several, so this takes one effect at a time and whoever is running the
     * card reads the words and calls it as many times as the card says. Wounds
     * and Tags land on "a single player, decided by the Run Leader"; Alerts go
     * into the pool; Retry and End the Run happen to everybody.
     *
     * The step is not enforced. Security may trigger effects with Alerts during
     * the same step, a Charge adds consequences after the fact, and Control
     * must be able to apply one out of order - so the guard is that the run is
     * still going, and the log is what says when each one landed.
     */
    public function applyConsequence(
        Run $run,
        RunConsequence $effect,
        ?Character $taker = null,
        int $times = 1,
        ?User $actor = null,
    ): RunEvent {
        $this->requireRunning($run);

        if ($times < 1) {
            throw ValidationException::withMessages(['times' => 'Apply a consequence at least once.']);
        }

        if ($effect->appliesToCharacter()) {
            $taker = $this->requireActiveRunner($run, $taker);
        }

        $cursor = $this->cursor($run);

        return DB::transaction(function () use ($run, $cursor, $effect, $taker, $times, $actor): RunEvent {
            $description = match ($effect) {
                RunConsequence::Alert => $this->raiseAlerts($run, $times),
                RunConsequence::Tag, RunConsequence::Wound => $this->hurt(
                    $run,
                    $effect,
                    $this->requireActiveRunner($run, $taker),
                    $times,
                    $actor,
                ),
                RunConsequence::Retry => $this->setRetry($run),
                RunConsequence::EndTheRun => 'The card ended the run.',
            };

            $event = $this->record(
                $run,
                RunEvent::TYPE_CONSEQUENCE,
                $description,
                actor: $actor,
                character: $taker,
                card: $cursor->card,
                pass: $cursor->pass,
                step: RunStep::Consequence,
                payload: ['effect' => $effect->value, 'times' => $times],
            );

            if ($effect === RunConsequence::EndTheRun) {
                $this->fail($run->refresh(), 'a card ended it', $actor);
            } elseif ($taker !== null && $taker->refresh()->isIncapacitated()) {
                // A Wound that reaches Body takes the Runner out there and then,
                // which can be what ends the run.
                $this->incapacitate($run->refresh(), $taker, $actor);
            }

            return $event;
        });
    }

    /**
     * Spend Alerts to add a consequence Security's own way (rulebook 3.4.2).
     *
     * The other thing Alerts are for. The prices are steep - 2 for a Tag, 5 for
     * a Wound, 12 for a Retry, 15 to end a run - and they compete with the same
     * Alerts being spent as temporary Credits, which is the decision the
     * Security player is there to make. Spending them also lowers the strength
     * every remaining card is getting from the Alert curve.
     */
    public function triggerWithAlerts(
        Run $run,
        RunConsequence $effect,
        ?Character $taker = null,
        ?User $actor = null,
    ): RunEvent {
        $this->requireRunning($run);

        $cost = $effect->alertCost();

        if ($cost === null) {
            throw ValidationException::withMessages([
                'effect' => 'Alerts cannot buy more Alerts.',
            ]);
        }

        if ($run->alertsAvailable() < $cost) {
            throw ValidationException::withMessages([
                'alerts' => sprintf(
                    'That costs %d Alerts and Security has %d.',
                    $cost,
                    $run->alertsAvailable(),
                ),
            ]);
        }

        return DB::transaction(function () use ($run, $effect, $taker, $cost, $actor): RunEvent {
            $run->forceFill(['alerts_spent' => $run->alerts_spent + $cost])->save();

            return $this->applyConsequence($run->refresh(), $effect, $taker, 1, $actor);
        });
    }

    /**
     * Shrug off an End the Run, and pay for it (rulebook 3.4.2).
     *
     * "For each 'End the Run' that you have ignored (including this one), you
     * take 1 Wound, 1 Tag and 1 Alert" - so the count is the multiplier rather
     * than a flag. The first one ignored costs 1 of each, the second 2 of each,
     * and it converts into a Retry: the same card again, now with more Alerts
     * standing behind it.
     */
    public function ignoreEndTheRun(Run $run, Character $taker, ?User $actor = null): RunEvent
    {
        $this->requireRunning($run);

        $taker = $this->requireActiveRunner($run, $taker);
        $cursor = $this->cursor($run);
        $ignored = $run->ignored_end_the_run + 1;

        return DB::transaction(function () use ($run, $cursor, $taker, $ignored, $actor): RunEvent {
            $run->forceFill([
                'ignored_end_the_run' => $ignored,
                'alerts' => $run->alerts + $ignored,
                'retry_pending' => true,
            ])->save();

            foreach ([Tracker::Wounds, Tracker::Tags] as $tracker) {
                $this->trackers->adjust(
                    $taker,
                    $tracker,
                    $ignored,
                    sprintf('Ignored End the Run #%d at %s', $ignored, $run->facility->name),
                    $actor,
                );
            }

            $event = $this->record(
                $run,
                RunEvent::TYPE_IGNORED_END_THE_RUN,
                sprintf(
                    '%s took %d Wound%s, %d Tag%s and %d Alert%s to ignore End the Run #%d and retry the card.',
                    $taker->name,
                    $ignored,
                    $ignored === 1 ? '' : 's',
                    $ignored,
                    $ignored === 1 ? '' : 's',
                    $ignored,
                    $ignored === 1 ? '' : 's',
                    $ignored,
                ),
                actor: $actor,
                character: $taker,
                card: $cursor->card,
                pass: $cursor->pass,
                step: RunStep::Consequence,
                payload: ['ignored' => $ignored],
            );

            if ($taker->refresh()->isIncapacitated()) {
                $this->incapacitate($run->refresh(), $taker, $actor);
            }

            return $event;
        });
    }

    /**
     * Walk away at the Breather (rulebook 3.4.2).
     *
     * "Leaving a Run when the rest of the group continue may have an effect on
     * your gang's Notoriety" - *may*, so no Notoriety moves here. That is
     * Control's ruling, and the event says so rather than the application
     * guessing a number the rulebook never printed.
     *
     * A Leader who leaves hands over. The rulebook wants the replacement chosen
     * democratically and randomly if not, so a named successor is taken as the
     * democratic answer and the die is what happens when nobody names one.
     */
    public function leave(
        Run $run,
        Character $character,
        ?Character $newLeader = null,
        ?User $actor = null,
    ): Run {
        $this->requireRunning($run);

        $participant = $run->participants->firstWhere('character_id', $character->id);

        if ($participant === null || ! $participant->isActive()) {
            throw ValidationException::withMessages([
                'character_id' => sprintf('%s is not on that run.', $character->name),
            ]);
        }

        $cursor = $this->cursor($run);

        return DB::transaction(function () use ($run, $cursor, $participant, $character, $newLeader, $actor): Run {
            $participant->forceFill([
                'left_at' => now(),
                'left_reason' => RunDeparture::Left,
            ])->save();

            $this->record(
                $run,
                RunEvent::TYPE_LEFT,
                sprintf(
                    '%s left the run. Whether that costs the gang any Notoriety is Control\'s call.',
                    $character->name,
                ),
                actor: $actor,
                character: $character,
                pass: $cursor->pass,
                step: RunStep::Breather,
            );

            return $this->afterDeparture($run->refresh(), $character, $newLeader, $actor);
        });
    }

    /**
     * Leave the Breather: on to the next card, or round again on this one.
     *
     * The card is passed whether or not the Runners broke it, because 3.4.2
     * only holds them up for an End the Run - failing a challenge costs the
     * consequence and they still get through. A Retry taken this pass is
     * consumed here instead, sending them at the same card again.
     *
     * Passing the last card is what wins a run: "The Run is considered
     * successful if the Runners break through all the Protection Cards".
     */
    public function advance(Run $run, ?User $actor = null): Run
    {
        $this->requireRunning($run);

        $cursor = $this->cursor($run);

        if ($cursor->step !== RunStep::Breather) {
            throw ValidationException::withMessages([
                'step' => sprintf('The run is still on the %s step.', $cursor->step->label()),
            ]);
        }

        return DB::transaction(function () use ($run, $cursor, $actor): Run {
            if ($run->retry_pending) {
                $run->forceFill(['retry_pending' => false])->save();

                $this->record(
                    $run,
                    RunEvent::TYPE_RETRIED,
                    sprintf('Back to the Activate step on %s.', $cursor->card?->cardType->name ?? 'the same card'),
                    actor: $actor,
                    card: $cursor->card,
                    pass: $cursor->pass,
                    step: RunStep::Breather,
                );

                return $run->refresh();
            }

            $wasActive = $cursor->cardIsActive();

            $run->forceFill([
                'cards_passed' => $run->cards_passed + 1,
                'active_cards_passed' => $run->active_cards_passed + ($wasActive ? 1 : 0),
            ])->save();

            $this->record(
                $run,
                RunEvent::TYPE_CARD_PASSED,
                sprintf(
                    'Past %s%s. %d card%s down.',
                    $cursor->card?->cardType->name ?? 'the card',
                    $wasActive ? '' : ', which never came on',
                    $run->cards_passed,
                    $run->cards_passed === 1 ? '' : 's',
                ),
                actor: $actor,
                card: $cursor->card,
                pass: $cursor->pass,
                step: RunStep::Breather,
                payload: ['card_was_active' => $wasActive],
            );

            $run = $run->refresh();

            if ($this->cursor($run)->cardsRemaining === 0) {
                return $this->succeed($run, $actor);
            }

            return $run;
        });
    }

    /**
     * Fail every run still going when the Action phase closes (rulebook 3.4.5).
     *
     * "If the end of phase is called and you have not yet been successful then
     * your run is treated as unsuccessful" - which includes a run that was
     * submitted and never went in, and which is why {@see TurnEngine} calls
     * this as the phase ends rather than leaving runs open across a turn.
     *
     * @return int how many runs were closed
     */
    public function failUnfinishedRuns(Turn $turn, ?User $actor = null): int
    {
        $runs = Run::query()
            ->where('turn_id', $turn->id)
            ->whereIn('status', [RunStatus::Submitted, RunStatus::Running])
            ->with('participants.character', 'facility')
            ->get();

        foreach ($runs as $run) {
            $this->fail($run, 'the Action phase ended', $actor, automated: true);
        }

        return $runs->count();
    }

    /**
     * The Runners got through everything (rulebook 3.4.1).
     *
     * What a successful run *pays* is 3.4.3, which this application does not
     * model: which technologies a Facility is storing, a technology's Steal
     * score and the per-Facility Credits card do not exist yet, so the accesses
     * are Control's to hand out. The run records that it succeeded, and the
     * event says so.
     */
    protected function succeed(Run $run, ?User $actor = null): Run
    {
        $run->forceFill([
            'status' => RunStatus::Succeeded,
            'ended_at' => now(),
        ])->save();

        $this->record(
            $run,
            RunEvent::TYPE_SUCCEEDED,
            sprintf(
                'The run on %s succeeded: %d Protection Card%s broken. The accesses are Control\'s to hand out (3.4.3).',
                $run->facility->name,
                $run->cards_passed,
                $run->cards_passed === 1 ? '' : 's',
            ),
            actor: $actor,
            step: RunStep::Breather,
        );

        SyncRunChannelAccess::dispatch($run->id, granting: false);

        return $run->refresh();
    }

    /**
     * The run is over and the Runners did not get through (rulebook 3.4.4).
     *
     * Pays the consolation - 1 Credit per 3 cards they got past, rounding up -
     * to "the last runner". Which is read as whoever was still on the run when
     * it ended: the Leader if they are still standing, otherwise the next
     * Runner in Breather order, otherwise the last person to have left. A group
     * that ends a run together splits that between them at the table, which is
     * a conversation rather than a number.
     */
    protected function fail(Run $run, string $because, ?User $actor = null, bool $automated = false): Run
    {
        if ($run->status->isFinished()) {
            return $run;
        }

        $reward = RunRewards::forFailedRun($run->cards_passed);
        $recipient = $this->lastRunner($run);

        $run->forceFill([
            'status' => RunStatus::Failed,
            'ended_at' => now(),
        ])->save();

        if ($reward > 0 && $recipient !== null) {
            $this->trackers->adjust(
                $recipient,
                Tracker::CharacterCredits,
                $reward,
                sprintf('Unsuccessful run on %s: %d cards passed', $run->facility->name, $run->cards_passed),
                $actor,
                automated: $automated,
            );
        }

        $this->record(
            $run,
            RunEvent::TYPE_FAILED,
            sprintf(
                'The run on %s was unsuccessful because %s, after %d card%s.%s',
                $run->facility->name,
                $because,
                $run->cards_passed,
                $run->cards_passed === 1 ? '' : 's',
                $reward > 0 && $recipient !== null
                    ? sprintf(' %s took %d Credit%s for it.', $recipient->name, $reward, $reward === 1 ? '' : 's')
                    : '',
            ),
            actor: $actor,
            character: $recipient,
            step: RunStep::Breather,
            payload: ['reason' => $because, 'reward' => $reward],
        );

        // Only where the group ever went in. A run submitted and never begun -
        // which the end of the Action phase turns into a failure - was never
        // granted anything, and asking Discord to remove an overwrite that was
        // never added is a request per Runner per channel for nothing.
        if ($run->started_at !== null) {
            SyncRunChannelAccess::dispatch($run->id, granting: false);
        }

        return $run->refresh();
    }

    /**
     * Wounds reached Body, so the Runner is out (rulebook 3.4.2).
     *
     * No Notoriety effect, unlike walking away - being carried out is not the
     * same as abandoning your group. Any permanent Equipment they played goes
     * to the Security player of the Corporation they were running against,
     * which the event says because who owns which Equipment card is not
     * modelled and so the hand-over is Control's to make.
     */
    protected function incapacitate(Run $run, Character $character, ?User $actor = null): Run
    {
        $participant = $run->participants->firstWhere('character_id', $character->id);

        if ($participant === null || ! $participant->isActive()) {
            return $run;
        }

        $cursor = $this->cursor($run);

        $participant->forceFill([
            'left_at' => now(),
            'left_reason' => RunDeparture::Incapacitated,
        ])->save();

        $this->record(
            $run,
            RunEvent::TYPE_INCAPACITATED,
            sprintf(
                '%s took %d Wound%s against a Body of %d and was carried out. Any permanent Equipment they played goes to %s\'s Security player.',
                $character->name,
                $character->wounds,
                $character->wounds === 1 ? '' : 's',
                $character->body,
                $run->facility->corporation->name,
            ),
            actor: $actor,
            character: $character,
            pass: $cursor->pass,
            step: $cursor->step,
        );

        return $this->afterDeparture($run->refresh(), $character, null, $actor);
    }

    /**
     * Tidy up after somebody leaves: hand over the Leader's job, or end the run.
     *
     * "If all Runners have left (be that voluntarily or due to incapacitation)
     * then the run is treated as unsuccessful."
     */
    protected function afterDeparture(
        Run $run,
        Character $departed,
        ?Character $newLeader,
        ?User $actor,
    ): Run {
        $remaining = $run->activeParticipants();

        if ($remaining->isEmpty()) {
            $run->forceFill(['run_leader_character_id' => null])->save();

            return $this->fail($run->refresh(), 'everybody left it', $actor);
        }

        if ($run->run_leader_character_id !== $departed->id) {
            return $run;
        }

        $successor = $newLeader !== null
            ? $remaining->firstWhere('character_id', $newLeader->id)
            : null;

        if ($newLeader !== null && $successor === null) {
            throw ValidationException::withMessages([
                'new_leader_character_id' => sprintf('%s is not still on that run.', $newLeader->name),
            ]);
        }

        $picked = $successor !== null;
        $rolled = false;

        if ($successor === null && $remaining->count() === 1) {
            // One candidate is not a choice, and rolling a one-sided die to
            // make it would be a lie about how it was decided.
            $successor = $remaining->first();
        } elseif ($successor === null) {
            // Nobody named a successor, so the rulebook says choose randomly.
            // Rolled through the same Dice the challenges use, so a test can say
            // who got it and the choice is not a black box.
            $candidates = $remaining->count();
            $roll = $this->dice->roll(1, max(2, $candidates))[0] ?? 1;
            $successor = $remaining[max(0, min($candidates - 1, $roll - 1))];
            $rolled = true;
        }

        /** @var RunParticipant $successor */
        $run->forceFill(['run_leader_character_id' => $successor->character_id])->save();

        $this->record(
            $run,
            RunEvent::TYPE_LEADER_CHANGED,
            sprintf(
                '%s is now the Run Leader, %s.',
                $successor->character->name,
                match (true) {
                    $picked => 'by the group\'s choice',
                    $rolled => 'chosen at random',
                    default => 'as the only Runner left in',
                },
            ),
            actor: $actor,
            character: $successor->character,
            step: RunStep::Breather,
            payload: ['chosen_at_random' => $rolled],
        );

        return $run->refresh();
    }

    /**
     * Alerts into the pool.
     */
    protected function raiseAlerts(Run $run, int $times): string
    {
        $run->forceFill(['alerts' => $run->alerts + $times])->save();

        return sprintf(
            '%d Alert%s raised, %d standing — every card left is +%d.',
            $times,
            $times === 1 ? '' : 's',
            $run->alertsAvailable(),
            AlertSchedule::strengthBonus($run->alertsAvailable()),
        );
    }

    /**
     * A Wound or a Tag on one Runner, through the ledger like everything else.
     */
    protected function hurt(
        Run $run,
        RunConsequence $effect,
        Character $taker,
        int $times,
        ?User $actor,
    ): string {
        /** @var Tracker $tracker Tag and Wound both have one; nothing else reaches here. */
        $tracker = $effect->tracker();

        $this->trackers->adjust(
            $taker,
            $tracker,
            $times,
            sprintf('%s on the run at %s', $effect->label(), $run->facility->name),
            $actor,
        );

        return sprintf(
            '%s took %d %s%s.',
            $taker->name,
            $times,
            $effect->label(),
            $times === 1 ? '' : 's',
        );
    }

    /**
     * A Retry, which takes effect after the Breather.
     */
    protected function setRetry(Run $run): string
    {
        $run->forceFill(['retry_pending' => true])->save();

        return 'Retry: after the Breather, the Runners face this card again.';
    }

    /**
     * Which step the run is waiting on.
     *
     * Read off the log of the current pass rather than stored. The one thing
     * worth spelling out: an Inactive card skips straight to the Breather, and
     * a card an earlier group already switched on leaves the Runners at
     * Activate - where Security may still Boost it - rather than jumping them
     * to the Challenge.
     */
    protected function stepFor(
        Run $run,
        int $pass,
        ?FacilityProtectionCard $card,
        ?FacilityCardActivation $activation,
    ): RunStep {
        if ($card === null) {
            return RunStep::Breather;
        }

        $events = $this->passEvents($run, $pass);

        if ($events->whereIn('type', [RunEvent::TYPE_CONSEQUENCE, RunEvent::TYPE_IGNORED_END_THE_RUN])->isNotEmpty()) {
            return RunStep::Breather;
        }

        $challenge = $events->firstWhere('type', RunEvent::TYPE_CHALLENGE);

        if ($challenge !== null) {
            return ($challenge->payload['runners_won'] ?? false) === true
                ? RunStep::Breather
                : RunStep::Consequence;
        }

        if ($activation !== null && ! $activation->isActive()) {
            return RunStep::Breather;
        }

        return RunStep::Activate;
    }

    /**
     * The events of one pass, oldest first.
     *
     * @return Collection<int, RunEvent>
     */
    protected function passEvents(Run $run, int $pass): Collection
    {
        return $run->events->where('pass', $pass)->values();
    }

    /**
     * Every Protection Card in the order the Runners meet it: physical, then cyber.
     *
     * @return array<int, FacilityProtectionCard>
     */
    protected function encounterOrder(Facility $facility): array
    {
        $cards = $facility->protectionCards()
            ->with('cardType')
            ->orderBy('position')
            ->get();

        // Two stacks read one after the other rather than one sort over both.
        // Written out because the obvious shorthand is a trap: Collection's
        // sortBy() reads an array of closures as *comparators* taking two
        // items, so a one-argument key extractor silently sorts by nothing -
        // which put the cyber stack first and cost an hour.
        return $cards->where('kind', ProtectionKind::Physical)
            ->concat($cards->where('kind', ProtectionKind::Cyber))
            ->values()
            ->all();
    }

    /**
     * Cyber cards already Active in this Facility this turn, which is what the
     * next one costs to switch on.
     */
    protected function activeCyberCards(Run $run): int
    {
        return FacilityCardActivation::query()
            ->where('turn_id', $run->turn_id)
            ->whereNotNull('activated_at')
            ->whereHas('card', fn ($query) => $query
                ->where('facility_id', $run->facility_id)
                ->where('kind', ProtectionKind::Cyber))
            ->count();
    }

    /**
     * Whether Security can cover a cost from Alerts and the budget between them.
     */
    protected function affordable(Run $run, int $amount, int $alertsToSpend): bool
    {
        $fromAlerts = min(max(0, $alertsToSpend), $run->alertsAvailable(), $amount);

        return $run->facility->stateForTurn($run->turn)->unspentBudget() >= $amount - $fromAlerts;
    }

    /**
     * Take a cost out of the Alerts and the budget.
     *
     * No tracker moves here, and that is not an oversight: the budget was taken
     * off the Corporation when it was placed on the Facility, so spending it
     * only records how much of that escrow has gone. Whatever is left goes home
     * at the end of the Action phase.
     *
     * @return array{alerts: int, budget: int}
     */
    protected function pay(Run $run, int $amount, int $alertsToSpend, string $reason, ?User $actor): array
    {
        if ($amount < 0) {
            throw ValidationException::withMessages(['amount' => 'A cost cannot be negative.']);
        }

        $fromAlerts = min(max(0, $alertsToSpend), $run->alertsAvailable(), $amount);
        $fromBudget = $amount - $fromAlerts;
        $state = $run->facility->stateForTurn($run->turn);

        if ($state->unspentBudget() < $fromBudget) {
            throw ValidationException::withMessages([
                'budget' => sprintf(
                    '%s has %d Credit%s of budget left and %d Alert%s: %s costs %d.',
                    $run->facility->name,
                    $state->unspentBudget(),
                    $state->unspentBudget() === 1 ? '' : 's',
                    $run->alertsAvailable(),
                    $run->alertsAvailable() === 1 ? '' : 's',
                    $reason,
                    $amount,
                ),
            ]);
        }

        if ($fromAlerts > 0) {
            $run->forceFill(['alerts_spent' => $run->alerts_spent + $fromAlerts])->save();
        }

        if ($fromBudget > 0) {
            $state->forceFill([
                'security_budget_spent' => $state->security_budget_spent + $fromBudget,
            ])->save();
        }

        return ['alerts' => $fromAlerts, 'budget' => $fromBudget];
    }

    /**
     * The gangs tied on the highest Notoriety in the game.
     *
     * Counted across the whole game rather than only the gangs in the queue,
     * which is what "the gang with the highest Notoriety" says - so the
     * tiebreak can legitimately settle nothing. Where two gangs tie at the top
     * the rulebook is silent, so members of either count.
     *
     * @return array<int, int>
     */
    protected function mostNotoriousGangIds(Turn $turn): array
    {
        $gangs = Gang::query()->where('game_id', $turn->game_id)->get();
        $highest = (int) $gangs->max('notoriety');

        return $gangs
            ->where('notoriety', $highest)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * A character reduced to the three things run ordering asks about.
     *
     * @return array{gang_id: int|null, brawn: int, hack: int}
     */
    protected function profile(Character $character): array
    {
        return [
            'gang_id' => $character->gang_id,
            'brawn' => $character->brawn,
            'hack' => $character->hack,
        ];
    }

    /**
     * Whoever was last on the run, for 3.4.4's consolation payment.
     */
    protected function lastRunner(Run $run): ?Character
    {
        $active = $run->activeParticipants();

        if ($active->isNotEmpty()) {
            $leader = $active->firstWhere('character_id', $run->run_leader_character_id);

            return ($leader ?? $active->first())->character;
        }

        return $run->participants
            ->sortBy([['left_at', 'desc'], ['position', 'desc']])
            ->first()?->character;
    }

    protected function requireRunning(Run $run): void
    {
        if ($run->status !== RunStatus::Running) {
            throw ValidationException::withMessages([
                'status' => sprintf('That run is %s.', $run->status->label()),
            ]);
        }
    }

    /**
     * The cursor, having checked there is a card in front of the Runners.
     */
    protected function requireCard(Run $run): RunCursor
    {
        $this->requireRunning($run);

        $cursor = $this->cursor($run);

        if (! $cursor->hasCard()) {
            throw ValidationException::withMessages([
                'card' => 'There are no Protection Cards left in that Facility.',
            ]);
        }

        return $cursor;
    }

    protected function requireActiveRunner(Run $run, ?Character $character): Character
    {
        if ($character === null) {
            throw ValidationException::withMessages([
                'character_id' => 'Somebody has to take that consequence.',
            ]);
        }

        $participant = $run->participants->firstWhere('character_id', $character->id);

        if ($participant === null || ! $participant->isActive()) {
            throw ValidationException::withMessages([
                'character_id' => sprintf('%s is not on that run.', $character->name),
            ]);
        }

        return $character;
    }

    /**
     * Write one line of the log.
     *
     * @param  array<string, mixed>|null  $payload
     */
    protected function record(
        Run $run,
        string $type,
        string $description,
        ?User $actor = null,
        ?Character $character = null,
        ?FacilityProtectionCard $card = null,
        ?int $pass = null,
        ?RunStep $step = null,
        ?array $payload = null,
    ): RunEvent {
        /** @var RunEvent $event */
        $event = RunEvent::create([
            'run_id' => $run->id,
            'pass' => $pass ?? 0,
            'step' => $step ?? RunStep::Activate,
            'type' => $type,
            'actor_user_id' => $actor?->id,
            'character_id' => $character?->id,
            'facility_protection_card_id' => $card?->id,
            'payload' => $payload,
            'description' => $description,
        ]);

        $run->unsetRelation('events');

        return $event;
    }

    /**
     * Keep the faces.
     *
     * @param  array<int, int>  $faces
     */
    protected function recordRoll(
        Run $run,
        ?RunEvent $event,
        DiceRoller $roller,
        int $pool,
        int $dieFaces,
        array $faces,
        int $successes,
        string $reason,
        ?Character $character = null,
    ): RunDiceRoll {
        /** @var RunDiceRoll */
        return RunDiceRoll::create([
            'run_id' => $run->id,
            'run_event_id' => $event?->id,
            'roller' => $roller,
            'character_id' => $character?->id,
            'pool' => $pool,
            'die_faces' => $dieFaces,
            'threshold' => DicePool::SUCCESS_ON,
            'faces' => $faces,
            'successes' => $successes,
            'reason' => $reason,
        ]);
    }

    protected function ordinal(int $position): string
    {
        return match ($position) {
            1 => 'first',
            2 => 'second',
            3 => 'third',
            4 => 'fourth',
            default => $position.'th',
        };
    }
}
