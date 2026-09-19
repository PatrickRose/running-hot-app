<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Control setting how many copies of an Equipment card a Runner is carrying.
 *
 * The counterpart of SetProtectionCardHoldingRequest, and set outright for the
 * same reason: every way a card changes hands in the rules is a conversation at
 * the table (2.2.1) - the market, a Runner selling to another, a gang splitting
 * a haul - so Control writes down where a count ended up rather than replaying
 * how it got there.
 */
class SetEquipmentHoldingRequest extends FormRequest
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
            // A hand of cards, so a ceiling rather than none at all. The
            // briefings' largest is $TUX's four H4ck1ng 4 Dummi3s, and Control
            // has plenty of room above that.
            'copies' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
