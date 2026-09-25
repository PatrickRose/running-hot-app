<?php

namespace App\Http\Requests;

use App\Models\Corporation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A CEO building their Corporation a new Facility (rulebook 3.3.1).
 *
 * Which Corporation is the route's, and whether this player may build for it is
 * the CorporationPolicy's. There is deliberately no cost here: a player pays
 * the type sheet's price, and naming another is Control's override.
 */
class RequisitionFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Corporation $corporation */
        $corporation = $this->route('corporation');

        return $this->user()?->can('requisition', $corporation) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Corporation $corporation */
        $corporation = $this->route('corporation');

        return [
            'facility_type_id' => [
                'required', 'integer',
                Rule::exists('facility_types', 'id')->where('game_id', $corporation->game_id),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
