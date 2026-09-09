<?php

namespace App\Support;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\PhaseType;
use App\Enums\RunStatus;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\Game;
use App\Models\Run;
use App\Models\RunDiceRoll;
use App\Models\RunEvent;
use App\Models\RunParticipant;
use App\Models\Turn;
use App\Models\User;
use App\Services\RunEngine;
use App\Support\Runs\AlertSchedule;
use App\Support\Runs\RunCursor;
use Illuminate\Support\Facades\Gate;

/**
 * A run as each side of it may see it (rulebook 3.4).
 *
 * Like the Council, almost all of this class is about who sees what - and a run
 * keeps two secrets rather than one, which is why the two sides get views built
 * separately instead of one payload with things taken out of it.
 *
 * **A Facility's stack depth is Secret** (3.4.1, footnote 11). So the Runners
 * are never told how many cards are left. They find out by running out of
 * cards, which is the whole tension of the Breather: leaving now costs you what
 * you have already paid for, and you cannot know whether you were one card from
 * the end.
 *
 * **A card is face down until it is Active.** Security is reading their own
 * stack, which 3.4.2 keeps Secret from everyone else and not from them, so they
 * see the card they are deciding whether to switch on. The Runners see it when
 * it is flipped - and if Security leaves it off, they get past a card they
 * never learn the name of.
 *
 * Control sees what Security sees, everywhere, because a ruling must not wait
 * on somebody being at their laptop.
 */
class RunPresenter
{
    public function __construct(private readonly RunEngine $engine) {}

    /**
     * The Facility game as this player sees it.
     *
     * @return array<string, mixed>
     */
    public function forPlayer(Game $game, ?User $user): array
    {
        $turn = $game->currentTurn();
        $phase = $game->currentPhase();
        $isControl = $user?->isControlFor($game) ?? false;

        return [
            'turn' => $turn?->number,
            'is_action_phase' => $phase?->type === PhaseType::Action,
            'is_control' => $isControl,
            'can_submit' => $user !== null && Gate::forUser($user)->allows('submit', [Run::class, $game]),
            'targets' => $this->targets($game, $turn),
            'party' => $user === null ? [] : $this->party($game, $user),
            // The runs this player is on, seen from inside the Facility.
            'yours' => $this->runsFor($game, $turn, $user, defending: false),
            // The runs coming at this player's own Facilities, seen from the
            // Security desk. Control gets every run in the game here, because
            // Control is the one seat that has to be able to run either side.
            'defending' => $this->runsFor($game, $turn, $user, defending: true),
        ];
    }

    /**
     * Every Facility a group could name, which is the public list and nothing
     * more.
     *
     * A Runner choosing a target knows what the `#facility-list` embed knows:
     * the Corporation, the Facility and its type. Not the stack, not its depth,
     * and not whether Security is Directing there - reconnaissance is supposed
     * to cost something.
     *
     * @return array<int, array<string, mixed>>
     */
    private function targets(Game $game, ?Turn $turn): array
    {
        return $game->corporations()
            ->with(['facilities' => fn ($query) => $query->orderBy('name'), 'facilities.facilityType'])
            ->orderBy('name')
            ->get()
            ->flatMap(fn (Corporation $corporation): array => $corporation->facilities
                ->filter(fn (Facility $facility): bool => $facility->isAvailableOnTurn($turn?->number))
                ->map(fn (Facility $facility): array => [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'facility_type' => $facility->facilityType->name,
                    'corporation' => FactionBadge::for($corporation->name),
                ])
                ->values()
                ->all())
            ->all();
    }

