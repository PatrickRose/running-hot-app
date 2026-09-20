<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Control handing somebody an Equipment card (rulebook 2.1, 3.4.1).
 *
 * The other half of SetEquipmentHoldingRequest, and adding rather than setting
 * is the whole difference: Control giving a card out knows what it is giving
 * and not what the player is already carrying, so a give that set the count
 * would quietly take away the two Shivs they had.
 */
class GiveEquipmentCardRequest extends FormRequest
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
        return [
            'character_id' => ['required', 'integer'],
            'equipment_card_type_id' => ['required', 'integer'],
            // At least one, because handing somebody no copies of a card is
            // not handing them anything. The ceiling is the hand-sized one
            // SetEquipmentHoldingRequest already uses.
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
