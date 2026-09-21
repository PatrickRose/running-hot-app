<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Services\EquipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * One player handing a card to another (rulebook 2.1).
 *
 * "You may buy equipment, either from the market or from other players" is the
 * whole of what the rulebook says, and this is the second half of it - the
 * first being the market counter on `/shop`. What travels is the card and
 * nothing else: whatever was agreed in exchange is settled at the table, for
 * the reason a research point trade settles there.
 *
 * Thin, like every other player-facing route here. Whether this seat is yours
 * and whether the shop's own clock is open are App\Policies\CharacterPolicy's;
 * whether there are copies to give is App\Services\EquipmentService's, which is
 * the one writer of that table. This class only says which three things are
 * being named.
 */
class EquipmentTransferController extends Controller
{
    public function __invoke(Request $request, EquipmentService $equipment): RedirectResponse
    {
        $game = Game::current();

        abort_if($game === null, 404);

        $validated = $request->validate([
            'from_character_id' => [
                'required', 'integer',
                Rule::exists('characters', 'id')->where('game_id', $game->id),
            ],
            'to_character_id' => [
                'required', 'integer',
                Rule::exists('characters', 'id')->where('game_id', $game->id),
            ],
            'equipment_card_type_id' => [
                'required', 'integer',
                Rule::exists('equipment_card_types', 'id')->where('game_id', $game->id),
            ],
            // A hand of cards, so the same ceiling Control's own give carries.
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var Character $from */
        $from = $game->characters()->findOrFail($validated['from_character_id']);

        // Asked of the seat the card is leaving, which is both what the gesture
        // names and the only hand this spends anything out of.
        Gate::authorize('giveEquipment', $from);

        /** @var Character $to */
        $to = $game->characters()->findOrFail($validated['to_character_id']);

        /** @var EquipmentCardType $card */
        $card = $game->equipmentCardTypes()->findOrFail($validated['equipment_card_type_id']);

        $copies = (int) $validated['copies'];

        $equipment->transfer($from, $to, $card, $copies);

        return back()->with('status', sprintf(
            '%s hands %s %d %s of %s.',
            $from->name,
            $to->name,
            $copies,
            $copies === 1 ? 'copy' : 'copies',
            $card->name,
        ));
    }
}
