<?php

namespace App\Http\Requests\Research;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Spending Research Points on the tree, and housing what comes out
 * (rulebook 3.2.2).
 *
 * The Facility is required because the rulebook makes it so: "You must then
 * place this in one of your Facilities", and footnote 7 turns that into a bar
 * on researching at all rather than a thing to sort out later.
 *
 * The holding is the optional half: a copy or a stolen card the Corporation
 * already has, which pays a discounted price and is flipped rather than
 * duplicated.
 */
class ResearchTechnologyRequest extends FormRequest
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
        $game = $this->researchGame();
        $corporation = $this->researchCorporation();

        return [
            'technology_type_id' => [
                'required', 'integer',
                Rule::exists('technology_types', 'id')->where('game_id', $game?->id),
            ],
            'facility_id' => [
                'required', 'integer',
                Rule::exists('facilities', 'id')->where('corporation_id', $corporation?->id),
            ],
            'technology_holding_id' => [
                'nullable', 'integer',
                Rule::exists('technology_holdings', 'id')->where('corporation_id', $corporation?->id),
            ],
        ];
    }
}
