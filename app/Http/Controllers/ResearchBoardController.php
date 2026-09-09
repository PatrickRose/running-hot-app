<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Support\GamePresenter;
use App\Support\ResearchPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The research sub-game as players see it (rulebook 3.2).
 *
 * One page for both halves of it, because they are one job: the Research player
 * earns points at the table during the Action phase and spends them on the tree
 * during Setup, and what they can spend depends on what they just earned.
 *
 * Two tiers, as on the Facility board. The table itself - who is sitting at it,
 * in what order, whose turn it is, and the six public cards - is everybody's.
 * A hand, a deck, a pile of Research Points and a tech tree belong to one
 * Corporation, and 3.2.5 makes the size of that pile semi-secret.
 */
class ResearchBoardController extends Controller
{
    public function __invoke(
        Request $request,
        GamePresenter $games,
        ResearchPresenter $research,
    ): Response {
        $game = Game::current();

        return Inertia::render('research', [
            'game' => $game === null ? null : $games->summary($game),
            'research' => $game === null ? null : $research->board($game, $request->user()),
        ]);
    }
}
