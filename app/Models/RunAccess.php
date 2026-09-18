<?php

namespace App\Models;

use App\Enums\RunAccessKind;
use App\Enums\TechnologyAccessAction;
use Database\Factories\RunAccessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One access a Runner spent inside a Facility they broke into (3.4.3).
 *
 * @property int $id
 * @property int $run_id
 * @property int $character_id
 * @property RunAccessKind $kind
 * @property int|null $technology_holding_id
 * @property TechnologyAccessAction|null $action
 * @property int|null $successes
 * @property string|null $outcome
 * @property int|null $discount_percent
 * @property int|null $credits
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Run $run
 * @property-read Character $character
 * @property-read TechnologyHolding|null $technologyHolding
 */
#[Fillable([
    'run_id',
    'character_id',
    'kind',
    'technology_holding_id',
    'action',
    'successes',
    'outcome',
    'discount_percent',
    'credits',
    'notes',
])]
class RunAccess extends Model
{
    /** @use HasFactory<RunAccessFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RunAccessKind::class,
            'action' => TechnologyAccessAction::class,
        ];
    }

    /** @return BelongsTo<Run, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** @return BelongsTo<TechnologyHolding, $this> */
    public function technologyHolding(): BelongsTo
    {
        return $this->belongsTo(TechnologyHolding::class);
    }
}
