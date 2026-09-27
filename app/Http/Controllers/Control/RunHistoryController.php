<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Support\GamePresenter;
use App\Support\RunPresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every run in the game that has ended, turn by turn, for Control to read.
 *
 * Read-only on purpose. The run screen is where a run is worked and it only
 * ever shows this turn; this is where Control answers who hit what, with whom,
 * and whether they got in, once the turn has moved on. Anything a ruling needs
 * to change afterwards goes through the tracker controls like any other.
 */
class RunHistoryController extends Controller
{
    public function index(Game $game, GamePresenter $games, RunPresenter $runs): Response
    {
        return Inertia::render('control/games/runs', [
            'game' => $games->controlSummary($game),
            'turns' => $runs->history($game),
        ]);
    }
}
