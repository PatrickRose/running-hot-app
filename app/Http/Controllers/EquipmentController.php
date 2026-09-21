<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Support\GamePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What you are carrying, as you see it (rulebook 3.4.1).
 *
 * Every Runner needs this and had nowhere to read it: the briefing names a
 * starting kit and then the count moves - a Single-use card is spent, a
 * permanent item is lost to the Security player who carried you out, Control
 * hands one over for a job that went well - and only Control could see where it
 * had got to. Choosing three permanent items to equip means being able to see
 * what there is to choose from.
 *
 * Not only a Runner's, either. 2.1 has Runners buying equipment "from other
 * players", so a card may be sitting with whoever bought it to hand over - a
 * CEO as readily as a gangmate - and they need to be able to read it too.
 *
 * You see your own hands and nobody else's. The rulebook does not make a hand
 * Secret the way 3.4.2 makes a Facility's stack, so this is a ruling rather
 * than a reading - but a gang reading each other's kit off a screen is a gang
 * that never has the conversation, and at the table you would have to ask.
 * Control sees everybody, because Control always does.
 *
 * Handing a card to another player happens here, which is the one thing on this
 * page that is not read-only: 2.1 has Runners buying equipment "from other
 * players", and that half of the sentence had nowhere to happen. What travels
 * is the card alone - whatever was agreed in exchange is settled at the table,
 * for the reason a research point trade settles there. Every other way a count
 * moves is still a conversation with Control, who sets it on their own panel.
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
            // Everybody a card can be handed to. The whole roster, because 2.1
            // names no restriction on the far side of the trade and who may
            // hold a card is Control's call - a Runner squaring a debt with a
            // CEO is a trade the rulebook has nothing to say against.
            'recipients' => $game === null ? [] : $presenter->equipmentRecipients($game),
            // So the page can say whose hands these are. Control is reading the
            // whole game and should be told so; a player is reading their own.
            'is_control' => $game !== null && $user !== null && $user->isControlFor($game),
        ]);
    }
}
