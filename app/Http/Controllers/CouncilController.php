<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Support\CouncilPresenter;
use App\Support\GamePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Council Chamber (rulebook 3.1).
 *
 * Everyone playing may open it, because the agenda is read out to the Council
 * and custom agenda cards are written by players rather than by CEOs. What a
 * given person may *do* here narrows from there - vote if they hold a CEO seat,
 * chair if their Corporation holds the Chair this turn - and what they may see
 * of a vote is CouncilPresenter's business.
 */
class CouncilController extends Controller
{
    public function __invoke(Request $request, GamePresenter $games, CouncilPresenter $council): Response
    {
        $game = Game::query()
            ->where('status', GameStatus::Running)
            ->latest('id')
            ->first();

        return Inertia::render('council', [
            'game' => $game === null ? null : $games->summary($game),
            'council' => $game === null ? null : $council->forPlayer($game, $request->user()),
        ]);
    }
}
