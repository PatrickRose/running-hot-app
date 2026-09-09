<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Support\GamePresenter;
use App\Support\RunPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Facility game, from whichever side of it this player is on.
 *
 * One page rather than two, because plenty of people are on both sides of it at
 * once - a Security player whose Corporation is being hit is also watching the
 * queue, and Control is watching everything. The presenter decides which of
 * them each viewer gets.
 */
class RunBoardController extends Controller
{
    public function __invoke(Request $request, GamePresenter $game, RunPresenter $runs): Response
    {
        $current = Game::query()
            ->where('status', GameStatus::Running)
            ->latest('id')
            ->first();

        return Inertia::render('runs', [
            'game' => $current === null ? null : $game->summary($current),
            'board' => $current === null ? null : $runs->forPlayer($current, $request->user()),
        ]);
    }
}
