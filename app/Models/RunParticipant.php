<?php

namespace App\Models;

use App\Enums\RunDeparture;
use Database\Factories\RunParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One Runner on one run, and whether they are still on it.
 *
 * @property int $id
 * @property int $run_id
 * @property int $character_id
 * @property int $position
 * @property Carbon|null $left_at
 * @property RunDeparture|null $left_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Run $run
 * @property-read Character $character
 */
#[Fillable(['run_id', 'character_id', 'position', 'left_at', 'left_reason'])]
class RunParticipant extends Model
{
    /** @use HasFactory<RunParticipantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'left_at' => 'datetime',
            'left_reason' => RunDeparture::class,
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

    /**
     * Whether this Runner is still on the run.
     */
    public function isActive(): bool
    {
        return $this->left_at === null;
    }
}
