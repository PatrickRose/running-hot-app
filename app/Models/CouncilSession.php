<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One turn's sitting of the Council (rulebook 3.1).
 *
 * @property int $id
 * @property int $turn_id
 * @property int|null $chair_corporation_id
 * @property Carbon|null $recess_at
 * @property Carbon|null $handed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Turn $turn
 * @property-read Corporation|null $chair
 * @property-read Collection<int, CouncilAgendaItem> $items
 * @property-read Collection<int, CouncilSeat> $seats
 */
#[Fillable(['turn_id', 'chair_corporation_id', 'recess_at', 'handed_at'])]
class CouncilSession extends Model
{
    /**
     * How many cards Control hands the Chair, and how many of them the Chair
     * keeps (rulebook 3.1.1).
     *
     * The first is what the Control panel offers rather than a limit: Control
     * picks the cards, so it may hand over a different number and the Chair
     * then keeps two of whatever arrives.
     */
    public const CARDS_HANDED = 3;

    public const CARDS_KEPT = 2;

    /**
     * The most agenda items one turn may vote on (rulebook 3.1.3).
     */
    public const MAXIMUM_ITEMS = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recess_at' => 'datetime',
            'handed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Turn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    /** @return BelongsTo<Corporation, $this> */
    public function chair(): BelongsTo
    {
        return $this->belongsTo(Corporation::class, 'chair_corporation_id');
    }

    /** @return HasMany<CouncilAgendaItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CouncilAgendaItem::class);
    }

    /** @return HasMany<CouncilSeat, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(CouncilSeat::class);
    }

    /**
     * Seconds until the Council goes into recess, derived from the stored
     * moment for the reason the phase clock is: the server owns the clock and
     * the browser only fills in the gap between polls.
     *
     * A paused phase passes its own paused_at as the reference, exactly as the
     * phase clock does, so a stopped game stops the Council rising too. The
     * stored moment is put right when the phase resumes.
     *
     * Null when no recess has been set at all, which is not the same as zero.
     */
    public function recessSecondsRemaining(?CarbonInterface $reference = null): ?int
    {
        if ($this->recess_at === null) {
            return null;
        }

        return max(0, (int) ceil(($reference ?? Carbon::now())->diffInSeconds($this->recess_at, false)));
    }

    public function isInRecess(?CarbonInterface $reference = null): bool
    {
        return $this->recess_at !== null && $this->recessSecondsRemaining($reference) === 0;
    }

    public function hasHandedOver(): bool
    {
        return $this->handed_at !== null;
    }
}
