<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Moving a technology card, repricing what it is worth, or salvaging one a Run
 * destroyed (rulebook 3.2.6).
 *
 * Every field is optional, and a field that is not sent is left alone: this is
 * one form doing several small jobs rather than a full replacement of the row.
 */
class UpdateTechnologyHoldingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'facility_id' => ['sometimes', 'nullable', 'integer'],
            'discount_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'restore' => ['sometimes', 'boolean'],
        ];
    }
}
