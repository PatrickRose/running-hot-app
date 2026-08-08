<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'stability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'civil_unrest' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'setup_seconds' => ['sometimes', 'integer', 'min:30', 'max:7200'],
            'action_seconds' => ['sometimes', 'integer', 'min:30', 'max:7200'],
            'team_time_seconds' => ['sometimes', 'integer', 'min:30', 'max:7200'],
            'auto_advance' => ['sometimes', 'boolean'],
            'discord_webhook_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