    /**
     * The Runners this player could take with them.
     *
     * Their own claimed characters first, then everybody else who could run,
     * because a group is assembled at the table out of whoever is up for it and
     * nothing says they share a gang. Anyone already out on a run this turn is
     * left out rather than shown and refused.
     *
     * The numbers travel with the names because 3.4.5 asks the group to work
     * out its dice pool in advance, and this is where they would do it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function party(Game $game, User $user): array
    {
        $turn = $game->currentTurn();

        $onRunThisTurn = $turn === null
            ? []
            : RunParticipant::query()
                ->whereHas('run', fn ($query) => $query->where('turn_id', $turn->id))
                ->pluck('character_id')
                ->all();

        return $game->characters()
            ->whereIn('role', [CharacterRole::Runner, CharacterRole::Freelancer])
            ->with('gang')
            ->orderBy('name')
            ->get()
            ->reject(fn (Character $character): bool => in_array($character->id, $onRunThisTurn, true))
            ->map(fn (Character $character): array => [
                'id' => $character->id,
                'name' => $character->name,
                'is_yours' => $character->user_id === $user->id,
                'gang' => $character->gang === null ? null : FactionBadge::for($character->gang->name),
                'brawn' => $character->brawn,
                'hack' => $character->hack,
                'body' => $character->body,
                'wounds' => $character->wounds,
                'tags' => $character->tags,
                'incapacitated' => $character->isIncapacitated(),
            ])
            ->values()
            ->all();
    }

    /**
     * This turn's runs, from one side or the other.
     *
     * @return array<int, array<string, mixed>>
     */
    private function runsFor(Game $game, ?Turn $turn, ?User $user, bool $defending): array
    {
        if ($user === null || $turn === null) {
            return [];
        }

        $runs = Run::query()
            ->where('turn_id', $turn->id)
            ->with([
                'facility.corporation',
                'facility.facilityType',
                'participants.character.gang',
                'leader',
                'events.character',
                'events.card.cardType',
                'diceRolls',
            ])
            ->orderBy('order_index')
            ->orderBy('id')
            ->get()
            ->filter(fn (Run $run): bool => Gate::forUser($user)->allows('view', $run));

        $gate = Gate::forUser($user);

        return $runs
            ->filter(fn (Run $run): bool => $defending
                ? ! $this->isOnRun($run, $user)
                : $this->isOnRun($run, $user))
            ->map(fn (Run $run): array => $this->run(
                $run,
                $user,
                // Control reads a run from the Security side even when nobody
                // on their Control team is defending it, because the side that
                // sees everything is the side Control has to be able to sit on.
                privileged: $gate->allows('defend', $run) || ($user->isControlFor($game)),
            ))
            ->values()
            ->all();
    }

