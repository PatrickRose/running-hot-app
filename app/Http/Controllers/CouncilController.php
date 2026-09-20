<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Services\CouncilService;
use App\Support\CouncilPresenter;
use App\Support\GamePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Council Chamber (rulebook 3.1).
 *
 * A seat is what it takes to open it. This used to be everyone's page, on the
 * reading that the agenda is read out and 3.1.3 hands blank cards to "players"
 * rather than to "CEOs" - so a Runner could read the Chamber and write a custom
 * agenda. It is the designer's ruling that they cannot: somebody with no seat
 * who wants an agenda raised has to convince somebody who has one.
 *
 * What a seated person may *do* narrows from there - vote with their
 * Corporation's Political Will or with the bloc Control wrote on them, chair if
 * their Corporation holds the Chair this turn - and what they may *see* of a
 * vote is CouncilPresenter's business. Control reaches all of it, as ever.
 */
class CouncilController extends Controller
{
    public function __invoke(
        Request $request,
        GamePresenter $games,
        CouncilPresenter $council,
        CouncilService $seats,
    ): Response {
        $game = Game::query()
            ->where('status', GameStatus::Running)
            ->latest('id')
            ->first();

        $user = $request->user();

        abort_unless(
            $game === null
                || $user?->isControlFor($game)
                || $seats->hasSeat($game, $user),
            403,
            'Only the Council may read the Chamber.',
        );

        return Inertia::render('council', [
            'game' => $game === null ? null : $games->summary($game),
            'council' => $game === null ? null : $council->forPlayer($game, $request->user()),
        ]);
    }
}
