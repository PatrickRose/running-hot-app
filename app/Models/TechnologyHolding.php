<?php

namespace App\Models;

use App\Enums\ResearchSuit;
use App\Enums\TechnologyHoldingStatus;
use App\Enums\TechnologyOrigin;
use Database\Factories\TechnologyHoldingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A technology card a Corporation actually has (rulebook 3.2.2).
 *
 * technology_types is the tree - what could be researched. This is what has
 * been, or what has arrived some other way: a copy Research Control made for a
 * partner (3.2.5), or one a Run brought back (3.2.6). Those are Claimed rather
 * than Researched - they take up their Facility's storage from the moment they
 * are stored (footnote 8 to 3.2.6) and they are worth a discount, but they do
 * nothing until the Corporation pays.
 *
 * @property int $id
 * @property int $game_id
 * @property int $corporation_id
 * @property int $technology_type_id
 * @property int|null $facility_id
 * @property TechnologyHoldingStatus $status
 * @property TechnologyOrigin $origin
 * @property int $discount_percent
 * @property int $paid_cog
 * @property int $paid_brain
 * @property int $paid_leaf
 * @property int $paid_maths
 * @property Carbon|null $researched_at
 * @property Carbon|null $destroyed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Corporation $corporation
 * @property-read TechnologyType $technologyType
 * @property-read Facility|null $facility
 */
#[Fillable([
    'game_id', 'corporation_id', 'technology_type_id', 'facility_id',
    'status', 'origin', 'discount_percent',
    'paid_cog', 'paid_brain', 'paid_leaf', 'paid_maths',
    'researched_at', 'destroyed_at', 'notes',
])]
class TechnologyHolding extends Model
{
    /** @use HasFactory<TechnologyHoldingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TechnologyHoldingStatus::class,
            'origin' => TechnologyOrigin::class,
            'researched_at' => 'datetime',
            'destroyed_at' => 'datetime',
        ];
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

    /** @return BelongsTo<TechnologyType, $this> */
    public function technologyType(): BelongsTo
    {
        return $this->belongsTo(TechnologyType::class);
    }

    /**
     * Where the card is stored. Null only while Control is placing it.
     *
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * What was paid for this, suit by suit.
     *
     * @return array<string, int>
     */
    public function paid(): array
    {
        $paid = [];

        foreach (ResearchSuit::all() as $suit) {
            $paid[$suit->value] = (int) $this->{'paid_'.$suit->value};
        }

        return $paid;
    }

    public function isResearched(): bool
    {
        return $this->status === TechnologyHoldingStatus::Researched;
    }

    /**
     * Cards that are still in the game: everything not destroyed by a Run.
     *
     * @param  Builder<TechnologyHolding>  $query
     * @return Builder<TechnologyHolding>
     */
    public function scopeStanding(Builder $query): Builder
    {
        return $query->where('status', '!=', TechnologyHoldingStatus::Destroyed);
    }

    /**
     * @param  Builder<TechnologyHolding>  $query
     * @return Builder<TechnologyHolding>
     */
    public function scopeResearched(Builder $query): Builder
    {
        return $query->where('status', TechnologyHoldingStatus::Researched);
    }
}
