<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Where Security's meeple sits, and the budget on the Facility (rulebook 3.3.5).
 */
class UpdateSecurityDirectionRequest extends FormRequest
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
        return [
            'security_directed' => ['sometimes', 'boolean'],
            'security_budget' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'security_budget_spent' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
