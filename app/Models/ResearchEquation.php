<?php

namespace App\Models;

use App\Enums\EquationSide;
use App\Enums\ResearchEquationStatus;
use App\Enums\ResearchSuit;
use App\Support\Equation;
use App\Support\EquationCard;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An equation somebody played, and what it paid (rulebook 3.2.1).
 *
 * Pending until its player takes the points, because the rulebook asks for
 * exactly that: "Scoring can and should be done while other players are taking
 * their turns". The cards were spent and the turn passed the moment it landed.
 *
 * The cards are held as they were played rather than as links. They are spent
 * immediately and gathered back into their decks when the next session opens,
 * so a link would stop meaning what it said - and what Control needs to see
 * later is the equation, not where its cards ended up.
 *
 * @property int $id
 * @property int $game_id
 * @property int|null $research_session_id
 * @property int|null $turn_id
 * @property int $corporation_id
 * @property ResearchEquationStatus $status
 * @property array<int, array{suit: string|null, value: int, from_hand: bool}> $left_cards
 * @property array<int, array{suit: string|null, value: int, from_hand: bool}> $right_cards
 * @property int $cards_per_side
 * @property int $left_sum
 * @property int $right_sum
 * @property bool $balanced
 * @property int $bonus
 * @property EquationSide|null $scored_side
 * @property ResearchSuit|null $scored_suit
 * @property array<string, int>|null $awards
 * @property int|null $scored_by_id
 * @property Carbon|null $scored_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Corporation $corporation
 */
#[Fillable([
    'game_id', 'research_session_id', 'turn_id', 'corporation_id', 'status',
    'left_cards', 'right_cards', 'cards_per_side',
    'left_sum', 'right_sum', 'balanced', 'bonus',
    'scored_side', 'scored_suit', 'awards', 'scored_by_id', 'scored_at', 'notes',
])]
class ResearchEquation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResearchEquationStatus::class,
            'left_cards' => 'array',
            'right_cards' => 'array',
            'balanced' => 'boolean',
            'scored_side' => EquationSide::class,
            'scored_suit' => ResearchSuit::class,
            'awards' => 'array',
            'scored_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Corporation, $this> */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    /** @return BelongsTo<ResearchSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ResearchSession::class, 'research_session_id');
    }

    /** @return BelongsTo<Turn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    /** @return BelongsTo<User, $this> */
    public function scoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === ResearchEquationStatus::Pending;
    }

    /**
     * The stored cards read back as the rules see them.
     *
     * Rebuilt from the snapshot rather than from the card rows, so scoring an
     * equation two turns after it was played still scores the equation that was
     * played.
     */
    public function toEquation(): Equation
    {
        return new Equation(
            $this->equationCards($this->left_cards),
            $this->equationCards($this->right_cards),
        );
    }

    /**
     * What one side reads as on a page: "3 Leaf + 4 Leaf".
     */
    public function describeSide(EquationSide $side): string
    {
        $cards = $side === EquationSide::Left ? $this->left_cards : $this->right_cards;

        return implode(' + ', array_map(
            fn (array $card): string => $card['suit'] === null
                ? sprintf('Wild %d', $card['value'])
                : sprintf('%d %s', $card['value'], ResearchSuit::from($card['suit'])->label()),
            $cards,
        ));
    }

    /**
     * @param  array<int, array{suit: string|null, value: int, from_hand: bool}>  $cards
     * @return array<int, EquationCard>
     */
    private function equationCards(array $cards): array
    {
        return array_map(fn (array $card): EquationCard => new EquationCard(
            $card['suit'] === null ? null : ResearchSuit::from($card['suit']),
            (int) $card['value'],
            (bool) $card['from_hand'],
        ), $cards);
    }
}
