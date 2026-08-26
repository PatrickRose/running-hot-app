<?php

namespace App\Http\Requests\Control;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Control's override on a built Facility: rename it, retype it, or move when it
 * comes online.
 */
class UpdateFacilityRequest extends FormRequest
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
        /** @var Game $game */
        $game = $this->route('game');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'facility_type_id' => [
                'sometimes', 'integer',
                Rule::exists('facility_types', 'id')->where('game_id', $game->id),
            ],
            'available_from_turn' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
