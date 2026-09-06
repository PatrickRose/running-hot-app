<?php

namespace App\Http\Controllers\Control;

use App\Enums\CouncilAttendance;
use App\Enums\PhaseType;
use App\Http\Controllers\Controller;
use App\Models\AgendaCard;
use App\Models\AgendaResolution;
use App\Models\Corporation;
use App\Models\CouncilSession;
use App\Models\Game;
use App\Services\CouncilService;
use App\Support\CouncilPresenter;
use App\Support\GamePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Control's side of the Council (rulebook 3.1).
 *
 * Deliberately the smaller half. The Council is the CEOs' sub-game and the
 * Chair runs it, so what is here is the four things the rulebook actually gives
 * Control - the deck and the draw, the remarks on a custom agenda, the sign-off
 * on an amendment, and the cost of an empty seat - plus the overrides Control
 * has over everything else in this application.
 */
class CouncilController extends Controller
{
    public function __construct(private readonly CouncilService $council) {}

    public function index(Game $game, GamePresenter $games, CouncilPresenter $council, Request $request): Response
    {
        return Inertia::render('control/games/council', [
            'game' => $games->summary($game),
            'council' => $council->forPlayer($game, $request->user()),
            'control' => $council->forControl($game),
        ]);
    }

    /**
     * Write a card into the game's agenda deck.
     *
     * A new game already holds the game's own deck, seeded from
     * App\Support\AgendaCardBlueprint, so this is Control adding to it rather
     * than filling it - the same way it adds an Equipment card that a
     * technology has just invented.
     */
    public function storeCard(Game $game, Request $request): RedirectResponse
    {
        $validated = $this->validateCard($request);

        $card = $this->council->createDeckCard(
            $game,
            $validated['title'],
            $validated['body'] ?? null,
            $validated['resolutions'],
        );

        return back()->with('status', $card->title.' is in the deck.');
    }

    public function updateCard(Game $game, AgendaCard $card, Request $request): RedirectResponse
    {
        $this->assertBelongs($game, $card);

        $validated = $this->validateCard($request);

        // Control rewrites a card outright rather than amending it, which is
        // the deck's own editor and nothing to do with 3.1.4. A card that has
        // been voted on is refused by the service: the ballots point at its
        // resolutions.
        $this->council->rewriteCard($card, $validated['title'], $validated['body'] ?? null, $validated['resolutions']);

        return back()->with('status', $card->title.' rewritten.');
    }

    public function destroyCard(Game $game, AgendaCard $card): RedirectResponse
    {
        $this->assertBelongs($game, $card);

        $title = $card->title;
        $card->delete();

        return back()->with('status', $title.' taken out of the deck.');
    }

    /**
     * Control draws three and hands them to the Chair (3.1.1).
     */
    public function draw(Game $game, Request $request): RedirectResponse
    {
        $session = $this->session($game);

        $drawn = $this->council->draw($session);

        return back()->with('status', sprintf(
            '%d card(s) drawn for the Chair.',
            $drawn->count(),
        ));
    }

    /**
     * Control's remarks on a custom agenda, and the card back to its author.
     */
    public function annotate(Game $game, AgendaCard $card, Request $request): RedirectResponse
    {
        $this->assertBelongs($game, $card);

        $validated = $request->validate([
            'control_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->council->annotate($card, $validated['control_note'] ?? null);

        return back()->with('status', $card->title.' goes back to its author.');
    }

    /**
     * Sign an amendment off, or refuse it (3.1.4).
     */
    public function amendment(Game $game, AgendaResolution $resolution, Request $request): RedirectResponse
    {
        if ($resolution->card->game_id !== $game->id) {
            abort(404);
        }

        $approved = $request->boolean('approve');

        if ($approved) {
            $this->council->signOffAmendment($resolution);

            return back()->with('status', 'The amendment is signed off.');
        }

        $this->council->rejectAmendment($resolution);

        return back()->with('status', 'The amendment is refused, and the card stands.');
    }

    /**
     * Hand the Chair to a different Corporation for this turn only.
     */
    public function chair(Game $game, Request $request): RedirectResponse
    {
        $session = $this->session($game);

        $validated = $request->validate([
            'corporation_id' => [
                'nullable', 'integer',
                Rule::exists('corporations', 'id')->where('game_id', $game->id),
            ],
        ]);

        $corporation = null;

        if (($validated['corporation_id'] ?? null) !== null) {
            /** @var Corporation $corporation */
            $corporation = Corporation::query()->findOrFail($validated['corporation_id']);
        }

        $this->council->setChair($session, $corporation);

        return back()->with('status', $corporation === null
            ? 'The Chair is vacant.'
            : $corporation->name.' takes the Chair.');
    }

    /**
     * Set the order the Chair rotates in, which Council Control announces on
     * the day (3.1.1). Held rather than derived, so this is the only thing that
     * decides whose turn it is next.
     */
    public function rotation(Game $game, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => [
                'required', 'integer',
                Rule::exists('corporations', 'id')->where('game_id', $game->id),
            ],
        ]);

        foreach (array_values($validated['order']) as $index => $corporationId) {
            Corporation::query()
                ->where('game_id', $game->id)
                ->where('id', $corporationId)
                ->update(['council_chair_order' => $index + 1]);
        }

        return back()->with('status', 'The Chair rotation is set.');
    }

