<?php

namespace App\Http\Requests;

use App\Models\CouncilAgendaItem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A CEO's vote, split however they like across the resolutions on one card
 * (rulebook 3.1.2).
 *
 * The cap - a Corporation may spread no more than the Political Will it holds -
 * is CouncilService's, because it is a rule rather than a shape. What this
 * checks is that the numbers are numbers.
 */
class CastBallotRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CouncilAgendaItem $item */
        $item = $this->route('item');

        return $this->user()?->can('vote', $item->session) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * Political Will per resolution, keyed by resolution id.
     *
     * @return array<int, int>
     */
    public function allocations(): array
    {
        /** @var array<array-key, mixed> $allocations */
        $allocations = $this->input('allocations', []);

        $mapped = [];

        foreach ($allocations as $resolutionId => $will) {
            $mapped[(int) $resolutionId] = (int) $will;
        }

        return $mapped;
    }
}
