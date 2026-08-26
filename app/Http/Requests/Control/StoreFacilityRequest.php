<?php

namespace App\Http\Requests\Control;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacilityRequest extends FormRequest
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
            'corporation_id' => [
                'required', 'integer',
                Rule::exists('corporations', 'id')->where('game_id', $game->id),
            ],
            'facility_type_id' => [
                'required', 'integer',
                Rule::exists('facility_types', 'id')->where('game_id', $game->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'cost' => ['sometimes', 'integer', 'min:0', 'max:1000'],

            // "requisition" is the rule as written: raised during Setup, opens
            // next turn. "immediate" is the Control override, and is how a
            // game's starting Facilities go in.
            'mode' => ['required', Rule::in(['requisition', 'immediate'])],
        ];
    }
}
