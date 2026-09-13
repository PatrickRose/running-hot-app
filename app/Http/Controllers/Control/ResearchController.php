<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Support\GamePresenter;
use App\Support\ResearchPresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Research Control's own view of the sub-game (rulebook 3.2).
 *
 * Everything, with no secrets kept: Research Control deals the cards, holds the
 * tokens, prices the custom proposals, makes the copies when two Corporations
 * agree to share, and scores for whoever cannot reach a screen. A tiered view
 * would only get in their way.
 */
class ResearchController extends Controller
{
    public function index(Game $game, GamePresenter $games, ResearchPresenter $research): Response
    {
        return Inertia::render('control/games/research', [
            'game' => $games->summary($game),
            'research' => $research->control($game),
        ]);
    }
}
