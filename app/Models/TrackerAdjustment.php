<?php

namespace App\Models;

use App\Enums\Tracker;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An audit record of a single tracker movement.
 *
 * @property int $id
 * @property int $game_id
 * @property int|null $phase_id
 * @property int|null $actor_id
 * @property string $subject_type
 * @property int $subject_id
 * @property Tracker $tracker
 * @property int $value_before
 * @property int $value_after
 * @property int $delta
 * @property string|null $reason
 * @property bool $automated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_id', 'phase_id', 'actor_id', 'subject_type', 'subject_id',
    'tracker', 'value_before', 'value_after', 'delta', 'reason', 'automated',
])]
class TrackerAdjustment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tracker' => Tracker::class,
            'automated' => 'boolean',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Phase, $this> */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