    /**
     * Move the recess, the second clock inside the Setup phase (3.1.1).
     */
    public function recess(Game $game, Request $request): RedirectResponse
    {
        $session = $this->session($game);

        $validated = $request->validate([
            'seconds' => ['required', 'integer', 'min:-3600', 'max:3600'],
        ]);

        $this->council->shiftRecess($session, (int) $validated['seconds']);

        return back()->with('status', sprintf(
            'The Council sits %d second(s) %s.',
            abs((int) $validated['seconds']),
            $validated['seconds'] >= 0 ? 'longer' : 'less',
        ));
    }

    /**
     * Mark a seat, and charge for an empty one (3.1.2).
     */
    public function attendance(Game $game, Request $request): RedirectResponse
    {
        $session = $this->session($game);

        $validated = $request->validate([
            'corporation_id' => [
                'required', 'integer',
                Rule::exists('corporations', 'id')->where('game_id', $game->id),
            ],
            'phase' => ['required', Rule::in([PhaseType::Setup->value, PhaseType::Action->value])],
            'attendance' => ['required', Rule::enum(CouncilAttendance::class)],
        ]);

        /** @var Corporation $corporation */
        $corporation = Corporation::query()->findOrFail($validated['corporation_id']);

        $this->council->markAttendance(
            $session,
            $corporation,
            PhaseType::from($validated['phase']),
            CouncilAttendance::from($validated['attendance']),
        );

        return back()->with('status', sprintf(
            '%s marked %s for the %s phase.',
            $corporation->name,
            CouncilAttendance::from($validated['attendance'])->label(),
            PhaseType::from($validated['phase'])->label(),
        ));
    }

    /**
     * The Political Will an absence costs.
     *
     * Control's figure, not the application's: the rulebook says an absence has
     * a negative impact on Political Will and names no number, so nothing here
     * derives one. It goes through TrackerService like every other movement, so
     * the CEO can be shown exactly why they are down.
     */
    public function penalty(Game $game, Request $request): RedirectResponse
    {
        $session = $this->session($game);

        $validated = $request->validate([
            'corporation_id' => [
                'required', 'integer',
                Rule::exists('corporations', 'id')->where('game_id', $game->id),
            ],
            'phase' => ['required', Rule::in([PhaseType::Setup->value, PhaseType::Action->value])],
            'amount' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        /** @var Corporation $corporation */
        $corporation = Corporation::query()->findOrFail($validated['corporation_id']);

        $this->council->applyAttendancePenalty(
            $session,
            $corporation,
            PhaseType::from($validated['phase']),
            (int) $validated['amount'],
            $request->user(),
        );

        return back()->with('status', sprintf(
            '%s loses %d Political Will for the empty seat.',
            $corporation->name,
            $validated['amount'],
        ));
    }

    /**
     * This turn's sitting, opened if the game has somehow reached the Council
     * without one - a game started before this feature existed, most likely.
     */
    private function session(Game $game): CouncilSession
    {
        $turn = $game->currentTurn();

        if ($turn === null) {
            throw ValidationException::withMessages([
                'council' => 'The game has not started, so the Council is not sitting.',
            ]);
        }

        return $this->council->openSession($turn);
    }

    private function assertBelongs(Game $game, AgendaCard $card): void
    {
        abort_if($card->game_id !== $game->id, 404);
    }

    /**
     * @return array{title: string, body?: string|null, resolutions: array<int, string>}
     */
    private function validateCard(Request $request): array
    {
        /** @var array{title: string, body?: string|null, resolutions: array<int, string>} $validated */
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
            'resolutions' => [
                'required', 'array',
                'min:'.AgendaCard::MINIMUM_RESOLUTIONS,
                'max:'.AgendaCard::MAXIMUM_RESOLUTIONS,
            ],
            'resolutions.*' => ['required', 'string', 'max:500'],
        ]);

        return $validated;
    }
}
