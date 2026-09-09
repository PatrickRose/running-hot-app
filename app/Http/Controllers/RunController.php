<?php

namespace App\Http\Controllers;

use App\Enums\RunConsequence;
use App\Enums\RunnerSkill;
use App\Http\Requests\SubmitRunRequest;
use App\Models\Character;
use App\Models\Facility;
use App\Models\Game;
use App\Models\Run;
use App\Services\RunEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The acts of a run, driven by the players doing them (rulebook 3.4).
 *
 * A thin thing on purpose. Every rule lives in {@see RunEngine} - what a card
 * costs to switch on, what a challenge is worth, who a consequence may land on,
 * what happens when the last Runner walks away - so a route can never grow its
 * own copy of one. What is here is who is asking, which the RunPolicy answers,
 * and turning form input into the arguments the engine takes.
 *
 * Every one of these is also reachable by Control, through the same routes and
 * the policy's before(): a run must never stall because a player has gone to
 * get a drink.
 */
class RunController extends Controller
{
    public function __construct(private readonly RunEngine $runs) {}

    /**
     * Put in for a run (rulebook 3.4.1).
     */
    public function store(SubmitRunRequest $request): RedirectResponse
    {
        $game = $request->game();
        /** @var Game $game */
        $turn = $game->currentTurn();

        if ($turn === null) {
            return back()->withErrors(['facility_id' => 'That game has not started a turn yet.']);
        }

        /** @var Facility $facility */
        $facility = Facility::query()->findOrFail($request->integer('facility_id'));
        /** @var Character $leader */
        $leader = Character::query()->findOrFail($request->integer('run_leader_character_id'));

        $run = $this->runs->submit(
            $turn,
            $facility,
            $leader,
            $request->memberCharacterIds(),
            $request->user(),
        );

        return back()->with('status', sprintf(
            'Run submitted against %s. It goes in when Control calls it.',
            $run->facility->name,
        ));
    }

    /**
     * Order the groups queueing at one Facility (rulebook 3.4.1).
     *
     * Control's alone - the RunPolicy gives no player this - because a group
     * that could order the queue could put itself at the front of it.
     */
    public function order(Facility $facility, Request $request): RedirectResponse
    {
        Gate::authorize('order', [Run::class, $facility->game]);

        $turn = $facility->game->currentTurn();

        if ($turn === null) {
            return back()->withErrors(['order' => 'That game has not started a turn yet.']);
        }

        $ordered = $this->runs->orderRuns($facility, $turn, $request->user());

        return back()->with('status', sprintf(
            '%d run%s ordered at %s.',
            $ordered->count(),
            $ordered->count() === 1 ? '' : 's',
            $facility->name,
        ));
    }

