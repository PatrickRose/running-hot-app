<?php

namespace App\Models;

use Database\Factories\EquipmentHoldingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * How many copies of one Equipment card a Runner is carrying.
 *
 * Per Character rather than per gang, which is what the rulebook says twice:
 * 3.4.1 caps *you* at three equipped permanent items and one copy of each card
 * by title, and 3.4.2 hands *your* permanent Equipment to the Security player
 * when you are incapacitated. Neither sentence means anything about a shared
 * pile.
 *
 * Copies here are in hand. Playing a This-run or Single-use card on a run
 * spends one, because both are "returned to Control" afterwards; a Permanent
 * item is not spent, because it comes home with its owner unless they are
 * carried out. App\Services\RunEngine owns every one of those moves, so nothing
 * else may write this - the same rule facility holdings live under.
 *
 * @property int $id
 * @property int $character_id
 * @property int $equipment_card_type_id
 * @property int $copies
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Character $character
 * @property-read EquipmentCardType $cardType
 */
#[Fillable(['character_id', 'equipment_card_type_id', 'copies'])]
class EquipmentHolding extends Model
{
    /** @use HasFactory<EquipmentHoldingFactory> */
    use HasFactory;

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** @return BelongsTo<EquipmentCardType, $this> */
    public function cardType(): BelongsTo
    {
        return $this->belongsTo(EquipmentCardType::class, 'equipment_card_type_id');
    }
}
