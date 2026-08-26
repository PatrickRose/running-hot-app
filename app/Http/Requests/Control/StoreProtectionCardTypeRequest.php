<?php

namespace App\Http\Requests\Control;

use App\Enums\ProtectionCardAvailability;
use App\Enums\ProtectionKind;
use App\Enums\RunnerSkill;
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
            // Titles are unique because the one-copy-per-Facility rule is by
            // title.
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('protection_card_types', 'name')
                    ->where('game_id', $game->id)
                    ->ignore($cardType?->id),
            ],
            'kind' => ['required', Rule::enum(ProtectionKind::class)],
            'cost' => ['required', 'integer', 'min:0', 'max:1000'],
            'challenge_skill' => ['required', Rule::enum(RunnerSkill::class)],
            'challenge_strength' => ['required', 'integer', 'min:0', 'max:20'],
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
