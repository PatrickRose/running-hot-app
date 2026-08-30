<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\SetProtectionCardHoldingRequest;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Services\FacilityDefenceService;
use Illuminate\Http\RedirectResponse;

/**
 * How many copies of a Protection Card a Corporation owns.
 *
 * Control's alone, and set outright. The rulebook has Corporations buying cards
 * from the shop, winning them at auction, being granted them by research and
 * trading them between Security players - every one of those is a conversation
 * at the table, so the application does not model any of them. It holds the
 * number, and the number is what caps how many Facilities a card can defend.
 */
class ProtectionCardHoldingController extends Controller
{
    public function __construct(private readonly FacilityDefenceService $defence) {}

    public function update(Game $game, SetProtectionCardHoldingRequest $request): RedirectResponse
    {
        /** @var Corporation $corporation */
        $corporation = $game->corporations()->findOrFail($request->integer('corporation_id'));

        /** @var ProtectionCardType $cardType */
        $cardType = $game->protectionCardTypes()->findOrFail($request->integer('protection_card_type_id'));

        $copies = $request->integer('copies');

        $this->defence->setCopiesInHand($corporation, $cardType, $copies);

        return back()->with('status', sprintf(
            '%s now holds %d copy/copies of %s.',
            $corporation->name,
            $copies,
            $cardType->name,
        ));
    }
}
