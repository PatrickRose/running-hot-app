<?php

namespace App\Http\Requests\Research;

use App\Enums\EquationSide;
use App\Enums\ResearchSuit;
use App\Models\ResearchEquation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Taking the points an equation is worth (rulebook 3.2.1).
 *
 * Three answers, because the rulebook asks three questions: which side you are
 * taking your points from, which of that side's suits you are taking them in
 * (an all-wild set may be any of the four), and how a balanced equation's bonus
 * is split across the suits you used.
 *
 * Whether those answers are legal is App\Support\Equation's - it is the one
 * implementation of the scoring rules, and it is what the payout is worked out
 * from.
 */
class ScoreEquationRequest extends FormRequest
{
    use ResolvesResearchCorporation;

    public function authorize(): bool
    {
        /** @var ResearchEquation $equation */
        $equation = $this->route('equation');

        $corporation = $this->researchCorporation();

        // Your own equations, and only your own: the points land on the
        // Corporation that played it.
        return $this->mayPlayResearch()
            && $corporation !== null
            && $equation->corporation_id === $corporation->id;
    }

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
     * The bonus split, as points by suit.
     *
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
