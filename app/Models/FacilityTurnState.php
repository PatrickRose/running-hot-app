<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What one Facility is doing during one turn: where Security is Directing, the
 * budget placed on it, and how many cards have been pulled out of it.
 *
 * @property int $id
 * @property int $turn_id
 * @property int $facility_id
 * @property bool $security_directed
 * @property int $security_budget
 * @property int $security_budget_spent
 * @property Carbon|null $budget_returned_at
 * @property int $cards_removed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Facility $facility
 * @property-read Turn $turn
 */
#[Fillable([
    'turn_id', 'facility_id', 'security_directed',
    'security_budget', 'security_budget_spent', 'budget_returned_at', 'cards_removed',
])]
class FacilityTurnState extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'security_directed' => 'boolean',
            'budget_returned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Turn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Budget still sitting on the Facility, which returns to the Corporation at
     * the end of the Action phase (rulebook 3.3.5).
     */
    public function unspentBudget(): int
    {
        return max(0, $this->security_budget - $this->security_budget_spent);
    }
}
