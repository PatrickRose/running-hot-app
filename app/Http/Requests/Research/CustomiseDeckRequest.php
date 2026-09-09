<?php

namespace App\Http\Requests\Research;

use App\Enums\ResearchSuit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Buying a card into a research deck (rulebook 3.2.3).
 *
 * The suits are the player's, one per amount the tree row asks for: "spend 6
 * research credits in any suit and 3 in another" is two choices, and "5 from
 * each suit" is four. The value is the player's too, inside the range the row
 * prints. Both are checked against the row itself in
 * App\Services\ResearchTableService, which is where the row is read.
 */
class CustomiseDeckRequest extends FormRequest
{
    use ResolvesResearchCorporation;

    public function authorize(): bool
    {
        return $this->mayPlayResearch();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'technology_type_id' => [
                'required', 'integer',
                Rule::exists('technology_types', 'id')->where('game_id', $this->researchGame()?->id),
            ],
            'suits' => ['required', 'array', 'min:1'],
            'suits.*' => ['required', Rule::enum(ResearchSuit::class)],
            'value' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, ResearchSuit>
     */
    public function suits(): array
    {
        /** @var array<int, string> $suits */
        $suits = $this->input('suits', []);

        return array_map(
            fn (string $suit): ResearchSuit => ResearchSuit::from($suit),
            array_values($suits),
        );
    }
}
