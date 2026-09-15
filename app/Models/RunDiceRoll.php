<?php

namespace App\Models;

use App\Enums\DiceRoller;
use Database\Factories\RunDiceRollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One roll of the dice, and every face it landed on.
 *
 * The evidence behind a result. A player who can see the faces will accept
 * losing a check; a player told only that they failed will not.
 *
 * @property int $id
 * @property int $run_id
 * @property int|null $run_event_id
 * @property DiceRoller $roller
 * @property int|null $character_id
 * @property int $pool
 * @property int $die_faces
 * @property int $threshold
 * @property array<int, int> $faces
 * @property int $successes
 * @property string $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Run $run
 * @property-read RunEvent|null $event
 * @property-read Character|null $character
 */
#[Fillable([
    'run_id',
    'run_event_id',
    'roller',
    'character_id',
    'pool',
    'die_faces',
    'threshold',
    'faces',
    'successes',
    'reason',
])]
class RunDiceRoll extends Model
{
    /** @use HasFactory<RunDiceRollFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roller' => DiceRoller::class,
            'faces' => 'array',
        ];
    }

    /** @return BelongsTo<Run, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /** @return BelongsTo<RunEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(RunEvent::class, 'run_event_id');
    }

    /**
     * The Runner this roll belongs to, where it belongs to one.
     *
     * Null for the Runners' challenge pool, which is one roll assembled from
     * the whole group's skills rather than any single person's.
     *
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * The dice as they would be read out: "6d8, 5+ — 3,8,1,5,2,4 (3 successes)".
     */
    public function readout(): string
    {
        return sprintf(
            '%dd%d, %d+ — %s (%d success%s)',
            $this->pool,
            $this->die_faces,
            $this->threshold,
            $this->faces === [] ? 'no dice' : implode(',', $this->faces),
            $this->successes,
            $this->successes === 1 ? '' : 'es',
        );
    }
}
