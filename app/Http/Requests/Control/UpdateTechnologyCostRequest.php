<?php

namespace App\Http\Requests\Control;

use App\Enums\ResearchSuit;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Control repricing a technology already on the tree (rulebook 3.2.2).
 *
 * Only the four suits, deliberately. The whole-row update takes every field and
 * rebuilds the prerequisites and the deck grant from what it is sent, so a form
 * that only meant to change a price would have to carry the rest of the card
 * back unchanged - and one field it forgot would be quietly wiped.
 */
class UpdateTechnologyCostRequest extends FormRequest
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
        $rules = [];

        // Zero is a real price in a suit rather than a missing one, the same
        // bound the whole-row form holds a technology to.
        foreach (ResearchSuit::all() as $suit) {
            $rules[$suit->costColumn()] = ['required', 'integer', 'min:0', 'max:1000'];
        }

        return $rules;
    }
}