    /**
     * One run.
     *
     * @return array<string, mixed>
     */
    private function run(Run $run, User $user, bool $privileged): array
    {
        $cursor = $this->engine->cursor($run);
        $gate = Gate::forUser($user);
        $state = $run->facility->stateForTurn($run->turn);

        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'facility' => [
                'id' => $run->facility->id,
                'name' => $run->facility->name,
                'facility_type' => $run->facility->facilityType->name,
                'corporation' => FactionBadge::for($run->facility->corporation->name),
            ],
            'order_index' => $run->order_index,
            'order_reason' => $run->order_reason,

            // Alerts are the Runners' own doing - their Tags, their group size -
            // so both sides see the pool. What Security has spent it on is
            // visible too: at the table the Alert tokens physically move.
            'alerts' => $run->alerts,
            'alerts_spent' => $run->alerts_spent,
            'alerts_available' => $run->alertsAvailable(),
            'alert_strength_bonus' => AlertSchedule::strengthBonus($run->alertsAvailable()),
            'next_alert_threshold' => AlertSchedule::nextStrengthThreshold($run->alertsAvailable()),

            'cards_passed' => $run->cards_passed,
            'active_cards_passed' => $run->active_cards_passed,
            'ignored_end_the_run' => $run->ignored_end_the_run,
            'retry_pending' => $run->retry_pending,

            'pass' => $cursor->pass,
            'step' => $cursor->step->value,
            'step_label' => $cursor->step->label(),

            // Footnote 11 makes the depth of a stack Secret, so this is the one
            // number that is withheld from the Runners outright rather than
            // shown in less detail.
            'cards_remaining' => $privileged ? $cursor->cardsRemaining : null,

            'card' => $this->card($cursor, $privileged),

            'leader_character_id' => $run->run_leader_character_id,
            'participants' => $run->participants
                ->map(fn (RunParticipant $participant): array => [
                    'id' => $participant->id,
                    'character_id' => $participant->character_id,
                    'name' => $participant->character->name,
                    'position' => $participant->position,
                    'is_leader' => $participant->character_id === $run->run_leader_character_id,
                    'is_yours' => $participant->character->user_id === $user->id,
                    'gang' => $participant->character->gang === null
                        ? null
                        : FactionBadge::for($participant->character->gang->name),
                    'brawn' => $participant->character->brawn,
                    'hack' => $participant->character->hack,
                    'body' => $participant->character->body,
                    'wounds' => $participant->character->wounds,
                    'tags' => $participant->character->tags,
                    'left' => ! $participant->isActive(),
                    'left_reason' => $participant->left_reason?->label(),
                ])->all(),

            // The budget is the Corporation's business, and a Runner who could
            // read it would know exactly how much defence was left in the
            // Facility.
            'budget' => $privileged ? [
                'directed' => $state->security_directed,
                'placed' => $state->security_budget,
                'spent' => $state->security_budget_spent,
                'left' => $state->unspentBudget(),
            ] : null,

            'can_lead' => $gate->allows('lead', $run),
            'can_act' => $gate->allows('act', $run),
            'can_defend' => $gate->allows('defend', $run),

            'log' => $this->log($run, $privileged),
        ];
    }

    /**
     * The card in front of the Runners, as much of it as this side may see.
     *
     * An Inactive card is a card face down on the table: the Runners are told
     * there is something there and nothing else. Once it is flipped they get
     * the whole of it, because they have to read the challenge to roll against
     * it - and because whoever is running the card names the printed strength
     * from the sentence rather than from a column, so the sentence has to be on
     * screen.
     *
     * @return array<string, mixed>|null
     */
    private function card(RunCursor $cursor, bool $privileged): ?array
    {
        if (! $cursor->hasCard()) {
            return null;
        }

        /** @var FacilityProtectionCard $card */
        $card = $cursor->card;
        $active = $cursor->cardIsActive();
        $activation = $cursor->activation;

        $shell = [
            'id' => $card->id,
            'kind' => $card->kind->value,
            'kind_label' => $card->kind->label(),
            'position' => $card->position,
            'active' => $active,
            'settled' => $cursor->activationSettled(),
            'boosts' => $activation === null ? 0 : $activation->boosts,
            'next_boost_cost' => $activation === null ? 1 : $activation->nextBoostCost(),
            'activation_cost' => $activation?->activation_cost,
        ];

        if (! $active && ! $privileged) {
            // Face down. Even the kind is fair game - the Runners know they are
            // through the physical stack and into the cyber one, because they
            // can see where they are standing.
            return $shell;
        }

        return [
            ...$shell,
            'name' => $card->cardType->name,
            'code' => $card->cardType->code,
            'challenge' => $card->cardType->challenge,
            'consequence' => $card->cardType->consequence,
            'charge_cost' => $card->cardType->charge_cost,
            'charge_consequence' => $card->cardType->charge_consequence,
            'image_path' => $card->cardType->imagePath(),
        ];
    }

    /**
     * The log, with the rolls that decided each line.
     *
     * Shown to both sides in full, and that is deliberate: this is the record
     * that settles an argument, and a log one side could not read would settle
     * nothing. It cannot leak the stack depth, because it only ever describes
     * cards the Runners have already met.
     *
     * The exception is a card that never came on. Security's decision not to
     * activate names the card, and the Runners are not entitled to that, so
     * those lines read as the card being left off without saying which card it
     * was.
     *
     * @return array<int, array<string, mixed>>
     */
    private function log(Run $run, bool $privileged): array
    {
        $rolls = $run->diceRolls->groupBy('run_event_id');

        return $run->events
            ->map(fn (RunEvent $event): array => [
                'id' => $event->id,
                'pass' => $event->pass,
                'step' => $event->step->value,
                'type' => $event->type,
                'description' => $this->describe($event, $privileged),
                'character' => $event->character?->name,
                'at' => $event->created_at?->toIso8601String(),
                'rolls' => $rolls->get($event->id, collect())
                    ->map(fn (RunDiceRoll $roll): array => [
                        'roller' => $roll->roller->value,
                        'roller_label' => $roll->roller->label(),
                        'pool' => $roll->pool,
                        'die_faces' => $roll->die_faces,
                        'faces' => $roll->faces,
                        'successes' => $roll->successes,
                        'readout' => $roll->readout(),
                        'reason' => $privileged ? $roll->reason : null,
                    ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * One line of the log, with anything the Runners may not read taken out.
     */
    private function describe(RunEvent $event, bool $privileged): string
    {
        if ($privileged) {
            return $event->description;
        }

        return match ($event->type) {
            RunEvent::TYPE_ACTIVATION_DECLINED => 'Security left the card switched off.',
            RunEvent::TYPE_ACTIVATION_FAILED => 'Security could not afford to switch the card on, so it stayed off.',
            default => $event->description,
        };
    }

    /**
     * Whether this user holds a character on the run.
     */
    private function isOnRun(Run $run, User $user): bool
    {
        return $run->participants
            ->contains(fn (RunParticipant $participant): bool => $participant->character->user_id === $user->id);
    }

    /**
     * Every run in the game this turn, for Control's own panel.
     *
     * Control has no secrets kept from it, so this is the privileged view of
     * everything - including the runs that have been submitted and not yet
     * ordered, which is the pile Control actually has to act on.
     *
     * @return array<string, mixed>
     */
    public function forControl(Game $game): array
    {
        $turn = $game->currentTurn();

        if ($turn === null) {
            return ['turn' => null, 'runs' => [], 'queues' => []];
        }

        $runs = Run::query()
            ->where('turn_id', $turn->id)
            ->with([
                'facility.corporation',
                'facility.facilityType',
                'participants.character.gang',
                'leader',
                'events.character',
                'events.card.cardType',
                'diceRolls',
            ])
            ->orderBy('facility_id')
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        return [
            'turn' => $turn->number,
            'game_running' => $game->status === GameStatus::Running,
            'runs' => $runs
                ->map(fn (Run $run): array => $this->control($run))
                ->all(),
            // The Facilities with more than one group at them, which are the
            // only ones where the ordering of 3.4.1 has anything to decide.
            'queues' => $runs
                ->filter(fn (Run $run): bool => ! $run->status->isFinished())
                ->groupBy('facility_id')
                ->filter(fn (mixed $group): bool => $group->count() > 1)
                ->map(fn (mixed $group): array => [
                    'facility_id' => $group->first()->facility_id,
                    'facility' => $group->first()->facility->name,
                    'corporation' => FactionBadge::for($group->first()->facility->corporation->name),
                    'runs' => $group->count(),
                    'ordered' => $group->every(fn (Run $run): bool => $run->order_index !== null),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * One run for Control, which is the same shape a Security player gets
     * without asking the Gate anything.
     *
     * @return array<string, mixed>
     */
    private function control(Run $run): array
    {
        $cursor = $this->engine->cursor($run);
        $state = $run->facility->stateForTurn($run->turn);

        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'is_finished' => $run->status->isFinished(),
            'is_running' => $run->status === RunStatus::Running,
            'facility' => [
                'id' => $run->facility->id,
                'name' => $run->facility->name,
                'facility_type' => $run->facility->facilityType->name,
                'corporation' => FactionBadge::for($run->facility->corporation->name),
            ],
            'order_index' => $run->order_index,
            'order_reason' => $run->order_reason,
            'alerts' => $run->alerts,
            'alerts_available' => $run->alertsAvailable(),
            'cards_passed' => $run->cards_passed,
            'active_cards_passed' => $run->active_cards_passed,
            'cards_remaining' => $cursor->cardsRemaining,
            'pass' => $cursor->pass,
            'step' => $cursor->step->value,
            'step_label' => $cursor->step->label(),
            'retry_pending' => $run->retry_pending,
            'ignored_end_the_run' => $run->ignored_end_the_run,
            'card' => $this->card($cursor, privileged: true),
            'leader' => $run->leader?->name,
            'leader_character_id' => $run->run_leader_character_id,
            'participants' => $run->participants
                ->map(fn (RunParticipant $participant): array => [
                    'character_id' => $participant->character_id,
                    'name' => $participant->character->name,
                    'position' => $participant->position,
                    'is_leader' => $participant->character_id === $run->run_leader_character_id,
                    'wounds' => $participant->character->wounds,
                    'body' => $participant->character->body,
                    'tags' => $participant->character->tags,
                    'brawn' => $participant->character->brawn,
                    'hack' => $participant->character->hack,
                    'left' => ! $participant->isActive(),
                    'left_reason' => $participant->left_reason?->label(),
                ])->all(),
            'budget' => [
                'directed' => $state->security_directed,
                'placed' => $state->security_budget,
                'spent' => $state->security_budget_spent,
                'left' => $state->unspentBudget(),
            ],
            'log' => $this->log($run, privileged: true),
        ];
    }
}
