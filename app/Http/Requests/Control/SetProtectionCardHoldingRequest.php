<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Control setting how many copies of a card a Corporation holds.
 *
 * The number is set outright rather than adjusted, because the events that move
 * it - the Corporation shop, an auction, a research grant, two Security players
 * trading - all happen at the table and Control is writing down where they
 * ended up, not replaying them.
 */
class SetProtectionCardHoldingRequest extends FormRequest
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
            // A hand of cards, so a sane ceiling rather than none at all: the
            // largest print run of any card in the game is twenty.
            'copies' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
