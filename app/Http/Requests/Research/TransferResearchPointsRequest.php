<?php

namespace App\Http\Requests\Research;

use App\Enums\ResearchSuit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Handing Research Points to another Corporation (rulebook 3.2.5).
 *
 * One way, and no price: "Players may freely trade their Research Points ... by
 * passing over the requisite tokens", so what the sender got back is their
 * business and happens away from here. The receiving Corporation is not asked -
 * nobody refuses tokens, and a two-sided handshake would make a trade at the
 * table into a form-filling exercise.
 */
class TransferResearchPointsRequest extends FormRequest
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
            'corporation_id' => [
                'required', 'integer',
                Rule::exists('corporations', 'id')->where('game_id', $this->researchGame()?->id),
            ],
            'suit' => ['required', Rule::enum(ResearchSuit::class)],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function suit(): ResearchSuit
    {
        return ResearchSuit::from($this->string('suit')->toString());
    }
}
