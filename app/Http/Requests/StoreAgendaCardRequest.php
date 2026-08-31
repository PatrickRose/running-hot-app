<?php

namespace App\Http\Requests;

use App\Models\AgendaCard;
use App\Models\Character;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A player filling out a blank agenda card (rulebook 3.1.3).
 *
 * The 2-to-5 bound on resolutions is 3.1.4's, and it is checked here as well as
 * in CouncilService so that a player writing a card is told which field is
 * wrong rather than being handed a rule violation. The service is still the
 * authority: it is the only thing every other route into a card goes through.
 */
class StoreAgendaCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $game = $this->game();

        return $game !== null && ($this->user()?->can('create', [AgendaCard::class, $game]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
            'resolutions' => [
                'required', 'array',
                'min:'.AgendaCard::MINIMUM_RESOLUTIONS,
                'max:'.AgendaCard::MAXIMUM_RESOLUTIONS,
            ],
            'resolutions.*' => ['required', 'string', 'max:500'],
            // Which of the player's characters is writing it. Any of them may:
            // the rulebook hands blank cards to players rather than to CEOs.
            'character_id' => [
                'required', 'integer',
                Rule::exists('characters', 'id')
                    ->where('game_id', $this->game()?->id)
                    ->where('user_id', $this->user()?->id),
            ],
        ];
    }

    public function game(): ?Game
    {
        /** @var Game|null $game */
        $game = $this->route('game') ?? Game::query()
            ->where('status', 'running')
            ->latest('id')
            ->first();

        return $game;
    }

    public function author(): Character
    {
        /** @var Character */
        return Character::query()->findOrFail($this->integer('character_id'));
    }

    /**
     * @return array<int, string>
     */
    public function resolutions(): array
    {
        /** @var array<int, mixed> $resolutions */
        $resolutions = $this->input('resolutions', []);

        return array_map('strval', array_values($resolutions));
    }
}
