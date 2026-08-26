<?php

namespace App\Http\Requests\Control;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The one confirmation gate in the application.
 *
 * Everything else Control can do is either reversible or leaves a
 * `tracker_adjustments` row saying what happened. Clearing a guild is neither:
 * the channels and every message in them are gone from Discord, not just from
 * here. Typing the game's name is the cheapest guard that cannot be clicked
 * through by accident, and it names the game being cleared — which matters when
 * Control has several open and only one of them is the test server.
 */
class ResetDiscordGuildRequest extends FormRequest
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
        $game = $this->route('game');

        return [
            'confirm' => ['required', 'string', Rule::in([$game instanceof Game ? $game->name : null])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $game = $this->route('game');

        return [
            'confirm.in' => sprintf(
                'Type the game\'s name exactly — %s — to confirm that every channel and role in its Discord server should be deleted.',
                $game instanceof Game ? $game->name : 'the game name',
            ),
            'confirm.required' => 'Type the game\'s name to confirm.',
        ];
    }
}
