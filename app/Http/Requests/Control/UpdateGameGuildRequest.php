<?php

namespace App\Http\Requests\Control;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameGuildRequest extends FormRequest
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
            // A Discord snowflake: 17 to 20 digits today, bounded loosely so a
            // future widening does not become a validation bug. Nullable so
            // Control can detach a game from a server again.
            'discord_guild_id' => ['nullable', 'string', 'regex:/^\d{15,25}$/'],
            'discord_invite_url' => ['nullable', 'string', 'max:2048', 'regex:#^https://(?:discord\.gg|discord\.com/invite)/[\w-]+$#'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discord_guild_id.regex' => 'That does not look like a Discord server ID. Turn on Developer Mode in Discord, right-click the server and choose "Copy Server ID".',
            'discord_invite_url.regex' => 'That does not look like a Discord invite link.',
        ];
    }
}
