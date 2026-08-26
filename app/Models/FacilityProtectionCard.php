<?php

namespace App\Models;

use App\Enums\ProtectionKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Protection Card installed in a Facility, and where it sits in the stack.
 *
 * Position 1 is the card Runners meet first, so the stack reads in encounter
 * order (rulebook 3.3.4). App\Services\FacilityDefenceService owns every write
 * to it and keeps each (facility, kind) stack numbered 1..n.
 *
 * @property int $id
 * @property int $facility_id
 * @property int $protection_card_type_id
 * @property ProtectionKind $kind
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Facility $facility
 * @property-read ProtectionCardType $cardType
 */
#[Fillable(['facility_id', 'protection_card_type_id', 'kind', 'position'])]
class FacilityProtectionCard extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProtectionKind::class,
        ];
    }

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /** @return BelongsTo<ProtectionCardType, $this> */
    public function cardType(): BelongsTo
    {
        return $this->belongsTo(ProtectionCardType::class, 'protection_card_type_id');
    }
}
