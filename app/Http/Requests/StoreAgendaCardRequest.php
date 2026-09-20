<?php

namespace App\Http\Requests;

use App\Models\AgendaCard;
use App\Models\Character;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // Which of the player's characters is writing it. That it is
            // theirs is checked here; that it holds a seat is checked below,
            // because the seat needs the Eloquent scope that is the one
            // definition of who sits at the Council.
            'character_id' => [
                'required', 'integer',
                Rule::exists('characters', 'id')
                    ->where('game_id', $this->game()?->id)
                    ->where('user_id', $this->user()?->id),
            ],
        ];
    }

    /**
     * The author has to be a character with a seat, not merely a character
     * belonging to somebody who has one.
     *
     * Otherwise a player holding both a CEO seat and a Runner could raise an
     * agenda under the Runner's name, and the Chair would be handed a card
     * from somebody who is not at the table. The policy asks whether this
     * *user* may write one at all; this asks which of their seats is signing
     * it.
     *
     * Control is not held to it: Control has its own route for the deck, and
     * reaches this one through the policy's before() to write on a player's
     * behalf.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $game = $this->game();

                if ($game === null || $validator->errors()->has('character_id')) {
                    return;
                }

                if ($this->user()?->isControlFor($game)) {
                    return;
                }

                $seated = $game->characters()
                    ->whereKey($this->integer('character_id'))
                    ->onTheCouncil()
                    ->exists();

                if (! $seated) {
                    $validator->errors()->add(
                        'character_id',
                        'Only a character with a seat at the Council may raise an agenda.',
                    );
                }
            },
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
