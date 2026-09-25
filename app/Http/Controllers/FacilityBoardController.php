<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Facility list as players see it.
 *
 * Every player needs this: a Runner to choose a target (rulebook 3.4.1), and a
 * Corporate player to see what they are defending. Until now the only Facility
 * view was Control's, so a Security player could not read their own stacks.
 *
 * Read-only itself: the writes are Security's own routes for the stacks and
 * the CEO's for a requisition, and this page is where both are drawn.
 */
class FacilityBoardController extends Controller
{
    public function __invoke(Request $request, GamePresenter $presenter): Response
    {
        $game = Game::current();

        return Inertia::render('facilities', [
            'game' => $game === null ? null : $presenter->summary($game),
            'board' => $game === null ? null : $presenter->facilityBoard($game, $request->user()),
        ]);
    }
}
