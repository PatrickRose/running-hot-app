<?php

namespace App\Http\Requests\Control;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallProtectionCardRequest extends FormRequest
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

        return [
            'protection_card_type_id' => [
                'required', 'integer',
                Rule::exists('protection_card_types', 'id')->where('game_id', $game->id),
            ],
        ];
    }
}
