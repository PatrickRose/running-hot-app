<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One vote on one agenda item, as handed to the Chair (3.1.2).
 *
 * @property int $id
 * @property int $council_agenda_item_id
 * @property string $voter_type
 * @property int $voter_id
 * @property int|null $character_id
 * @property int|null $user_id
 * @property Carbon $submitted_at
 * @property Carbon|null $returned_at
 * @property string|null $returned_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CouncilAgendaItem $item
 * @property-read Corporation|Character $voter
 * @property-read Collection<int, CouncilBallotAllocation> $allocations
 */
#[Fillable([
    'council_agenda_item_id', 'voter_type', 'voter_id', 'character_id', 'user_id',
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

    /**
     * Whose vote this is: a Corporation, or a character holding a seat of its
     * own (App\Models\Character::sitsOnCouncil()).
     *
     * @return MorphTo<Model, $this>
     */
    public function voter(): MorphTo
    {
        return $this->morphTo();
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
     * The votes this ballot carries in total.
     */
    public function weight(): int
    {
        return (int) $this->allocations->sum('political_will');
    }

    /**
     * What to call the voter on a page. Both kinds of voter have a name.
     */
    public function voterName(): string
    {
        return (string) $this->voter->getAttribute('name');
    }

    /**
     * A stable identity for one voter across both kinds, so a Corporation and
     * a character that happen to share a row id are never mistaken for each
     * other.
     */
    public function voterKey(): string
    {
        return $this->voter_type.':'.$this->voter_id;
    }

    public function wasReturned(): bool
    {
        return $this->returned_at !== null;
    }
}
