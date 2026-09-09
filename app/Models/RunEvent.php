<?php

namespace App\Models;

use App\Enums\RunStep;
use Database\Factories\RunEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One thing that happened during a run.
 *
 * Append-only. Nothing updates or deletes one of these: a mistake is corrected
 * by Control acting again, and that correction is itself an event.
 *
 * @property int $id
 * @property int $run_id
 * @property int $pass
 * @property RunStep $step
 * @property string $type
 * @property int|null $actor_user_id
 * @property int|null $character_id
 * @property int|null $facility_protection_card_id
 * @property array<string, mixed>|null $payload
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Run $run
 * @property-read User|null $actor
 * @property-read Character|null $character
 * @property-read FacilityProtectionCard|null $card
 * @property-read Collection<int, RunDiceRoll> $diceRolls
 */
#[Fillable([
    'run_id',
    'pass',
    'step',
    'type',
    'actor_user_id',
    'character_id',
    'facility_protection_card_id',
    'payload',
    'description',
])]
class RunEvent extends Model
{
    /** @use HasFactory<RunEventFactory> */
    use HasFactory;

    /**
     * The kinds of thing that happen during a run.
     *
     * Constants rather than an enum because the column is deliberately a free
     * string: Control correcting something mid-game may need to describe an act
     * the application has no name for, and a cast would refuse to store it.
     * These are the names the engine writes, and the ones anything reading the
     * log back can rely on.
     */
    public const TYPE_SUBMITTED = 'submitted';

    public const TYPE_ORDERED = 'ordered';

    public const TYPE_BEGAN = 'began';

    public const TYPE_ACTIVATED = 'activated';

    /** Security attempted to activate and could not cover the cost (3.4.2). */
    public const TYPE_ACTIVATION_FAILED = 'activation_failed';

    /** Security chose not to activate, which only a Directing player may do. */
    public const TYPE_ACTIVATION_DECLINED = 'activation_declined';

    public const TYPE_BOOSTED = 'boosted';

    public const TYPE_CHARGED = 'charged';

    public const TYPE_CHALLENGE = 'challenge';

    public const TYPE_CONSEQUENCE = 'consequence';

    public const TYPE_IGNORED_END_THE_RUN = 'ignored_end_the_run';

    public const TYPE_LEFT = 'left';

    public const TYPE_INCAPACITATED = 'incapacitated';

    public const TYPE_LEADER_CHANGED = 'leader_changed';

    public const TYPE_CARD_PASSED = 'card_passed';

    /** A Retry consumed at the Breather, sending the Runners at the same card again. */
    public const TYPE_RETRIED = 'retried';

    public const TYPE_SUCCEEDED = 'succeeded';

    public const TYPE_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => RunStep::class,
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Run, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }

    /**
     * Who did it, or null for something the engine did on its own.
     *
     * A User rather than a Character because the question this answers is "was
     * that the player or was that Control?", and Control has no Character.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** @return BelongsTo<FacilityProtectionCard, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(FacilityProtectionCard::class, 'facility_protection_card_id');
    }

    /** @return HasMany<RunDiceRoll, $this> */
    public function diceRolls(): HasMany
    {
        return $this->hasMany(RunDiceRoll::class)->orderBy('id');
    }
}
