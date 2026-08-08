<?php

namespace App\Models;

use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use Database\Factories\PhaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $turn_id
 * @property PhaseType $type
 * @property int $sequence
 * @property PhaseStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $ended_at
 * @property int $version
 * @property Carbon|null $upkeep_applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Turn $turn
 */
#[Fillable([
    'turn_id', 'type', 'sequence', 'status',
    'starts_at', 'ends_at', 'paused_at', 'ended_at',
    'version', 'upkeep_applied_at',
])]
class Phase extends Model
{
    /** @use HasFactory<PhaseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PhaseType::class,
            'status' => PhaseStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'paused_at' => 'datetime',
            'ended_at' => 'datetime',
            'upkeep_applied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Turn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    public function game(): Game
    {
        return $this->turn->game;
    }

    /**
     * Seconds left on the clock. While paused this freezes at the value it held
     * when the pause began, because resuming pushes ends_at out by the elapsed
     * pause rather than tracking pause time separately.
     */
    public function remainingSeconds(): int
    {
        if ($this->ends_at === null) {
            return 0;
        }

        $reference = $this->status === PhaseStatus::Paused && $this->paused_at !== null
            ? $this->paused_at
            : Carbon::now();

        return max(0, (int) ceil($reference->diffInSeconds($this->ends_at, false)));
    }

    public function isOverdue(): bool
    {
        return $this->status === PhaseStatus::Running
            && $this->ends_at !== null
            && Carbon::now()->greaterThanOrEqualTo($this->ends_at);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [PhaseStatus::Running, PhaseStatus::Paused], true);
    }

    /**
     * @param  Builder<Phase>  $query
     * @return Builder<Phase>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [PhaseStatus::Running, PhaseStatus::Paused]);
    }
}
