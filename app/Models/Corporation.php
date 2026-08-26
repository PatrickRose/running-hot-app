<?php

namespace App\Models;

use Database\Factories\CorporationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_id
 * @property string $name
 * @property int $income
 * @property int $political_will
 * @property int $credits
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['game_id', 'name', 'income', 'political_will', 'credits'])]
class Corporation extends Model
{
    /** @use HasFactory<CorporationFactory> */
    use HasFactory;

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<Character, $this> */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /** @return HasMany<Facility, $this> */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * The Protection Cards this Corporation has in hand, waiting to be
     * installed. An installed copy is not among them.
     *
     * @return HasMany<ProtectionCardHolding, $this>
     */
    public function protectionCardHoldings(): HasMany
    {
        return $this->hasMany(ProtectionCardHolding::class);
    }

    /**
     * The technologies on this Corporation's own tech tree, not counting the
     * ones common to every Corporation.
     *
     * @return HasMany<TechnologyType, $this>
     */
    public function technologyTypes(): HasMany
    {
        return $this->hasMany(TechnologyType::class);
    }

    /** @return MorphMany<TrackerAdjustment, $this> */
    public function trackerAdjustments(): MorphMany
    {
        return $this->morphMany(TrackerAdjustment::class, 'subject');
    }
}
