<?php

namespace App\Http\Requests\Control;

use App\Models\Character;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Control putting the address a player signed up with against their seat.
 *
 * The fallback for when the Discord handle was never right - see
 * App\Actions\ClaimCharactersByEmail.
 */
class UpdateCharacterEmailRequest extends FormRequest
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
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Character::normaliseEmail($this->input('email')),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $email = $this->input('email');

                if ($email === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Game $game */
                $game = $this->route('game');
                /** @var Character $character */
                $character = $this->route('character');

                // The same reasoning the Discord handle is held to: a claim
                // matches every unclaimed seat on the address, so two
                // characters sharing one would hand whoever got there first
                // both of them. A player who really is on two seats in one
                // game takes the second by handle, or Control assigns it.
                $taken = Character::query()
                    ->where('game_id', $game->id)
                    ->whereKeyNot($character->getKey())
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->exists();

                if ($taken) {
                    $validator->errors()->add(
                        'email',
                        'Another character in this game is already reserved for that email address.',
                    );
                }
            },
        ];
    }
}
