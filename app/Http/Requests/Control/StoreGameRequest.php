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
     * A webhook is optional: provisioning a game's Discord server creates one,
     * so requiring it here would ask Control for the thing the application is
     * about to make. There is still deliberately no global default, so a game
     * without one has nowhere to announce rather than somewhere wrong.
     *
     * The pattern catches the common mispaste of a channel or invite link,
     * which would otherwise only surface as a failed announcement mid-game.
     *
     * @return array<int, mixed>
     */
    public static function webhookRules(): array
    {
        return [
            'nullable',
            'url',
            'max:2048',
            'regex:#^https://(?:canary\\.|ptb\\.)?discord(?:app)?\\.com/api/(?:v\\d+/)?webhooks/\\d+/[\\w-]+$#',
        ];
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
            'discord_webhook_url' => self::webhookRules(),
            // Not a column: whether to go straight on to adding a Discord server.
            'connect_discord' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The validated attributes that belong on the model.
     *
     * Deliberately not called attributes(): FormRequest already defines that
     * for custom validation attribute names, and it is called while the
     * validator is being built, before safe() has anything to give.
     *
     * @return array<string, mixed>
     */
    public function gameAttributes(): array
    {
        return $this->safe()->except('connect_discord');
    }

    /**
     * Whether Control asked to set the game's Discord server up straight away.
     */
    public function shouldConnectDiscord(): bool
    {
        return $this->boolean('connect_discord');
    }
}
