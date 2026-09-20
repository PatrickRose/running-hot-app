<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\GiveEquipmentCardRequest;
use App\Http\Requests\Control\SetEquipmentHoldingRequest;
use App\Models\Character;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Services\EquipmentService;
use Illuminate\Http\RedirectResponse;

/**
 * How many copies of an Equipment card somebody is carrying (rulebook 3.4.1).
 *
 * Control's alone, and the same shape as ProtectionCardHoldingController.
 * Runners buy from the market and from each other during Setup (2.1), sell what
 * a run brought back, and are handed cards by Control for a job that went well.
 * Every one of those is a conversation, so the application holds the number
 * rather than modelling any of the routes to it.
 *
 * Two writes, because they answer different questions. `give` adds copies and
 * is what Control reaches for at the table - it knows what it is handing over
 * and not what the player already has. `update` sets the count outright, which
 * is the correction: a card spent, a haul split, a number typed wrong.
 *
 * Anybody in the game may be handed one. Equipment used to be refused to a
 * Corporate seat, on the reasoning that a CEO with a Katana in hand is a row
 * nothing reads - but 2.1 has Runners buying equipment "from other players",
 * so a card reaches a Runner by way of somebody else often enough that the
 * refusal was in the way of the thing it was protecting. A CEO holding a card
 * to pass on is a real position, and who may hold what is Control's call.
 *
 * Thin on purpose: the write itself is EquipmentService's, which is the one
 * place `equipment_holdings` is touched. A Control route must not grow its own
 * copy of a rule the run loop also depends on.
 */
class EquipmentHoldingController extends Controller
{
    public function __construct(private readonly EquipmentService $equipment) {}

    /**
     * Hand somebody one or more copies, on top of whatever they are carrying.
     */
    public function give(Game $game, GiveEquipmentCardRequest $request): RedirectResponse
    {
        /** @var Character $character */
        $character = $game->characters()->findOrFail($request->integer('character_id'));

        /** @var EquipmentCardType $cardType */
        $cardType = $game->equipmentCardTypes()->findOrFail($request->integer('equipment_card_type_id'));

        $copies = $request->integer('copies');

        $this->equipment->giveCopies($character, $cardType, $copies);

        return back()->with('status', sprintf(
            '%s takes %d %s of %s.',
            $character->name,
            $copies,
            $copies === 1 ? 'copy' : 'copies',
            $cardType->name,
        ));
    }

    /**
     * Set the count outright, which is the correction rather than the gift.
     */
    public function update(Game $game, SetEquipmentHoldingRequest $request): RedirectResponse
    {
        /** @var Character $character */
        $character = $game->characters()->findOrFail($request->integer('character_id'));

        /** @var EquipmentCardType $cardType */
        $cardType = $game->equipmentCardTypes()->findOrFail($request->integer('equipment_card_type_id'));

        $copies = $request->integer('copies');

        $this->equipment->setCopiesInHand($character, $cardType, $copies);

        return back()->with('status', sprintf(
            '%s now carries %d %s of %s.',
            $character->name,
            $copies,
            $copies === 1 ? 'copy' : 'copies',
            $cardType->name,
        ));
    }
}
