<?php

namespace App\Http\Requests\Control;

use App\Enums\ResearchSuit;
use App\Http\Requests\ShapesCardMarkings;
use App\Support\CardMarking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A research card's printed face: its suit, its value and anything else on it.
 *
 * An empty suit is a wild card rather than a card with no suit set - "some
 * cards are marked as wild and can be used as any type" - which is why the
 * field is nullable rather than required.
 *
 * A marking is a choice rather than free text, because the equation rules act
 * on it: a marking they could not enforce would be worse on the card than no
 * marking at all. A card may carry more than one - the two the game prints are
 * about different halves of the equation - so this takes a list.
 */
class UpdateResearchCardRequest extends FormRequest
{
    use ShapesCardMarkings;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'markings' => $this->filterBlankMarkings($this->input('markings')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'suit' => ['nullable', Rule::enum(ResearchSuit::class)],
            'value' => ['required', 'integer', 'min:1', 'max:99'],
            ...$this->markingRules(),
        ];
    }

    /**
     * @return array<int, CardMarking>
     */
    public function markings(): array
    {
        /** @var array<int, mixed> $submitted */
        $submitted = $this->input('markings', []);

        return $this->shapeMarkings($submitted);
    }
}
