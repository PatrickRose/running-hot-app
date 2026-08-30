<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Support\CardImage;
use App\Support\GamePresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * All three of the game's card lists, as printed.
 *
 * Read-only, and deliberately so: what Control needs here is to look a card up
 * while ruling on it at the table, which is what a printed card list would have
 * been for. Protection Cards are the ones that gets asked of most often, since a
 * Run turns on a challenge and its consequence.
 *
 * The Protection Card catalogue is editable, but on the Facility Defence page
 * rather than this one - it belongs beside installing, which is the thing that
 * makes a card matter. This is the same list to look at rather than to change.
 */
class CardCatalogueController extends Controller
{
    public function index(Game $game, GamePresenter $presenter): Response
    {
        return Inertia::render('control/games/cards', [
            'game' => $presenter->summary($game),
            'protectionCards' => $presenter->protectionCardTypes($game),
            'equipment' => $presenter->equipmentCardTypes($game),
            'technologies' => $presenter->technologyTypes($game),
            'researchSuits' => $presenter->researchSuits(),
            // For the forms that add a card: which trees a technology may sit
            // on, and which Facility types it may require.
            'technologyTrees' => $presenter->technologyTrees($game),
            'facilityTypes' => $presenter->facilityTypes($game),
            // So the page can say the artwork is missing rather than showing
            // rows of text boxes as though every card were one Control invented.
            'hasArtwork' => CardImage::anyOnRecord(),
        ]);
    }
}
