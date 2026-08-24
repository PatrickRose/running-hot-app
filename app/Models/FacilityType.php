<?php

namespace App\Models;

use App\Enums\FacilityGrantScaling;
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
 * @property string|null $access_effect
 * @property int $build_cost
 * @property int $physical_slots_granted
 * @property int $cyber_slots_granted
 * @property int $technology_capacity_granted
 * @property int $card_move_discount
 * @property FacilityGrantScaling $grant_scaling
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_id', 'key', 'name', 'description', 'access_effect', 'build_cost',
    'physical_slots_granted', 'cyber_slots_granted',
    'technology_capacity_granted', 'card_move_discount', 'grant_scaling',
])]
class FacilityType extends Model
{
    /** @use HasFactory<FacilityTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grant_scaling' => FacilityGrantScaling::class,
        ];
    }

    /**
     * What one of this type's effects is worth to a Corporation owning $count
     * open Facilities of the type.
     *
     * The column is named rather than switched on so that a type Control
     * invents scales the same way as one from the type sheet.
     */
    public function grantTotal(string $column, int $count): int
    {
        return $this->grant_scaling->total((int) $this->getAttribute($column), $count);
    }

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
