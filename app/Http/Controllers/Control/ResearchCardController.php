<?php

namespace App\Http\Controllers\Control;

use App\Enums\ResearchCardRestriction;
use App\Enums\ResearchSuit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Control\UpdateResearchCardRequest;
use App\Models\Game;
use App\Models\ResearchCard;
use App\Services\ResearchTableService;
use Illuminate\Http\RedirectResponse;

/**
 * Editing a card in a research deck (rulebook 3.2.3).
 *
 * The other half of deck customisation. The tree prices adding a card, and the
 * application sells that; the rulebook also offers "upgrading your existing
 * cards" and prices it nowhere, so an upgrade is a custom proposal under 3.2.4:
 * Research Control names a price, takes the points with the tracker controls,
 * and changes the card here.
 */
class ResearchCardController extends Controller
{
    public function __construct(private readonly ResearchTableService $table) {}

    public function update(
        Game $game,
        ResearchCard $card,
        UpdateResearchCardRequest $request,
    ): RedirectResponse {
        abort_if($card->game_id !== $game->id, 404);

        $suit = $request->string('suit')->toString();
        $restriction = $request->string('restriction')->toString();

        $this->table->editCard(
            $card,
            $suit === '' ? null : ResearchSuit::from($suit),
            $request->integer('value'),
            $restriction === '' ? null : ResearchCardRestriction::from($restriction),
        );

        return back()->with('status', 'Research card is now '.$card->label().'.');
    }
}
