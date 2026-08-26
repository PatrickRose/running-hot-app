<?php

namespace App\Http\Requests\Control;

use App\Enums\FacilityGrantScaling;
use App\Models\FacilityType;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Control adding a Facility type mid-game (rulebook 3.3.1 footnote).
 */
class StoreFacilityTypeRequest extends FormRequest
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

        /** @var FacilityType|null $type */
        $type = $this->route('facilityType');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('facility_types', 'name')
                    ->where('game_id', $game->id)
                    ->ignore($type?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'access_effect' => ['nullable', 'string', 'max:2000'],
            'build_cost' => ['sometimes', 'integer', 'min:0', 'max:1000'],

            // Left to Control rather than inferred from the name: a type they
            // invent has to be able to grant slots, storage or a discount too.
            'physical_slots_granted' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'cyber_slots_granted' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'technology_capacity_granted' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'card_move_discount' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'grant_scaling' => ['sometimes', Rule::enum(FacilityGrantScaling::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function typeAttributes(): array
    {
        $validated = $this->validated();

        return [
            ...$validated,
            'key' => Str::slug($this->string('name')->toString()),
        ];
    }
}
