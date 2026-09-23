<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Support\CardImage;
use App\Support\GamePresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The game's Protection Cards and Equipment, as printed, for every player.
 *
 * Control has had the card lists all along, on its own panel; a player had
 * only the cards in their own hand, and a Runner deciding what to carry into a
 * Facility or a Security player deciding what to buy had nothing to read the
 * rest of them off. So this is the same list, read-only, and without the parts
 * of it that are Control's - see `GamePresenter::publicCardList()` for what is
 * left off and why.
 *
 * Research is deliberately not here. Technologies are the research game's, and
 * a tech tree is read by the Corporation it belongs to on `/research`.
 */
class CardListController extends Controller
{
    public function __invoke(GamePresenter $presenter): Response
    {
        $game = Game::current();

        return Inertia::render('cards', [
            'game' => $game === null ? null : $presenter->summary($game),
            'cards' => $game === null ? null : $presenter->publicCardList($game),
            'hasArtwork' => CardImage::anyOnRecord(),
        ]);
    }
}
