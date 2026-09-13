<?php

namespace App\Http\Requests\Control;

use App\Enums\TechnologyOrigin;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Research Control putting a technology card into a Corporation's hands
 * (rulebook 3.2.5, 3.2.6).
 */
class GrantTechnologyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Game $game */
        $game = $this->route('game');

        return [
            'corporation_id' => [
                'required', 'integer',
                Rule::exists('corporations', 'id')->where('game_id', $game->id),
            ],
            'technology_type_id' => [
                'required', 'integer',
                Rule::exists('technology_types', 'id')->where('game_id', $game->id),
            ],
            'origin' => ['required', Rule::enum(TechnologyOrigin::class)],
            // Left out to take the origin's own discount, which is what
            // Research Control usually wants.
            'discount_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'facility_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function origin(): TechnologyOrigin
    {
        return TechnologyOrigin::from($this->string('origin')->toString());
    }
}
