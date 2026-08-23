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
     * Clearing the webhook is allowed: a game whose Discord server has gone
     * away should be able to say so, rather than keeping a dead URL that looks
     * configured while every announcement fails.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'discord_webhook_url' => StoreGameRequest::webhookRules(),
        ];
    }
}
