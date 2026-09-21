<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Support\GamePresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every number in the game, in one place, editable.
 *
 * Control is asked to move these constantly - the rulebook defers to them on
 * almost every page - and the numbers were spread across the panel a card at a
 * time, with a character's four printed stats editable nowhere at all. This is
 * the one screen that holds the lot: Procatorion, the Corporations, the gangs
 * and every character, with the ledger underneath saying why each of them last
 * moved.
 *
 * It owns no rules. Trackers still go through TrackerService from the dialog
 * behind each number, and the four character stats through
 * CharacterController::updateStats, which is the only write here that does not
 * write a ledger row - see that method for why.
 */
class StatsController extends Controller
{
    public function index(Game $game, GamePresenter $presenter): Response
    {
        return Inertia::render('control/games/stats', [
            'game' => $presenter->controlSummary($game),
            'trackers' => $presenter->trackers($game),
            'adjustments' => $presenter->recentAdjustments($game),
        ]);
    }
}
