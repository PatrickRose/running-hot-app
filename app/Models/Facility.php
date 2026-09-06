<?php

namespace App\Models;

use App\Jobs\SyncFacilityChannels;
use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One of a Corporation's Facilities (rulebook 3.3).
 *
 * @property int $id
 * @property int $game_id
 * @property int $corporation_id
 * @property int $facility_type_id
 * @property string $name
 * @property int $available_from_turn
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Corporation $corporation
 * @property-read FacilityType $facilityType
 */
#[Fillable([
    'game_id', 'corporation_id', 'facility_type_id',
    'name', 'available_from_turn', 'notes',
])]
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory;

    /**
     * The turn a game that has not started yet is treated as being on.
     */
    public const FIRST_TURN = 1;

    /**
     * Give a new Facility its Discord channels.
     *
     * A hook rather than a call in the requisition, so that every route into a
     * Facility - a requisition, Control building one by hand, a seeder - ends
     * up with somewhere to run against it. Queued after commit, because the
     * job reads the Facility back and must not race the transaction that made
     * it; and it does nothing at all for a game with no Discord server, which
     * is every game at the moment it is created.
     */
    protected static function booted(): void
    {
        static::created(function (Facility $facility): void {
            SyncFacilityChannels::dispatch($facility->id)->afterCommit();
        });
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Corporation, $this> */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    /** @return BelongsTo<FacilityType, $this> */
    public function facilityType(): BelongsTo
    {
        return $this->belongsTo(FacilityType::class);
    }

    /** @return HasMany<FacilityProtectionCard, $this> */
    public function protectionCards(): HasMany
    {
        return $this->hasMany(FacilityProtectionCard::class);
    }

    /** @return HasMany<Run, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    /** @return HasMany<FacilityTurnState, $this> */
    public function turnStates(): HasMany
    {
        return $this->hasMany(FacilityTurnState::class);
    }

    /**
     * Whether the Facility has finished building by the given turn.
     *
     * A Facility requisitioned during turn N's Setup phase opens during turn
     * N+1's, so availability is a comparison against the clock rather than a
     * stored flag somebody has to remember to flip.
     *
     * A game that has not started yet counts as turn 1, so the Facilities
     * Control sets up before kick-off read as open rather than as building.
     */
    public function isAvailableOnTurn(?int $turnNumber): bool
    {
        return ($turnNumber ?? self::FIRST_TURN) >= $this->available_from_turn;
    }

    /**
     * The turn state for the given turn, creating it if this is the first thing
     * to touch this Facility this turn.
     */
    public function stateForTurn(Turn $turn): FacilityTurnState
    {
        /** @var FacilityTurnState */
        return $this->turnStates()->firstOrCreate(['turn_id' => $turn->id]);
    }

    /**
     * @param  Builder<Facility>  $query
     * @return Builder<Facility>
     */
    public function scopeAvailableOnTurn(Builder $query, ?int $turnNumber): Builder
    {
        return $query->where('available_from_turn', '<=', $turnNumber ?? self::FIRST_TURN);
    }
}
