<?php

namespace App\Http\Requests\Control;

use App\Enums\EquipmentCategory;
use App\Models\EquipmentCardType;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Control writing an Equipment card into the catalogue (rulebook 3.4.1).
 *
 * Needed during play rather than only at setup: DTC's "Unfortunate Malfunction"
 * hands out a single-use bypass card named after whichever Protection Card it
 * counters, and no such card is printed. Control invents it on the night.
 */
class StoreEquipmentCardTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isControl() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Game $game */
        $game = $this->route('game');

        /** @var EquipmentCardType|null $card */
        $card = $this->route('equipmentCard');

        return [
            // The code is what identifies a printed card and finds its artwork.
            // A card Control invents has neither, so it may be left blank.
            'code' => [
                'nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('equipment_card_types', 'code')
                    ->where('game_id', $game->id)
                    ->ignore($card?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            // The one attribute the application reads rather than merely shows:
            // it decides when the card may be played and whether it counts
            // against the three items a Runner may equip.
            'category' => ['required', Rule::enum(EquipmentCategory::class)],
            'effect' => ['required', 'string', 'max:2000'],
            // Null where the market does not sell it, which is not the same as
            // free. Pricing arrives with the shop.
            'cost' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
