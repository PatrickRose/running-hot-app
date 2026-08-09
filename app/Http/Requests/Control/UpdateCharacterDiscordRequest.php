<?php

namespace App\Http\Requests\Control;

use App\Models\Character;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCharacterDiscordRequest extends FormRequest
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
            'discord_username' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discord_username' => Character::normaliseDiscordUsername($this->input('discord_username')),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $handle = $this->input('discord_username');

                if ($handle === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Game $game */
                $game = $this->route('game');
                /** @var Character $character */
                $character = $this->route('character');

                // Two characters in one game sharing a handle would make the
                // claim ambiguous, and silently hand a player both.
                $taken = Character::query()
                    ->where('game_id', $game->id)
                    ->whereKeyNot($character->getKey())
                    ->whereRaw('LOWER(discord_username) = ?', [$handle])
                    ->exists();

                if ($taken) {
                    $validator->errors()->add(
                        'discord_username',
                        'Another character in this game is already reserved for that Discord handle.',
                    );
                }
            },
        ];
    }
}
