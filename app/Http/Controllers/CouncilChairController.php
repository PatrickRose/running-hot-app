<?php

namespace App\Http\Controllers;

use App\Enums\CharacterRole;
use App\Enums\ResolutionAmendment;
use App\Models\AgendaCard;
use App\Models\AgendaResolution;
use App\Models\Character;
use App\Models\CouncilAgendaItem;
use App\Models\CouncilSession;
use App\Services\CouncilService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Everything the Chair does with the agenda (rulebook 3.1.1, 3.1.3 and 3.1.4).
 *
 * Thin, like the Facility board's controller and for the same reason: the rules
 * live in CouncilService, so a route a player can reach cannot grow its own
 * copy of the five-item cap or of the bounds on a card's resolutions. What is
 * here is who is asking, and that is the CouncilSessionPolicy's answer.
 */
class CouncilChairController extends Controller
{
    public function __construct(private readonly CouncilService $council) {}

    /**
     * Keep two of what Control handed over, and discard the rest (3.1.1).
     */
    public function keep(Request $request, CouncilSession $session): RedirectResponse
    {
        Gate::authorize('chair', $session);

        $validated = $request->validate([
            'kept' => ['required', 'array', 'min:1', 'max:'.CouncilSession::CARDS_KEPT],
            'kept.*' => ['required', 'integer'],
        ]);

        $kept = $this->council->keep($session, array_map('intval', $validated['kept']));

        return back()->with('status', sprintf(
            'The Council will vote on %s.',
            $kept->pluck('title')->join(' and '),
        ));
    }

    /**
     * Promote one previously submitted item onto this turn's agenda (3.1.3).
     */
    public function promote(Request $request, CouncilSession $session): RedirectResponse
    {
        Gate::authorize('chair', $session);

        $card = $this->cardIn($session, $request);

        $this->council->promote($session, $card);

        return back()->with('status', $card->title.' is up for vote this turn.');
    }

    /**
     * The Chair's three answers to a custom agenda (3.1.3).
     */
    public function rule(Request $request, CouncilSession $session): RedirectResponse
    {
        Gate::authorize('chair', $session);

        $card = $this->cardIn($session, $request);

        $ruling = $request->validate([
            'ruling' => ['required', Rule::in(['urgent', 'important', 'reject'])],
        ])['ruling'];

        $status = match ($ruling) {
            'urgent' => 'is urgent, and is up for vote this turn.',
            'important' => 'is held as important for a future turn.',
            default => 'goes back to its author.',
        };

        match ($ruling) {
            'urgent' => $this->council->acceptUrgent($session, $card),
            'important' => $this->council->acceptImportant($card),
            default => $this->council->reject($card),
        };

        return back()->with('status', $card->title.' '.$status);
    }

    /**
     * Declare the vote secret, or public again (3.1.2).
     */
    public function secret(Request $request, CouncilAgendaItem $item): RedirectResponse
    {
        Gate::authorize('chair', $item->session);

        $secret = $request->boolean('secret');

        $returned = $this->council->declareSecret($item, $secret);

        return back()->with('status', match (true) {
            ! $secret => 'The breakdown of this vote will be read out.',
            $returned > 0 => sprintf(
                'This vote is secret. %d vote(s) already in were handed back.',
                $returned,
            ),
            default => 'This vote is secret. The Chair still sees the breakdown.',
        });
    }

    /**
     * Resolve the vote, breaking a tie if there is one (3.1.2).
     */
    public function resolve(Request $request, CouncilAgendaItem $item): RedirectResponse
    {
        Gate::authorize('chair', $item->session);

        $request->validate([
            'resolution_id' => [
                'nullable', 'integer',
                Rule::exists('agenda_resolutions', 'id')->where('agenda_card_id', $item->agenda_card_id),
            ],
        ]);

        $choice = $request->filled('resolution_id')
            ? AgendaResolution::query()->findOrFail($request->integer('resolution_id'))
            : null;

        $resolved = $this->council->resolve($item, $choice, $request->user());

        return back()->with('status', sprintf(
            '%s carried%s.',
            $resolved->outcome->text,
            $resolved->tie_broken ? ', on the Chair\'s casting decision' : '',
        ));
    }

    /**
     * Propose an amendment to a card's resolutions (3.1.4).
     *
     * Proposed only: nothing changes on the card until Council Control signs it
     * off, which is what the rulebook requires and what stops a CEO voting on
     * words that moved underneath them.
     */
    public function amend(Request $request, CouncilSession $session, AgendaCard $card): RedirectResponse
    {
        Gate::authorize('chair', $session);

        if ($card->game_id !== $session->turn->game_id) {
            throw ValidationException::withMessages(['agenda_card_id' => 'That card belongs to a different game.']);
        }

        $validated = $request->validate([
            'amendment' => ['required', Rule::enum(ResolutionAmendment::class)],
            'text' => ['nullable', 'string', 'max:500'],
            'resolution_id' => [
                'nullable', 'integer',
                Rule::exists('agenda_resolutions', 'id')->where('agenda_card_id', $card->id),
            ],
        ]);

        $amendment = ResolutionAmendment::from($validated['amendment']);
        $chair = $this->chairSeat($request, $session);

        if ($amendment === ResolutionAmendment::Addition) {
            if (($validated['text'] ?? '') === '') {
                throw ValidationException::withMessages(['text' => 'A new resolution needs some words.']);
            }

            $this->council->proposeAddition($card, $validated['text'], $chair);
        } else {
            if (($validated['resolution_id'] ?? null) === null) {
                throw ValidationException::withMessages(['resolution_id' => 'Name the resolution to amend.']);
            }

            /** @var AgendaResolution $resolution */
            $resolution = AgendaResolution::query()->findOrFail($validated['resolution_id']);

            if ($amendment === ResolutionAmendment::Removal) {
                $this->council->proposeRemoval($resolution, $chair);
            } else {
                if (($validated['text'] ?? '') === '') {
                    throw ValidationException::withMessages(['text' => 'A rewording needs some words.']);
                }

                $this->council->proposeRewording($resolution, $validated['text'], $chair);
            }
        }

        return back()->with('status', 'The amendment is with Council Control.');
    }

    private function cardIn(CouncilSession $session, Request $request): AgendaCard
    {
        $request->validate([
            'agenda_card_id' => [
                'required', 'integer',
                Rule::exists('agenda_cards', 'id')->where('game_id', $session->turn->game_id),
            ],
        ]);

        /** @var AgendaCard */
        return AgendaCard::query()->findOrFail($request->integer('agenda_card_id'));
    }

    /**
     * The CEO who is chairing, so an amendment records who asked for it.
     *
     * Null when Control is acting, which is the honest answer: Control is not
     * sitting in the Chair's seat, it is standing behind it.
     */
    private function chairSeat(Request $request, CouncilSession $session): ?Character
    {
        if ($session->chair_corporation_id === null) {
            return null;
        }

        return $session->turn->game->characters()
            ->where('user_id', $request->user()?->id)
            ->where('corporation_id', $session->chair_corporation_id)
            ->where('role', CharacterRole::Ceo)
            ->first();
    }
}
