<?php

namespace App\Http\Requests\Control;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One Corporation building a Facility for another: MCM's Construction Leader.
 */
class BuildFacilityOnBehalfRequest extends FormRequest
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

        $corporation = Rule::exists('corporations', 'id')->where('game_id', $game->id);

        return [
            'builder_corporation_id' => ['required', 'integer', $corporation],
            'corporation_id' => ['required', 'integer', $corporation],
            'facility_type_id' => [
                'required', 'integer',
                Rule::exists('facility_types', 'id')->where('game_id', $game->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            // What the owner is charged. Blank is the type sheet's price less
            // the builder's discount; nought is free.
            'cost' => ['nullable', 'integer', 'min:0', 'max:1000'],
            // What the builder is paid. Blank is 1.
            'fee' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'mode' => ['required', Rule::in(['requisition', 'immediate'])],
        ];
    }
}
