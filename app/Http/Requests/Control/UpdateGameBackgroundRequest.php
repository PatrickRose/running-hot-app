<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameBackgroundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isControl() ?? false;
    }

    /**
     * Any web address will do - a briefing pack lives wherever the organisers
     * keep it - but it has to be one a browser can open, because every
     * player's sidebar links straight to it. Clearing it takes the link away.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'background_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }
}
