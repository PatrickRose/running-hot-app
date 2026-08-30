<?php

namespace App\Http\Requests\Control;

use App\Models\ControlMember;
use App\Models\Game;
use App\Support\DiscordHandle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreControlMemberRequest extends FormRequest
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
            'discord_username' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discord_username' => DiscordHandle::normalise($this->input('discord_username')),
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

                // A second seat for the same person is not a second organiser,
                // and the seat they already hold may already be claimed.
                $taken = ControlMember::query()
                    ->where('game_id', $game->id)
                    ->where(fn ($query) => $query
                        ->whereRaw('LOWER(discord_username) = ?', [$handle])
                        ->orWhereHas('user', fn ($user) => $user->whereRaw('LOWER(discord_username) = ?', [$handle])))
                    ->exists();

                if ($taken) {
                    $validator->errors()->add(
                        'discord_username',
                        'That Discord handle is already on this game\'s Control team.',
                    );
                }
            },
        ];
    }
}
