<?php

namespace App\Http\Requests;

use App\Models\Facility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Security dropping one of their Corporation's cards into a Facility.
 *
 * Which Facility is the route's, and whether this player may touch it is the
 * FacilityPolicy's - so all that is left here is that the card is one this game
 * has heard of.
 */
class InstallProtectionCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Facility $facility */
        $facility = $this->route('facility');

        return $this->user()?->can('defend', $facility) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Facility $facility */
        $facility = $this->route('facility');

        return [
            'protection_card_type_id' => [
                'required', 'integer',
                Rule::exists('protection_card_types', 'id')->where('game_id', $facility->game_id),
            ],
        ];
    }
}
