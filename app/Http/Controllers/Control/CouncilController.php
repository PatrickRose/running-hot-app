<?php

namespace App\Http\Controllers\Control;

use App\Enums\CouncilAttendance;
use App\Enums\PhaseType;
use App\Http\Controllers\Controller;
use App\Models\AgendaCard;
use App\Models\AgendaResolution;
use App\Models\Character;
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
 * Control - the deck and picking from it, the remarks on a custom agenda, the sign-off
 * on an amendment, and the cost of an empty seat - plus the overrides Control
 * has over everything else in this application.
 */
class CouncilController extends Controller
{
    /**
     * The two kinds of thing that can hold the Chair, as the morph map names
     * them: a Corporation, or a character Control has seated in its own right.
     *
     * @var list<string>
     */
    private const SEAT_KINDS = ['corporation', 'character'];

    public function __construct(private readonly CouncilService $council) {}

    public function index(Game $game, GamePresenter $games, CouncilPresenter $council, Request $request): Response
    {
        return Inertia::render('control/games/council', [
            'game' => $games->controlSummary($game),
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
     * Control picks the cards the Council is asked about (3.1.1).
     *
     * The whole set every time rather than one at a time, so unpicking a card
     * before the Chair has looked is the same act as picking one - and the
     * service is where "the Chair has already chosen" is decided.
     */
    public function hand(Game $game, Request $request): RedirectResponse
    {
        $session = $this->session($game);

        $validated = $request->validate([
            'cards' => ['required', 'array', 'min:1'],
            'cards.*' => [
                'required', 'integer',
                Rule::exists('agenda_cards', 'id')->where('game_id', $game->id),
            ],
        ]);

        $handed = $this->council->handToChair($session, $validated['cards']);

        return back()->with('status', sprintf(
            '%s handed to the Chair.',
            $handed->pluck('title')->join(', ', ' and '),
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
     * Hand the Chair to somebody else for this turn only.
     *
     * Either kind of seat: a Corporation, whose CEO speaks for it, or a seat
     * Control has given somebody in their own right. Naming nothing vacates it.
     */
    public function chair(Game $game, Request $request): RedirectResponse
    {
        $session = $this->session($game);

        $validated = $request->validate([
            'chair_type' => ['nullable', 'string', Rule::in(self::SEAT_KINDS)],
            'chair_id' => ['nullable', 'integer', 'required_with:chair_type'],
        ]);

        $chair = $this->namedSeat($game, $validated['chair_type'] ?? null, $validated['chair_id'] ?? null);

        $this->council->setChair($session, $chair);

        return back()->with('status', $chair === null
            ? 'The Chair is vacant.'
            : $chair->name.' takes the Chair.');
    }

    /**
     * Set the order the Chair rotates in, which Council Control announces on
     * the day (3.1.1). Held rather than derived, so this is the only thing that
     * decides whose turn it is next.
     *
     * The whole order arrives rather than a move, because that is what a list
     * being rearranged is - and it is what lets a seat leave the rotation by
     * being left out of it.
     */
    public function rotation(Game $game, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*.type' => ['required', 'string', Rule::in(self::SEAT_KINDS)],
            'order.*.id' => ['required', 'integer'],
        ]);

        /** @var array<int, array{type: string, id: int}> $rows */
        $rows = $validated['order'];

        $this->council->setRotation($game, array_map(
            fn (array $row): Corporation|Character => $this->namedSeat($game, $row['type'], $row['id'])
                ?? throw ValidationException::withMessages([
                    'order' => 'That seat is not in this game.',
                ]),
            $rows,
        ));

        return back()->with('status', 'The Chair rotation is set.');
    }

    /**
     * One seat named by its morph, scoped to this game so nothing outside it
     * can be put in the Chair or the rotation.
     */
    private function namedSeat(Game $game, ?string $type, ?int $id): Corporation|Character|null
    {
        if ($type === null || $id === null) {
            return null;
        }

        /** @var Corporation|Character */
        return $type === 'character'
            ? $game->characters()->findOrFail($id)
            : $game->corporations()->findOrFail($id);
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
     * Seat somebody at the Council who is not a Corporation, or take the seat
     * away again.
     *
     * Control's ruling rather than a rule - 3.1 seats only the CEOs - so this
     * is Control's to set and there is nothing to derive. The refusals, a CEO
     * and a seat worth nothing, are the service's.
     */
    public function seat(Game $game, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'character_id' => [
                'required', 'integer',
                Rule::exists('characters', 'id')->where('game_id', $game->id),
            ],
            // Null takes the seat away, which is why this is nullable rather
            // than bounded at zero: a seat worth no votes is not a seat.
            'votes' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        /** @var Character $character */
        $character = Character::query()->findOrFail($validated['character_id']);

        $votes = $validated['votes'] ?? null;

        $this->council->seat($character, $votes);

        return back()->with('status', $votes === null
            ? sprintf('%s no longer has a seat at the Council.', $character->name)
            : sprintf('%s sits at the Council with %d vote(s).', $character->name, $votes));
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
