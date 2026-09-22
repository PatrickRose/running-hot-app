<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Control handing a Corporation copies of a Protection Card (rulebook 3.3.3).
 *
 * The other half of SetProtectionCardHoldingRequest, and adding rather than
 * setting is the whole difference: Control giving cards out knows what it is
 * giving and not what the Corporation already holds, so a give that set the
 * count would quietly take away the four Angels it had. Exactly the division
 * GiveEquipmentCardRequest already draws one table along.
 */
class GiveProtectionCardRequest extends FormRequest
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
            'corporation_id' => ['required', 'integer'],
            'protection_card_type_id' => ['required', 'integer'],
            // At least one, because giving no copies of a card is not giving
            // anything. The ceiling is the hand-sized one
            // SetProtectionCardHoldingRequest already uses.
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
