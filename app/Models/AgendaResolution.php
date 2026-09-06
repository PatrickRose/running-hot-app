<?php

namespace App\Models;

use App\Enums\ResolutionAmendment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One option on an agenda card, and any amendment the Chair has proposed to it
 * (rulebook 3.1.4).
 *
 * @property int $id
 * @property int $agenda_card_id
 * @property int $position
 * @property string $text
 * @property ResolutionAmendment|null $pending_amendment
 * @property string|null $pending_text
 * @property int|null $proposed_by_character_id
 * @property Carbon|null $removed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgendaCard $card
 */
#[Fillable([
    'agenda_card_id', 'position', 'text',
    'pending_amendment', 'pending_text', 'proposed_by_character_id', 'removed_at',
])]
class AgendaResolution extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pending_amendment' => ResolutionAmendment::class,
            'removed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AgendaCard, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(AgendaCard::class, 'agenda_card_id');
    }

    /** @return HasMany<CouncilBallotAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(CouncilBallotAllocation::class);
    }

    /** @return BelongsTo<Character, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'proposed_by_character_id');
    }

    /**
     * Whether a CEO may put Political Will behind this.
     *
     * An addition Control has not signed off is not yet an option, and a
     * removal that has been signed off is no longer one. A removal still
     * awaiting sign-off is: the card reads as it stands until Control agrees.
     */
    public function isVotable(): bool
    {
        return $this->removed_at === null
            && $this->pending_amendment !== ResolutionAmendment::Addition;
    }

    /**
     * The words as they would read if Control signed the amendment off.
     */
    public function amendedText(): string
    {
        return $this->pending_text ?? $this->text;
    }
}
