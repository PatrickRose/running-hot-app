<?php

namespace App\Services;

use App\Enums\DiceRoller;
use App\Enums\ProtectionKind;
use App\Enums\RunAccessKind;
use App\Enums\RunConsequence;
use App\Enums\RunDeparture;
use App\Enums\RunnerSkill;
use App\Enums\RunStatus;
use App\Enums\RunStep;
use App\Enums\TechnologyAccessAction;
use App\Enums\TechnologyHoldingStatus;
use App\Enums\TechnologyOrigin;
use App\Enums\Tracker;
use App\Jobs\SyncRunChannelAccess;
use App\Models\Character;
use App\Models\Facility;
use App\Models\FacilityCardActivation;
use App\Models\FacilityProtectionCard;
use App\Models\Gang;
use App\Models\Run;
use App\Models\RunAccess;
use App\Models\RunDiceRoll;
use App\Models\RunEvent;
use App\Models\RunParticipant;
use App\Models\TechnologyHolding;
use App\Models\Turn;
use App\Models\User;
use App\Support\Runs\AccessCheck;
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
     * carrying right now plus a bonus for its size. The group bonus takes an
     * override, because Control can overrule any number in the game.
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
     * Security names the card's strength and rolls its defence (rulebook 3.4.2).
     *
     * Its own act, before the Runners throw anything. Security is a player at
     * the table holding the card, and the sentence on it is theirs to read:
     * "Brute (6)", "Brute/Hack (2)", "Hack (4+N) - where N is the number of
     * cards underneath this". There is no parsed strength column to read it
     * off, so they name the printed number and this says what happens to it -
     * +1 per 2 Active cards the Runners are past, the Alert curve, and whatever
     * has been spent Boosting this card.
     *
     * The breakdown goes in the event and the roll keeps every face, so a
     * Runner asking where "strength 6" came from gets printed 3, +1 for the
     * four cards you are past, +1 at three Alerts, +1 Boost rather than a
     * number to take on trust.
     */
    public function defend(
        Run $run,
        int $printedStrength,
        ?User $actor = null,
        ?int $alertStrengthOverride = null,
    ): RunEvent {
        $cursor = $this->requireCard($run);

        if (! $cursor->cardIsActive()) {
            throw ValidationException::withMessages([
                'defend' => 'An Inactive card is walked straight past - there is nothing to defend.',
            ]);
        }

        $events = $this->passEvents($run, $cursor->pass);

        if ($events->contains('type', RunEvent::TYPE_DEFENDED)) {
            throw ValidationException::withMessages([
                'defend' => 'Security has already rolled for that card this pass.',
            ]);
        }

        if ($events->contains('type', RunEvent::TYPE_CHALLENGE)) {
            throw ValidationException::withMessages([
                'defend' => 'That card has already been challenged this pass.',
            ]);
        }

        /** @var FacilityCardActivation $activation */
        $activation = $cursor->activation;
        /** @var FacilityProtectionCard $card */
        $card = $cursor->card;

        $strength = ChallengeStrength::for(
            printed: $printedStrength,
            cardsPassed: $run->active_cards_passed,
            alerts: $run->alertsAvailable(),
            boosts: $activation->boosts,
            alertOverride: $alertStrengthOverride,
        );

        return DB::transaction(function () use ($run, $cursor, $card, $strength, $actor): RunEvent {
            $faces = $this->dice->roll($strength->total(), DicePool::HEALTHY_DIE);
            $successes = DicePool::countSuccesses($faces);

            $event = $this->record(
                $run,
                RunEvent::TYPE_DEFENDED,
                sprintf(
                    '%s defends at strength %s, and rolls %d success%s.',
                    $card->cardType->name,
                    $strength->explain(),
                    $successes,
                    $successes === 1 ? '' : 'es',
                ),
                actor: $actor,
                card: $card,
                pass: $cursor->pass,
                step: RunStep::Challenge,
                payload: [
                    'strength' => $strength->toArray(),
                    'security_successes' => $successes,
                ],
            );

            $this->recordRoll(
                $run,
                $event,
                DiceRoller::Security,
                $strength->total(),
                DicePool::HEALTHY_DIE,
                $faces,
                $successes,
                sprintf('%s defending, strength %s', $card->cardType->name, $strength->explain()),
            );

            return $event;
        });
    }

    /**
     * The Runners throw their dice at the card (rulebook 3.4.2).
     *
     * The Run Leader rolls their full skill and everybody else adds half of
     * theirs, or a quarter if they are Wounded - and the die size comes from
     * the Leader alone, so a Wounded Leader handing over before a hard card is
     * a real tactic. Both sides need 5 or better, and a tie goes to Security.
     *
     * Security has already rolled by the time this is reachable, which is why
     * no strength is named here: it was named by the people holding the card,
     * and this compares against what they actually threw rather than rolling
     * for them.
     */
    public function challenge(
        Run $run,
        RunnerSkill $skill,
        ?User $actor = null,
    ): ChallengeOutcome {
        $cursor = $this->requireCard($run);

        if (! $cursor->cardIsActive()) {
            throw ValidationException::withMessages([
                'challenge' => 'An Inactive card is walked straight past - there is nothing to break.',
            ]);
        }

        $events = $this->passEvents($run, $cursor->pass);

        if ($events->contains('type', RunEvent::TYPE_CHALLENGE)) {
            throw ValidationException::withMessages([
                'challenge' => 'That card has already been challenged this pass.',
            ]);
        }

        $defence = $events->firstWhere('type', RunEvent::TYPE_DEFENDED);

        if ($defence === null) {
            throw ValidationException::withMessages([
                'challenge' => 'Security has not rolled for that card yet.',
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

        // Both come off the roll Security already made. Re-deriving the
        // strength here would be a second implementation of the same sum, and
        // it would give a different answer the moment an Alert moved between
        // the two rolls - which is exactly what Security spending one does.
        $strength = new ChallengeStrength(
            printed: max(0, (int) ($defence->payload['strength']['printed'] ?? 0)),
            fromCardsPassed: max(0, (int) ($defence->payload['strength']['cards_passed'] ?? 0)),
            fromAlerts: max(0, (int) ($defence->payload['strength']['alerts'] ?? 0)),
            fromBoosts: max(0, (int) ($defence->payload['strength']['boosts'] ?? 0)),
        );

        $securitySuccesses = max(0, (int) ($defence->payload['security_successes'] ?? 0));

        /** @var RunDiceRoll $securityRoll */
        $securityRoll = $run->diceRolls()
            ->where('run_event_id', $defence->id)
            ->where('roller', DiceRoller::Security)
            ->firstOrFail();

        /** @var FacilityProtectionCard $card */
        $card = $cursor->card;

        return DB::transaction(function () use ($run, $cursor, $card, $skill, $pool, $strength, $securitySuccesses, $securityRoll, $actor): ChallengeOutcome {
            $runnerFaces = $this->dice->roll($pool->total(), $pool->dieFaces);

            $runnerSuccesses = DicePool::countSuccesses($runnerFaces);
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
    // ------------------------------------------------------------------
    // Accesses (3.4.3)
    // ------------------------------------------------------------------

    /**
     * How many accesses a Runner has left inside a Facility they broke into.
     *
     * "For each Runner in the group, they receive one access." One each, and
     * spending it is the whole of what they get - a failed copy or a steal that
     * missed still costs the access, because 3.4.3 says the card goes back on
     * the list and "you may attempt to access it again", which only means
     * anything if the first attempt was spent.
     *
     * Equipment may give a Runner more (footnote 13) and nobody's Equipment is
     * modelled, so that arrives with the Equipment holdings rather than here.
     */
    public function accessesLeft(Run $run, Character $runner): int
    {
        if ($run->status !== RunStatus::Succeeded) {
            return 0;
        }

        $onTheRun = $run->participants()
            ->where('character_id', $runner->id)
            ->exists();

        if (! $onTheRun) {
            return 0;
        }

        $spent = $run->accesses()->where('character_id', $runner->id)->count();

        return max(0, 1 - $spent);
    }

    /**
     * The Facility's Credits card (rulebook 3.4.3).
     *
     * One card in the building, so the first Runner to reach for it takes it
     * and the rest find it gone. The amount is read off what the Facility is
     * actually holding rather than set anywhere: the Protection Cards installed
     * in it - installed, not activated, because a card Security could not
     * afford to switch on is still a card in the building - and the
     * technologies stored there.
     */
    public function takeCredits(Run $run, Character $runner, ?User $actor = null): RunAccess
    {
        $this->requireAccess($run, $runner, RunAccessKind::Credits);

        $credits = RunRewards::forCreditsCard(
            protectionCards: $run->facility->protectionCards()->count(),
            technologies: $this->storedTechnologies($run)->count(),
        );

        return DB::transaction(function () use ($run, $runner, $credits, $actor): RunAccess {
            $this->trackers->adjust(
                $runner,
                Tracker::CharacterCredits,
                $credits,
                sprintf('Credits card from %s', $run->facility->name),
                $actor,
            );

            return $this->recordAccess(
                $run,
                $runner,
                RunAccessKind::Credits,
                sprintf(
                    '%s took the Credits card from %s: %d Credit%s.',
                    $runner->name,
                    $run->facility->name,
                    $credits,
                    $credits === 1 ? '' : 's',
                ),
                ['credits' => $credits],
                $actor,
            );
        });
    }

    /**
     * The Facility type's own effect (rulebook 3.4.3).
     *
     * What it does is stored as words on the type and not read by anything:
     * spying on a rival's stack, a blackmail file, a stock certificate. Those
     * are conversations, so this records that the effect was taken and leaves
     * the conversation to happen - which is the one place Control is still
     * wanted, and only to hand over what the Runner has already won.
     */
    public function takeFacilityEffect(Run $run, Character $runner, ?User $actor = null): RunAccess
    {
        $this->requireAccess($run, $runner, RunAccessKind::FacilityEffect);

        $effect = $run->facility->facilityType->access_effect;

        if (blank($effect)) {
            throw ValidationException::withMessages([
                'access' => sprintf(
                    'A %s Facility has no access effect to take.',
                    $run->facility->facilityType->name,
                ),
            ]);
        }

        return $this->recordAccess(
            $run,
            $runner,
            RunAccessKind::FacilityEffect,
            sprintf(
                '%s used the %s Facility\'s own effect: %s',
                $runner->name,
                $run->facility->facilityType->name,
                $effect,
            ),
            [],
            $actor,
        );
    }

    /**
     * An access spent on something only Control can answer (3.4.3, 3.4.4).
     *
     * A Technology Location card, a plot thread, anything the Runner came in
     * for that the application does not model. It takes the access and says so
     * on the run, so the Runner has a record of having spent it and Control has
     * a queue to work through.
     */
    public function takePlotAccess(
        Run $run,
        Character $runner,
        ?string $note = null,
        ?User $actor = null,
    ): RunAccess {
        $this->requireAccess($run, $runner, RunAccessKind::Plot);

        return $this->recordAccess(
            $run,
            $runner,
            RunAccessKind::Plot,
            sprintf(
                '%s spent an access on a plot lead at %s. Control\'s to answer.',
                $runner->name,
                $run->facility->name,
            ),
            ['notes' => $note],
            $actor,
        );
    }

    /**
     * Copy, steal or destroy a technology stored in the Facility (3.4.3).
     *
     * **The card is drawn rather than chosen.** The rulebook has the Run Leader
     * pick from the list and the Security player reveal it; at the table that
     * is a person holding cards face down and fanning them out, so the drawn
     * card is the same thing without somebody to hold them. A card another
     * Runner has already been at this run is out of the draw.
     *
     * The dice are the group's: "the combination of your Brawn and Hack" in
     * full for whoever is spending the access, and the usual half or quarter
     * from everybody else. Unlike a Protection Card there is no opposing roll
     * and no consequence for failing - what the successes buy is read off a
     * printed band, and a card that survives the attempt is simply still there.
     */
    public function accessTechnology(
        Run $run,
        Character $runner,
        TechnologyAccessAction $action,
        ?User $actor = null,
    ): RunAccess {
        $this->requireAccess($run, $runner, RunAccessKind::Technology);

        $available = $this->storedTechnologies($run)
            ->reject(fn (TechnologyHolding $holding): bool => in_array(
                $holding->id,
                $run->accesses()->whereNotNull('technology_holding_id')->pluck('technology_holding_id')->all(),
                true,
            ));

        if ($available->isEmpty()) {
            throw ValidationException::withMessages([
                'access' => 'There is nothing left in this Facility to access.',
            ]);
        }

        // A one-sided die is not a die, so a Facility down to its last card
        // simply hands it over rather than being rolled for - the same rule the
        // Run Leader handover follows.
        $choices = $available->count();
        $index = 0;

        if ($choices > 1) {
            $index = $this->dice->roll(1, $choices)[0] - 1;
        }

        /** @var TechnologyHolding $holding */
        $holding = $available->values()->get($index);

        $pool = $this->accessPool($run, $runner);

        return DB::transaction(function () use ($run, $runner, $action, $holding, $pool, $actor): RunAccess {
            $faces = $this->dice->roll($pool->total(), $pool->dieFaces);
            $successes = DicePool::countSuccesses($faces);

            [$outcome, $description, $discount] = $this->resolveTechnologyAccess(
                $run,
                $runner,
                $action,
                $holding,
                $successes,
            );

            $access = $this->recordAccess(
                $run,
                $runner,
                RunAccessKind::Technology,
                $description,
                [
                    'technology_holding_id' => $holding->id,
                    'action' => $action,
                    'successes' => $successes,
                    'outcome' => $outcome,
                    'discount_percent' => $discount,
                ],
                $actor,
                $event,
            );

            $this->recordRoll(
                $run,
                $event,
                DiceRoller::Runners,
                $pool->total(),
                $pool->dieFaces,
                $faces,
                $successes,
                sprintf('%s on %s', $action->label(), $holding->technologyType->name),
            );

            return $access;
        });
    }

    /**
     * What one access did to one technology, and what to say about it.
     *
     * The three actions share a shape and nothing else: a copy leaves the card
     * where it is and produces a discount for whoever buys the copy off the
     * Runner, a theft takes the card, and a destroy only removes it on the last
     * band. Everything short of that leaves traces, which is a discount for the
     * Corporation to research it again - Control's to apply, because the
     * rulebook prints no percentages for it.
     *
     * @return array{0: string, 1: string, 2: int|null}
     */
    protected function resolveTechnologyAccess(
        Run $run,
        Character $runner,
        TechnologyAccessAction $action,
        TechnologyHolding $holding,
        int $successes,
    ): array {
        $name = $holding->technologyType->name;

        return match ($action) {
            TechnologyAccessAction::Copy => $this->resolveCopy($runner, $name, $successes),
            TechnologyAccessAction::Steal => $this->resolveSteal($runner, $holding, $name, $successes),
            TechnologyAccessAction::Destroy => $this->resolveDestroy($runner, $holding, $name, $successes),
        };
    }

    /**
     * @return array{0: string, 1: string, 2: int|null}
     */
    protected function resolveCopy(Character $runner, string $name, int $successes): array
    {
        $discount = AccessCheck::copyDiscount($successes);

        if ($discount === null) {
            return ['failed', sprintf(
                '%s failed to copy %s. The card is untouched.',
                $runner->name,
                $name,
            ), null];
        }

        // The card itself never moves for a copy, so there is nothing to write
        // on the holding: what the Runner is carrying out is a copy to sell,
        // and it becomes somebody's technology_holdings row when they sell it.
        return [
            $discount === 50 ? 'good_copy' : 'weak_copy',
            sprintf(
                '%s made a %s copy of %s on %d success%s: %d%% off for whoever buys it.',
                $runner->name,
                $discount === 50 ? 'good' : 'weak',
                $name,
                $successes,
                $successes === 1 ? '' : 'es',
                $discount,
            ),
            $discount,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: int|null}
     */
    protected function resolveSteal(
        Character $runner,
        TechnologyHolding $holding,
        string $name,
        int $successes,
    ): array {
        if (! AccessCheck::stealSucceeds($successes)) {
            return ['failed', sprintf(
                '%s tried to steal %s and missed: %d success%s against %d.',
                $runner->name,
                $name,
                $successes,
                $successes === 1 ? '' : 'es',
                AccessCheck::STEAL_STRENGTH,
            ), null];
        }

        $holding->forceFill([
            'status' => TechnologyHoldingStatus::Stolen,
            'facility_id' => null,
        ])->save();

        return ['stolen', sprintf(
            '%s stole %s on %d successes. The card is out of the building.',
            $runner->name,
            $name,
            $successes,
        ), TechnologyOrigin::Stolen->defaultDiscountPercent()];
    }

    /**
     * @return array{0: string, 1: string, 2: int|null}
     */
    protected function resolveDestroy(
        Character $runner,
        TechnologyHolding $holding,
        string $name,
        int $successes,
    ): array {
        $band = AccessCheck::destroyBand($successes);

        if ($band === null) {
            return ['failed', sprintf(
                '%s failed to destroy %s. The card is untouched.',
                $runner->name,
                $name,
            ), null];
        }

        if (AccessCheck::destroyIsTotal($band)) {
            $holding->forceFill([
                'status' => TechnologyHoldingStatus::Destroyed,
                'facility_id' => null,
                'destroyed_at' => now(),
            ])->save();

            return ['destroyed', sprintf(
                '%s destroyed %s outright on %d successes: no useful traces left.',
                $runner->name,
                $name,
                $successes,
            ), null];
        }

        // Every band short of the last leaves the card standing and leaves the
        // Corporation able to research it again at a discount. The rulebook
        // prints no percentage for that, so nothing is written on the holding
        // and the band is what Control reads.
        return ['damaged_'.$band, sprintf(
            '%s damaged %s on %d successes, reaching the %d-success band. It can be researched again at a discount Control names.',
            $runner->name,
            $name,
            $successes,
            $band,
        ), null];
    }

    /**
     * Whether this Runner may spend an access, and whether the kind is left.
     */
    protected function requireAccess(Run $run, Character $runner, RunAccessKind $kind): void
    {
        if ($run->status !== RunStatus::Succeeded) {
            throw ValidationException::withMessages([
                'access' => 'Only a successful run gets inside to take anything.',
            ]);
        }

        if ($this->accessesLeft($run, $runner) < 1) {
            throw ValidationException::withMessages([
                'access' => sprintf('%s has no access left on this run.', $runner->name),
            ]);
        }

        if ($kind->onlyOncePerRun() && $run->accesses()->where('kind', $kind)->exists()) {
            throw ValidationException::withMessages([
                'access' => sprintf(
                    'The %s has already been taken out of this Facility.',
                    strtolower($kind->label()),
                ),
            ]);
        }
    }

    /**
     * The technologies actually sitting in the Facility.
     *
     * Only the ones that occupy storage, which is what keeps a card an earlier
     * run destroyed or stole out of both the Credits sum and the draw.
     *
     * @return Collection<int, TechnologyHolding>
     */
    protected function storedTechnologies(Run $run): Collection
    {
        return $run->facility->technologyHoldings()
            ->with('technologyType')
            ->get()
            ->filter(fn (TechnologyHolding $holding): bool => $holding->status->occupiesStorage())
            ->values();
    }

    /**
     * The dice a group throws at a technology (rulebook 3.4.3).
     *
     * "The dice pool is equal to your full skill level (the combination of your
     * Brawn and Hack)" - both, added, rather than one of them, which is the
     * whole difference from a Protection Card. Everybody else adds their share
     * of the same combined score, and the die size comes from the Runner
     * spending the access rather than from the Run Leader: they are the one
     * with their hands on the card.
     */
    protected function accessPool(Run $run, Character $runner): DicePool
    {
        $others = $run->activeParticipants()
            ->reject(fn (RunParticipant $p): bool => $p->character_id === $runner->id)
            ->mapWithKeys(fn (RunParticipant $p): array => [$p->character_id => [
                'skill' => $p->character->brawn + $p->character->hack,
                'wounded' => $p->character->wounds > 0,
            ]])
            ->all();

        return DicePool::for(
            leaderSkill: $runner->brawn + $runner->hack,
            leaderWounded: $runner->wounds > 0,
            others: $others,
        );
    }

    /**
     * Write the access down, on the run and in the log.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function recordAccess(
        Run $run,
        Character $runner,
        RunAccessKind $kind,
        string $description,
        array $attributes = [],
        ?User $actor = null,
        ?RunEvent &$event = null,
    ): RunAccess {
        $event = $this->record(
            $run,
            RunEvent::TYPE_ACCESS,
            $description,
            actor: $actor,
            character: $runner,
            step: RunStep::Breather,
            payload: ['kind' => $kind->value] + array_map(
                fn (mixed $value): mixed => $value instanceof TechnologyAccessAction ? $value->value : $value,
                $attributes,
            ),
        );

        return RunAccess::create([
            'run_id' => $run->id,
            'character_id' => $runner->id,
            'kind' => $kind,
            ...$attributes,
        ]);
    }

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
                'The run on %s succeeded: %d Protection Card%s broken. %d Runner%s inside, one access each (3.4.3).',
                $run->facility->name,
                $run->cards_passed,
                $run->cards_passed === 1 ? '' : 's',
                $run->activeParticipants()->count(),
                $run->activeParticipants()->count() === 1 ? '' : 's',
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

        // Security has named the strength and thrown its dice, so what is left
        // of the Challenge step is the Runners throwing theirs. This is the one
        // place RunStep::Challenge is reached: before the two rolls were split
        // the whole step happened in a single act and the step never showed.
        if ($events->contains('type', RunEvent::TYPE_DEFENDED)) {
            return RunStep::Challenge;
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