    /**
     * Go in (rulebook 3.4.1).
     *
     * The Leader's, because the Leader is who submitted it. The Alert override
     * is here for a group of more than six, where the rulebook stops printing
     * numbers and sends Control to a help sheet.
     */
    public function begin(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('lead', $run);

        $validated = $request->validate([
            'group_alert_override' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $run = $this->runs->begin(
            $run,
            $request->user(),
            $validated['group_alert_override'] ?? null,
        );

        return back()->with('status', sprintf(
            'In at %s, on %d Alert%s.',
            $run->facility->name,
            $run->alerts,
            $run->alerts === 1 ? '' : 's',
        ));
    }

    /**
     * Security's go at the card in front of the Runners (rulebook 3.4.2).
     *
     * `activating` is only ever false where Security is Directing here, which
     * is the engine's to refuse rather than this method's.
     */
    public function activate(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('defend', $run);

        $validated = $request->validate([
            'activating' => ['nullable', 'boolean'],
            'alerts_to_spend' => ['nullable', 'integer', 'min:0'],
        ]);

        $activation = $this->runs->activate(
            $run,
            $validated['activating'] ?? null,
            (int) ($validated['alerts_to_spend'] ?? 0),
            $request->user(),
        );

        return back()->with('status', $activation->isActive()
            ? 'The card is Active.'
            : 'The card stayed off, and the Runners walk past it.');
    }

    /**
     * Make the card harder for the rest of the phase (rulebook 3.4.2).
     */
    public function boost(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('defend', $run);

        $validated = $request->validate([
            'times' => ['nullable', 'integer', 'min:1', 'max:9'],
            'alerts_to_spend' => ['nullable', 'integer', 'min:0'],
        ]);

        $activation = $this->runs->boost(
            $run,
            (int) ($validated['times'] ?? 1),
            (int) ($validated['alerts_to_spend'] ?? 0),
            $request->user(),
        );

        return back()->with('status', sprintf(
            'Boosted. The card is +%d for the rest of the phase.',
            $activation->boosts,
        ));
    }

    /**
     * Pay a card's Charge cost (rulebook 3.4.2).
     */
    public function charge(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('defend', $run);

        $validated = $request->validate([
            'alerts_to_spend' => ['nullable', 'integer', 'min:0'],
        ]);

        $event = $this->runs->charge(
            $run,
            (int) ($validated['alerts_to_spend'] ?? 0),
            $request->user(),
        );

        return back()->with('status', $event->description);
    }

    /**
     * Throw the dice (rulebook 3.4.2).
     *
     * The skill and the printed strength both come from whoever is running the
     * card, because a challenge is the sentence the card prints rather than a
     * parsed skill and number - "Brute/Hack (2)" lets the Runners choose, and
     * "Hack (4+N) - where N is the number of cards underneath this" is not
     * known until the card is met. The sentence is on screen; the table
     * converts it.
     */
    public function challenge(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('lead', $run);

        $validated = $request->validate([
            'skill' => ['required', Rule::enum(RunnerSkill::class)],
            'printed_strength' => ['required', 'integer', 'min:0', 'max:99'],
            // Control's override on the Alert curve, which runs past where the
            // rulebook prints numbers.
            'alert_strength_override' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $outcome = $this->runs->challenge(
            $run,
            RunnerSkill::from($validated['skill']),
            (int) $validated['printed_strength'],
            $request->user(),
            $validated['alert_strength_override'] ?? null,
        );

        return back()->with('status', $outcome->explain());
    }

    /**
     * Take a consequence (rulebook 3.4.2).
     *
     * The Leader's call, because "the consequence must be taken by a single
     * player, decided by the Run Leader" - so the person taking it is an
     * argument rather than the person clicking.
     */
    public function consequence(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('lead', $run);

        $validated = $request->validate([
            'effect' => ['required', Rule::enum(RunConsequence::class)],
            'character_id' => ['nullable', 'integer'],
            'times' => ['nullable', 'integer', 'min:1', 'max:9'],
        ]);

        $event = $this->runs->applyConsequence(
            $run,
            RunConsequence::from($validated['effect']),
            $this->character($validated['character_id'] ?? null),
            (int) ($validated['times'] ?? 1),
            $request->user(),
        );

        return back()->with('status', $event->description);
    }

    /**
     * Spend Alerts to add a consequence Security's own way (rulebook 3.4.2).
     */
    public function triggerWithAlerts(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('defend', $run);

        $validated = $request->validate([
            'effect' => ['required', Rule::enum(RunConsequence::class)],
            'character_id' => ['nullable', 'integer'],
        ]);

        $event = $this->runs->triggerWithAlerts(
            $run,
            RunConsequence::from($validated['effect']),
            $this->character($validated['character_id'] ?? null),
            $request->user(),
        );

        return back()->with('status', $event->description);
    }

    /**
     * Shrug off an End the Run and pay for it (rulebook 3.4.2).
     */
    public function ignoreEnd(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('lead', $run);

        $validated = $request->validate([
            'character_id' => ['required', 'integer'],
        ]);

        /** @var Character $taker */
        $taker = Character::query()->findOrFail($validated['character_id']);

        $event = $this->runs->ignoreEndTheRun($run, $taker, $request->user());

        return back()->with('status', $event->description);
    }

    /**
     * Walk away at the Breather (rulebook 3.4.2).
     *
     * Each Runner's own decision rather than the Leader's, so this is `act`
     * and not `lead` - and a Leader who leaves may name their successor,
     * because the rulebook wants that chosen democratically where it can be.
     */
    public function leave(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('act', $run);

        $validated = $request->validate([
            'character_id' => ['required', 'integer'],
            'new_leader_character_id' => ['nullable', 'integer'],
        ]);

        /** @var Character $leaving */
        $leaving = Character::query()->findOrFail($validated['character_id']);

        $run = $this->runs->leave(
            $run,
            $leaving,
            $this->character($validated['new_leader_character_id'] ?? null),
            $request->user(),
        );

        return back()->with('status', $run->status->isFinished()
            ? sprintf('%s left, and the run is over.', $leaving->name)
            : sprintf('%s left the run.', $leaving->name));
    }

    /**
     * Leave the Breather: on to the next card, or round again on this one.
     */
    public function advance(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('lead', $run);

        $run = $this->runs->advance($run, $request->user());

        return back()->with('status', $run->status->isFinished()
            ? sprintf('The run is over: %s.', $run->status->label())
            : 'On to the next card.');
    }

    /**
     * A character by id, or null where none was named.
     */
    private function character(int|string|null $id): ?Character
    {
        if ($id === null || $id === '') {
            return null;
        }

        /** @var Character */
        return Character::query()->findOrFail((int) $id);
    }
}
