<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameWebhookRequest extends FormRequest
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
            'discord_webhook_url' => StoreGameRequest::webhookRules(),
        ];
    }
}
