<?php

namespace App\Models;

use Database\Factories\FacilityTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A kind of Facility a Corporation can build (rulebook 3.3.1).
 *
 * A row rather than an enum case: the rulebook says more Facility types may be
 * researched during the game, so Control adds them mid-game. The mechanical
 * effects travel on the row for the same reason - a type Control invents has to
 * be able to grant slots or storage without new code.
 *
 * @property int $id
 * @property int $game_id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property int $protection_slots_granted
 * @property int $technology_capacity_granted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_id', 'key', 'name', 'description',
    'protection_slots_granted', 'technology_capacity_granted',
])]
class FacilityType extends Model
{
    /** @use HasFactory<FacilityTypeFactory> */
    use HasFactory;

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<Facility, $this> */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }
}
