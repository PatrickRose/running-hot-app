<?php

namespace App\Models;

use Database\Factories\FacilityCardActivationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Whether a Protection Card is warmed up this turn, and how hard it has been made.
 *
 * The state that makes going second worse. A card activated by the first group
 * to reach it is still Active for the second, and a Boost Security paid for
 * lasts the rest of the phase (rulebook 3.4.2) - so this hangs off the turn
 * rather than off any one run.
 *
 * @property int $id
 * @property int $turn_id
 * @property int $facility_protection_card_id
 * @property Carbon|null $activated_at
 * @property int $activation_cost
 * @property int $boosts
 * @property int $boost_credits_spent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Turn $turn
 * @property-read FacilityProtectionCard $card
 */
#[Fillable([
    'turn_id',
    'facility_protection_card_id',
    'activated_at',
    'activation_cost',
    'boosts',
    'boost_credits_spent',
])]
class FacilityCardActivation extends Model
{
    /** @use HasFactory<FacilityCardActivationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Turn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    /** @return BelongsTo<FacilityProtectionCard, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(FacilityProtectionCard::class, 'facility_protection_card_id');
    }

    /**
     * Whether the card is Active.
     *
     * A row can exist without the card being Active: Security who is not
     * Directing here attempts to activate and fails to cover the cost from the
     * budget, which leaves the card Inactive and skipped (3.4.2). That attempt
     * is worth recording, so absence of a row and an unactivated row are
     * different things.
     */
    public function isActive(): bool
    {
        return $this->activated_at !== null;
    }

    /**
     * What the next Boost on this card would cost.
     *
     * Cumulative: the first Boost of each card costs 1 Credit, the second 2,
     * the third 3 (3.4.2). Priced per card rather than per run, because the
     * Boosts already paid for stay on the card for the rest of the phase.
     */
    public function nextBoostCost(): int
    {
        return $this->boosts + 1;
    }
}
