<?php

namespace App\Http\Requests\Control;

use App\Enums\ProtectionCardAvailability;
use App\Enums\ProtectionKind;
use App\Models\Game;
use App\Models\ProtectionCardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Control writing a card into the catalogue (rulebook 3.3.2).
 */
class StoreProtectionCardTypeRequest extends FormRequest
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
        /** @var Game $game */
        $game = $this->route('game');

        /** @var ProtectionCardType|null $cardType */
        $cardType = $this->route('cardType');

        return [
            // The code printed on the card is what identifies it, so it is the
            // one thing that may not repeat. Titles do repeat: Doppleganger is
            // two cards, one in each stack.
            'code' => [
                'nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('protection_card_types', 'code')
                    ->where('game_id', $game->id)
                    ->ignore($cardType?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(ProtectionKind::class)],
            // A card nobody can buy yet has no price, which is not the same as
            // being free.
            'cost' => ['nullable', 'integer', 'min:0', 'max:1000'],
            // The challenge as the card prints it - "Brute (6)", or
            // "Hack (4+N) - where N is the number of cards underneath this".
            // Nothing parses it, so anything the card says is valid.
            'challenge' => ['required', 'string', 'max:500'],
            'consequence' => ['required', 'string', 'max:2000'],
            'charge_cost' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'charge_consequence' => ['nullable', 'string', 'max:2000'],
            'availability' => ['required', Rule::enum(ProtectionCardAvailability::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * A Charge is a cost and a consequence together: one without the other is
     * an ability nobody can use, or a price for nothing.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $cost = $this->input('charge_cost');
                $consequence = $this->input('charge_consequence');

                if ($cost !== null && blank($consequence)) {
                    $validator->errors()->add(
                        'charge_consequence',
                        'A Charge cost needs a Charge consequence.',
                    );
                }

                if (filled($consequence) && $cost === null) {
                    $validator->errors()->add(
                        'charge_cost',
                        'A Charge consequence needs a cost to use it.',
                    );
                }
            },
        ];
    }
}
