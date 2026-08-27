<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Support\CardImage;
use App\Support\GamePresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The two card lists this application holds but does not yet play with: the
 * Equipment Runners carry (rulebook 3.4.1) and the technologies on the
 * Corporations' tech trees (3.2.2).
 *
 * Read-only, and deliberately so. The market that sells equipment and the
 * research game that spends Research Points are both unbuilt, so there is
 * nothing here for the application to enforce - what Control needs is to be able
 * to look a card up while ruling on it at the table, which is what a printed
 * card list would have been for.
 */
class CardCatalogueController extends Controller
{
    public function index(Game $game, GamePresenter $presenter): Response
    {
        return Inertia::render('control/games/cards', [
            'game' => $presenter->summary($game),
            'equipment' => $presenter->equipmentCardTypes($game),
            'technologies' => $presenter->technologyTypes($game),
            'researchSuits' => $presenter->researchSuits(),
            // So the page can say the artwork is missing rather than showing
            // rows of text boxes as though every card were one Control invented.
            'hasArtwork' => CardImage::anyOnRecord(),
        ]);
    }
}
