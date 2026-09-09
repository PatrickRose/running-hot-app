<?php

namespace App\Http\Requests\Control;

use App\Enums\EquationSide;
use App\Enums\ResearchSuit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Control scoring an equation on a player's behalf (rulebook 3.2.1).
 *
 * The same three answers the player would give. Authorisation is the route's -
 * every Control route already carries can:control-game - so there is nothing
 * to add here beyond the shape.
 */
class ScoreResearchEquationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'side' => ['required', Rule::enum(EquationSide::class)],
            'suit' => ['required', Rule::enum(ResearchSuit::class)],
            'bonus' => ['sometimes', 'array'],
            'bonus.*' => ['integer', 'min:0'],
        ];
    }

    public function side(): EquationSide
    {
        return EquationSide::from($this->string('side')->toString());
    }

    public function suit(): ResearchSuit
    {
        return ResearchSuit::from($this->string('suit')->toString());
    }

    /**
     * @return array<string, int>
     */
    public function bonus(): array
    {
        /** @var array<string, mixed> $bonus */
        $bonus = $this->input('bonus', []);
        $allocation = [];

        foreach ($bonus as $suit => $points) {
            $allocation[(string) $suit] = (int) $points;
        }

        return $allocation;
    }
}
