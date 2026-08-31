<?php

namespace App\Models;

use App\Enums\ResearchSuit;
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
 * @property int $cog_points
 * @property int $brain_points
 * @property int $leaf_points
 * @property int $maths_points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_id', 'name', 'income', 'political_will', 'credits',
    'cog_points', 'brain_points', 'leaf_points', 'maths_points',
])]
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

    /**
     * This Corporation's private research deck, in every zone (rulebook 3.2.1).
     *
     * The public deck is not among them: it belongs to the game.
     *
     * @return HasMany<ResearchCard, $this>
     */
    public function researchCards(): HasMany
    {
        return $this->hasMany(ResearchCard::class);
    }

    /**
     * The technology cards this Corporation has, researched or merely claimed
     * (rulebook 3.2.2).
     *
     * @return HasMany<TechnologyHolding, $this>
     */
    public function technologyHoldings(): HasMany
    {
        return $this->hasMany(TechnologyHolding::class);
    }

    /**
     * The equations this Corporation has played (rulebook 3.2.1).
     *
     * @return HasMany<ResearchEquation, $this>
     */
    public function researchEquations(): HasMany
    {
        return $this->hasMany(ResearchEquation::class);
    }

    /**
     * Research Points banked, by suit (rulebook 3.2.1).
     *
     * Given as all four rather than the ones that are not zero, because a
     * caller showing these shows four columns. Every one of them moves through
     * App\Services\TrackerService.
     *
     * @return array<string, int>
     */
    public function researchPoints(): array
    {
        $points = [];

        foreach (ResearchSuit::all() as $suit) {
            $points[$suit->value] = (int) $this->{$suit->pointsColumn()};
        }

        return $points;
    }

    /**
     * What this Corporation holds in one suit, read from the database rather
     * than off the attribute.
     *
     * The distinction matters wherever affordability is checked. A service may
     * be holding a model that was loaded before the last tracker write, and
     * TrackerService clamps Research Points at zero - so a stale check would
     * not refuse an overspend, it would quietly take the pile down to nothing.
     */
    public function researchPointsIn(ResearchSuit $suit): int
    {
        return (int) static::query()->whereKey($this->getKey())->value($suit->pointsColumn());
    }

    /** @return MorphMany<TrackerAdjustment, $this> */
    public function trackerAdjustments(): MorphMany
    {
        return $this->morphMany(TrackerAdjustment::class, 'subject');
    }
}
