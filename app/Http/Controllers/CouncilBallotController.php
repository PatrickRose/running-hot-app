<?php

namespace App\Http\Controllers;

use App\Enums\CharacterRole;
use App\Http\Requests\CastBallotRequest;
use App\Models\Character;
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
 * A CEO votes for their Corporation and for no other, so which Corporation a
 * ballot is for is read from the seat they hold rather than taken from the
 * request. Control votes on somebody's behalf through the same route, naming
 * the Corporation, because a CEO at the table and not at a laptop still has to
 * be able to vote.
 */
class CouncilBallotController extends Controller
{
    public function __construct(private readonly CouncilService $council) {}

    public function store(CouncilAgendaItem $item, CastBallotRequest $request): RedirectResponse
    {
        $character = $this->ceoSeatFor($request, $item);

        $this->council->castBallot(
            $item,
            $character->corporation()->firstOrFail(),
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
            $ballot->corporation->name,
        ));
    }

    /**
     * The CEO seat this vote is cast from.
     *
     * Control is the awkward case and the one worth being explicit about: it
     * holds no seat, so it names the Corporation and this finds that
     * Corporation's CEO. Anybody else votes from their own seat, whatever they
     * put in the request.
     */
    private function ceoSeatFor(Request $request, CouncilAgendaItem $item): Character
    {
        $game = $item->session->turn->game;
        $user = $request->user();

        $seats = $game->characters()
            ->where('role', CharacterRole::Ceo)
            ->whereNotNull('corporation_id');

        $own = (clone $seats)->where('user_id', $user?->id)->first();

        if ($own !== null) {
            return $own;
        }

        $corporationId = $request->integer('corporation_id');

        $onBehalf = $corporationId === 0
            ? null
            : (clone $seats)->where('corporation_id', $corporationId)->first();

        if ($onBehalf === null) {
            throw ValidationException::withMessages([
                'corporation_id' => 'Name the Corporation this vote is for.',
            ]);
        }

        return $onBehalf;
    }
}
