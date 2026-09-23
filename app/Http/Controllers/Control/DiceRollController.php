<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use App\Support\DiceRollPresenter;
use App\Support\GamePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every roll players have made for Control to read, newest first.
 *
 * Read-only on purpose. A roll is evidence for a ruling rather than a thing
 * that moves a number, so what Control does with it - Credits, a Wound, a
 * plot lead - goes through the tracker controls like any other ruling.
 */
class DiceRollController extends Controller
{
    public function index(Request $request, Game $game, GamePresenter $games, DiceRollPresenter $rolls): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('control/games/dice', [
            'game' => $games->controlSummary($game),
            'rolls' => $rolls->recent($game, $user),
        ]);
    }
}
