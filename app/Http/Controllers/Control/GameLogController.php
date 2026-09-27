<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Support\GameLogPresenter;
use App\Support\GamePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every tracker movement in the game, for Control to read back.
 *
 * The Stats page keeps the last forty beside the numbers they moved; this is
 * the rest of the ledger, a page at a time. Read-only, like the run history.
 */
class GameLogController extends Controller
{
    public function index(Request $request, Game $game, GamePresenter $games, GameLogPresenter $log): Response
    {
        $filters = [
            'tracker' => $request->string('tracker')->toString() ?: null,
            'subject' => $request->string('subject')->toString() ?: null,
            'turn' => $request->filled('turn') ? $request->integer('turn') : null,
        ];

        return Inertia::render('control/games/log', [
            'game' => $games->controlSummary($game),
            'adjustments' => $log->page($game, $filters),
            'filters' => $filters,
            'options' => $log->options($game),
        ]);
    }
}
