<?php

namespace App\Models;

use Database\Factories\GangFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_id
 * @property string $name
 * @property-read int $notoriety summed from the gang's members; not a column
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['game_id', 'name'])]
class Gang extends Model
{
    /** @use HasFactory<GangFactory> */
    use HasFactory;

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<Character, $this> */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * Historical only.
     *
     * Notoriety was a tracker on the gang before it moved to the character, so
     * a game that ran under the old reading still has rows pointing here. They
     * are kept - the ledger is the answer to "why did that number change?" and
     * deleting the question's history to tidy a relation would be the wrong
     * trade - but nothing writes a new one.
     *
     * @return MorphMany<TrackerAdjustment, $this>
     */
    public function trackerAdjustments(): MorphMany
    {
        return $this->morphMany(TrackerAdjustment::class, 'subject');
    }

    /**
     * How well known the gang is: the total of what its members have earned.
     *
     * Derived rather than stored, which is the same choice Facility slots and
     * technology storage make. A column kept in step with the rows it sums is a
     * second source of truth, and the one that drifts is always the summary.
     *
     * Reads a `withSum('characters', 'notoriety')` off the query where one has
     * been eager loaded, and falls back to asking. Anything listing gangs
     * should load it - GamePresenter and RunEngine both do - because the
     * fallback is a query per gang.
     *
     * @return Attribute<int, never> read-only: there is no column to set.
     */
    protected function notoriety(): Attribute
    {
        return Attribute::get(function (): int {
            $summed = $this->attributes['characters_sum_notoriety'] ?? null;

            return $summed === null
                ? (int) $this->characters()->sum('notoriety')
                : (int) $summed;
        });
    }
}
