<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How many copies of one Protection Card a Corporation has in hand.
 *
 * The briefings give each Corporation counts, and the count is what stops the
 * same card defending every Facility: one copy per Facility (rulebook 3.3.4)
 * means four copies of Security Team can cover four Facilities and no more.
 *
 * Copies here are uninstalled. Installing moves one out of this count and into
 * facility_protection_cards; removing an installed card moves it back.
 * App\Services\FacilityDefenceService owns both halves of that move, so nothing
 * else may write this.
 *
 * @property int $id
 * @property int $corporation_id
 * @property int $protection_card_type_id
 * @property int $copies
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Corporation $corporation
 * @property-read ProtectionCardType $cardType
 */
#[Fillable(['corporation_id', 'protection_card_type_id', 'copies'])]
class ProtectionCardHolding extends Model
{
    /** @return BelongsTo<Corporation, $this> */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    /** @return BelongsTo<ProtectionCardType, $this> */
    public function cardType(): BelongsTo
    {
        return $this->belongsTo(ProtectionCardType::class, 'protection_card_type_id');
    }
}
