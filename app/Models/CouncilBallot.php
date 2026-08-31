<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One Corporation's vote on one agenda item, as handed to the Chair (3.1.2).
 *
 * @property int $id
 * @property int $council_agenda_item_id
 * @property int $corporation_id
 * @property int|null $character_id
 * @property int|null $user_id
 * @property Carbon $submitted_at
 * @property Carbon|null $returned_at
 * @property string|null $returned_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CouncilAgendaItem $item
 * @property-read Corporation $corporation
 * @property-read Collection<int, CouncilBallotAllocation> $allocations
 */
#[Fillable([
    'council_agenda_item_id', 'corporation_id', 'character_id', 'user_id',
    'submitted_at', 'returned_at', 'returned_reason',
])]
class CouncilBallot extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CouncilAgendaItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(CouncilAgendaItem::class, 'council_agenda_item_id');
    }

    /** @return BelongsTo<Corporation, $this> */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** @return HasMany<CouncilBallotAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(CouncilBallotAllocation::class);
    }

    /**
     * The Political Will this ballot carries in total.
     */
    public function weight(): int
    {
        return (int) $this->allocations->sum('political_will');
    }

    public function wasReturned(): bool
    {
        return $this->returned_at !== null;
    }
}
