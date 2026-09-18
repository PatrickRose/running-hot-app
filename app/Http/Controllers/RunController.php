<?php

namespace App\Http\Controllers;

use App\Enums\RunAccessKind;
use App\Enums\RunConsequence;
use App\Enums\RunnerSkill;
use App\Enums\TechnologyAccessAction;
use App\Http\Requests\SubmitRunRequest;
use App\Models\Character;
use App\Models\Facility;
use App\Models\Game;
use App\Models\Run;
use App\Models\RunAccess;
use App\Models\RunEvent;
use App\Models\TechnologyHolding;
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
     * Security names the strength and rolls the card's defence (rulebook 3.4.2).
     *
     * The printed strength comes from Security because Security is holding the
     * card: a challenge is the sentence it prints rather than a parsed skill
     * and number, and "Hack (4+N) - where N is the number of cards underneath
     * this" is not known until the card is met. The sentence is on screen
     * beside the box; the table converts it.
     */
    public function defend(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('defend', $run);

        $validated = $request->validate([
            'printed_strength' => ['required', 'integer', 'min:0', 'max:99'],
            // Control's override on the Alert curve.
            'alert_strength_override' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $event = $this->runs->defend(
            $run,
            (int) $validated['printed_strength'],
            $request->user(),
            $validated['alert_strength_override'] ?? null,
        );

        return back()->with('status', $event->description);
    }

    /**
     * The Runners throw their dice (rulebook 3.4.2).
     *
     * Only the skill, because Security has already named the strength and
     * rolled against it. "Brute/Hack (2)" is the one thing here that is the
     * Runners' choice, which is why it is the one thing they are asked for.
     */
    public function challenge(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('lead', $run);

        $validated = $request->validate([
            'skill' => ['required', Rule::enum(RunnerSkill::class)],
        ]);

        $outcome = $this->runs->challenge(
            $run,
            RunnerSkill::from($validated['skill']),
            $request->user(),
        );

        return back()->with('status', $outcome->explain());
    }

    /**
     * Take a consequence (rulebook 3.4.2).
     *
     * The Leader's call, because "the consequence must be taken by a single
     * player, decided by the Run Leader" - so the person taking it is an
     * argument rather than the person clicking.
     *
     * **Several at once, because a card prints several.** "1 alert, 1 wound" is
     * one consequence with two parts, and applying them a request at a time did
     * not work: the first consequence event moves the derived cursor to the
     * Breather, so the Leader was offered exactly one part of the card and the
     * rest of the sentence quietly went unpaid. They are applied in the order
     * given, inside one transaction, and each lands in the log as its own line -
     * the ledger should read like the card, not like a single lump.
     *
     * A part that ends the run stops the rest: there is nobody left to take a
     * Wound, and {@see RunEngine::applyConsequence()} would refuse it anyway.
     */
    public function consequence(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('lead', $run);

        $validated = $request->validate([
            // One effect, or a list of them. The single form is what a button
            // that means one thing posts - End the Run has no count and no
            // companion - and the list is what the card's own sentence needs.
            'effect' => ['required_without:effects', Rule::enum(RunConsequence::class)],
            'times' => ['nullable', 'integer', 'min:1', 'max:9'],
            'effects' => ['required_without:effect', 'array', 'min:1', 'max:5'],
            'effects.*.effect' => ['required', Rule::enum(RunConsequence::class)],
            'effects.*.times' => ['nullable', 'integer', 'min:1', 'max:9'],
            'character_id' => ['nullable', 'integer'],
        ]);

        $parts = $validated['effects'] ?? [[
            'effect' => $validated['effect'],
            'times' => $validated['times'] ?? 1,
        ]];

        $taker = $this->character($validated['character_id'] ?? null);
        $descriptions = [];

        foreach ($parts as $part) {
            if ($run->refresh()->status->isFinished()) {
                break;
            }

            $descriptions[] = $this->runs->applyConsequence(
                $run,
                RunConsequence::from($part['effect']),
                $taker,
                (int) ($part['times'] ?? 1),
                $request->user(),
            )->description;
        }

        return back()->with('status', implode(' ', $descriptions));
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
     * Spend an access inside a Facility the Runners broke into (3.4.3).
     *
     * One route for all four kinds, because they are one act with one choice -
     * the Runner picks what to spend their access on, and every one of them
     * costs the same single access. A card access only draws the card here;
     * what to do with it is decided once it is face up, on the route below.
     *
     * A plot access asks for no reason. A Runner tells Control what they are
     * chasing in the channel they are already standing in, and a text box that
     * has to be filled in before the button works is a worse version of a
     * conversation. Authorised as `act` and checked against the
     * character named: a Runner spends their own, not their Leader's, and not
     * for somebody who has wandered off.
     *
     * Control reaches it through the policy's before(), which is what "Control
     * shouldn't need to be involved" leaves room for: they are not in the way,
     * and they can still act for a player who is not at their laptop.
     */
    public function access(Run $run, Request $request): RedirectResponse
    {
        Gate::authorize('act', $run);

        $validated = $request->validate([
            'character_id' => ['required', 'integer'],
            'kind' => ['required', Rule::enum(RunAccessKind::class)],
            // A card access may name a technology this run has already turned
            // over. Absent means draw blind from the ones nobody has seen,
            // which is the only way to reach an unknown card.
            'technology_holding_id' => ['nullable', 'integer'],
        ]);

        /** @var Character $runner */
        $runner = Character::query()->findOrFail($validated['character_id']);

        $this->authoriseAccessFor($request, $run, $runner);

        $access = match (RunAccessKind::from($validated['kind'])) {
            RunAccessKind::Credits => $this->runs->takeCredits($run, $runner, $request->user()),
            RunAccessKind::FacilityEffect => $this->runs->takeFacilityEffect($run, $runner, $request->user()),
            RunAccessKind::Plot => $this->runs->takePlotAccess(
                $run,
                $runner,
                null,
                $request->user(),
            ),
            RunAccessKind::Technology => $this->runs->accessTechnology(
                $run,
                $runner,
                $this->holding($validated['technology_holding_id'] ?? null),
                $request->user(),
            ),
        };

        return back()->with(
            'status',
            $run->refresh()->events()->where('type', RunEvent::TYPE_ACCESS)->latest('id')->value('description')
                ?? sprintf('%s spent an access.', $runner->name),
        );
    }

    /**
     * Copy, steal, destroy or leave the card an access turned up (3.4.3).
     *
     * Its own act because the rulebook makes it one: the card is revealed and
     * *then* the choice is made. Authorised exactly as taking the access was -
     * the Runner whose access it is, or Control - so a gangmate cannot decide
     * what happens to somebody else's card.
     */
    public function resolveAccess(Run $run, RunAccess $access, Request $request): RedirectResponse
    {
        Gate::authorize('act', $run);

        abort_if($access->run_id !== $run->id, 404);

        $validated = $request->validate([
            // Absent is "leave it", which is a real answer rather than a
            // missing one - so this is nullable rather than required.
            'action' => ['nullable', Rule::enum(TechnologyAccessAction::class)],
        ]);

        $this->authoriseAccessFor($request, $run, $access->character);

        $this->runs->resolveAccess(
            $access,
            isset($validated['action']) ? TechnologyAccessAction::from((string) $validated['action']) : null,
            $request->user(),
        );

        return back()->with(
            'status',
            $run->refresh()->events()->where('type', RunEvent::TYPE_ACCESS)->latest('id')->value('description')
                ?? 'Access resolved.',
        );
    }

    /**
     * The technology an access named, if it named one.
     *
     * Whether it is a card this run may actually reach for is the engine's
     * question, not this one's - all that happens here is turning an id into
     * a row.
     */
    private function holding(mixed $id): ?TechnologyHolding
    {
        if ($id === null) {
            return null;
        }

        /** @var TechnologyHolding */
        return TechnologyHolding::query()->findOrFail($id);
    }

    /**
     * A player spends their own access; Control spends anybody's.
     *
     * `act` already asks whether the caller is on the run, but every Runner on
     * a run passes that - so without this a Runner could spend a gangmate's
     * access out from under them, which is the one thing per-Runner accesses
     * are for.
     */
    private function authoriseAccessFor(Request $request, Run $run, Character $runner): void
    {
        $user = $request->user();

        if ($user !== null && $user->isControlFor($run->game)) {
            return;
        }

        if ($runner->user_id !== $user?->id) {
            abort(403, 'That is somebody else\'s access to spend.');
        }
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
