<?php

namespace App\Http\Controllers;

use App\Enums\CharacterRole;
use App\Http\Requests\CastBallotRequest;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\CouncilAgendaItem;
use App\Models\CouncilBallot;
use App\Services\CouncilService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Votes, as handed to the Chair (rulebook 3.1.2).
 *
 * A CEO votes for their Corporation and for no other, so whose vote a ballot is
 * for is read from the seat they hold rather than taken from the request - as
 * is HM Government's, which is a seat of its own rather than a Corporation's.
 * Control votes on somebody's behalf through the same route, naming the
 * Corporation, because a CEO at the table and not at a laptop still has to be
 * able to vote.
 */
class CouncilBallotController extends Controller
{
    public function __construct(private readonly CouncilService $council) {}

    public function store(CouncilAgendaItem $item, CastBallotRequest $request): RedirectResponse
    {
        [$voter, $character] = $this->voterFor($request, $item);

        $this->council->castBallot(
            $item,
            $voter,
            $request->allocations(),
            $character,
            $request->user(),
        );

        return back()->with('status', 'Your vote is with the Chair.');
    }

    /**
     * The Chair hands a ballot back, which is what has to happen to every vote
     * already in when a vote is declared secret - and the only way a CEO gets
     * to change one.
     */
    public function destroy(Request $request, CouncilBallot $ballot): RedirectResponse
    {
        Gate::authorize('chair', $ballot->item->session);

        $this->council->returnBallot(
            $ballot,
            $request->string('reason')->toString() ?: 'The Chair handed the vote back.',
        );

        return back()->with('status', sprintf(
            '%s\'s vote was handed back.',
            $ballot->voterName(),
        ));
    }

    /**
     * Whose vote this is, and whose hand is carrying it.
     *
     * Two kinds of seat answer to this. A CEO votes for their Corporation. A
     * character Control has seated in their own right - HM Government - votes
     * for themselves, and is then both the voter and the hand.
     *
     * Control is the awkward case and the one worth being explicit about: it
     * holds no seat at all, so it names the Corporation it is voting for and
     * this finds that Corporation's CEO to carry it. Anybody else votes from
     * their own seat whatever they put in the request.
     *
     * @return array{0: Corporation|Character, 1: Character|null}
     */
    private function voterFor(Request $request, CouncilAgendaItem $item): array
    {
        $game = $item->session->turn->game;
        $user = $request->user();

        $mine = $game->characters()->where('user_id', $user?->id)->get();

        $ceo = $mine->first(fn (Character $character): bool => $character->role === CharacterRole::Ceo
            && $character->corporation_id !== null);

        if ($ceo !== null) {
            return [$ceo->corporation()->firstOrFail(), $ceo];
        }

        $seated = $mine->first(fn (Character $character): bool => $character->sitsOnCouncil());

        if ($seated !== null) {
            return [$seated, $seated];
        }

        $corporationId = $request->integer('corporation_id');

        /** @var Corporation|null $corporation */
        $corporation = $corporationId === 0
            ? null
            : $game->corporations()->find($corporationId);

        if ($corporation === null) {
            throw ValidationException::withMessages([
                'corporation_id' => 'Name the Corporation this vote is for.',
            ]);
        }

        $chair = $game->characters()
            ->where('role', CharacterRole::Ceo)
            ->where('corporation_id', $corporation->id)
            ->first();

        return [$corporation, $chair];
    }
}
