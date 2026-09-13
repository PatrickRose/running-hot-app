<?php

namespace App\Http\Requests\Research;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The two sets of cards a player is putting up as an equation
 * (rulebook 3.2.1).
 *
 * Card ids only. Whether they are in this Corporation's hand or the public
 * pool, whether one has been named twice, and whether the two sets make an
 * equation at all are all App\Services\ResearchTableService's - the rule is one
 * implementation, and a second copy of it in a validation rule would be a
 * second place for it to drift.
 */
class PlayEquationRequest extends FormRequest
{
    use ResolvesResearchCorporation;

    public function authorize(): bool
    {
        return $this->mayPlayResearch();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'left' => ['required', 'array', 'min:1'],
            'left.*' => ['required', 'integer'],
            'right' => ['required', 'array', 'min:1'],
            'right.*' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function left(): array
    {
        return $this->cardIds('left');
    }

    /**
     * @return array<int, int>
     */
    public function right(): array
    {
        return $this->cardIds('right');
    }

    /**
     * @return array<int, int>
     */
    private function cardIds(string $key): array
    {
        /** @var array<int, mixed> $ids */
        $ids = $this->input($key, []);

        return array_map('intval', array_values($ids));
    }
}
