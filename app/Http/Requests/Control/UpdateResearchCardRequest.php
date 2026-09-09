<?php

namespace App\Http\Requests\Control;

use App\Enums\ResearchCardRestriction;
use App\Enums\ResearchSuit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A research card's printed face: its suit, its value and anything else on it.
 *
 * An empty suit is a wild card rather than a card with no suit set - "some
 * cards are marked as wild and can be used as any type" - which is why the
 * field is nullable rather than required.
 *
 * A restriction is a choice rather than free text, because the equation rules
 * act on it: a marking they could not enforce would be worse on the card than
 * no marking at all.
 */
class UpdateResearchCardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'suit' => ['nullable', Rule::enum(ResearchSuit::class)],
            'value' => ['required', 'integer', 'min:1', 'max:99'],
            'restriction' => ['nullable', Rule::enum(ResearchCardRestriction::class)],
        ];
    }
}
