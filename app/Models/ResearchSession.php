<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One sitting of the research game (rulebook 3.2.1).
 *
 * Opened when Research Control deals, closed when the phase end is called. It
 * holds the turn order and whose turn it is, and nothing else: the cards live
 * in research_cards and the plays in research_equations.
 *
 * @property int $id
 * @property int $game_id
 * @property int|null $turn_id
 * @property int|null $current_order
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['game_id', 'turn_id', 'current_order', 'opened_at', 'closed_at'])]
class ResearchSession extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Turn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    /** @return HasMany<ResearchSeat, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(ResearchSeat::class);
    }

    /** @return HasMany<ResearchEquation, $this> */
    public function equations(): HasMany
    {
        return $this->hasMany(ResearchEquation::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * The seat whose turn it is, or null when nobody is still playing.
     */
    public function currentSeat(): ?ResearchSeat
    {
        if ($this->current_order === null) {
            return null;
        }

        /** @var ResearchSeat|null */
        return $this->seats()->where('order', $this->current_order)->first();
    }

    /**
     * Whether it is this Corporation's turn to make an equation.
     */
    public function isTurnOf(Corporation $corporation): bool
    {
        return $this->currentSeat()?->corporation_id === $corporation->id;
    }
}
