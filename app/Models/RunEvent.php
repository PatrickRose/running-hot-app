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
