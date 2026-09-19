<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What a Runner is carrying, as they see it (rulebook 3.4.1).
 *
 * Every Runner needs this and had nowhere to read it: the briefing names a
 * starting kit and then the count moves - a Single-use card is spent, a
 * permanent item is lost to the Security player who carried you out, Control
 * hands one over for a job that went well - and only Control could see where it
 * had got to. Choosing three permanent items to equip means being able to see
 * what there is to choose from.
 *
 * You see your own hands and nobody else's. The rulebook does not make a hand
 * Secret the way 3.4.2 makes a Facility's stack, so this is a ruling rather
 * than a reading - but a gang reading each other's kit off a screen is a gang
 * that never has the conversation, and at the table you would have to ask.
 * Control sees everybody, because Control always does.
 *
 * Read-only. Every way a card changes hands is a conversation with Control, who
 * sets the count on their own panel - the same division the Protection Card
 * holdings live under.
 */
class EquipmentController extends Controller
{
    public function __invoke(Request $request, GamePresenter $presenter): Response
    {
        $game = Game::current();
        $user = $request->user();

        return Inertia::render('equipment', [
            'game' => $game === null ? null : $presenter->summary($game),
            'holdings' => $game === null ? null : $presenter->equipmentHoldings($game, $user),
            // So the page can say whose hands these are. Control is reading the
            // whole game and should be told so; a player is reading their own.
            'is_control' => $game !== null && $user !== null && $user->isControlFor($game),
        ]);
    }
}
