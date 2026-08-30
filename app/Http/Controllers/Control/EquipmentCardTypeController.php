<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\Control\StoreEquipmentCardTypeRequest;
use App\Models\EquipmentCardType;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;

/**
 * The game's Equipment catalogue (rulebook 3.4.1).
 *
 * Control owns it end to end, and has to be able to add to it during play: a
 * DTC technology hands out a bypass card named after whichever Protection Card
 * it counters, and no such card has ever been printed.
 */
class EquipmentCardTypeController extends Controller
{
    public function store(Game $game, StoreEquipmentCardTypeRequest $request): RedirectResponse
    {
        $card = $game->equipmentCardTypes()->create($request->validated());

        return back()->with('status', $card->name.' added to the Equipment list.');
    }

    public function update(
        Game $game,
        EquipmentCardType $equipmentCard,
        StoreEquipmentCardTypeRequest $request,
    ): RedirectResponse {
        abort_if($equipmentCard->game_id !== $game->id, 404);

        $equipmentCard->update($request->validated());

        return back()->with('status', $equipmentCard->name.' updated.');
    }

    public function destroy(Game $game, EquipmentCardType $equipmentCard): RedirectResponse
    {
        abort_if($equipmentCard->game_id !== $game->id, 404);

        $equipmentCard->delete();

        return back()->with('status', $equipmentCard->name.' removed from the Equipment list.');
    }
}
