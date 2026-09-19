<?php

namespace App\Models;

use App\Enums\RunDeparture;
use App\Enums\RunnerSkill;
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
 * @property int $brawn_adjustment
 * @property int $hack_adjustment
 * @property Carbon|null $left_at
 * @property RunDeparture|null $left_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Run $run
 * @property-read Character $character
 */
#[Fillable([
    'run_id', 'character_id', 'position', 'left_at', 'left_reason',
    'brawn_adjustment', 'hack_adjustment',
])]
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

    /**
     * What this Runner's skill is worth on this run.
     *
     * Their own score plus whatever their Equipment is doing to it, which is
     * the number every pool has to be built from - the challenge roll, the
     * access roll, and the readout the screen quotes before either. Three call
     * sites reading `$character->brawn` directly is three chances for the
     * quoted pool and the rolled one to disagree about a Shiv.
     *
     * Floored at zero: a Runner talked into a card that costs them more skill
     * than they have contributes nothing rather than taking dice off the rest
     * of the group.
     *
     * @return int<0, max>
     */
    public function skill(RunnerSkill $skill): int
    {
        $own = (int) $this->character->getAttribute($skill->column());

        return max(0, $own + $this->adjustmentTo($skill));
    }

    /**
     * Brawn and Hack added together, which is what an access rolls (3.4.3).
     *
     * Each half floored on its own before they are added, so a Runner cannot
     * carry a penalty in one skill across into the other.
     *
     * @return int<0, max>
     */
    public function combinedSkill(): int
    {
        return $this->skill(RunnerSkill::Brawn) + $this->skill(RunnerSkill::Hack);
    }

    public function adjustmentTo(RunnerSkill $skill): int
    {
        return match ($skill) {
            RunnerSkill::Brawn => $this->brawn_adjustment,
            RunnerSkill::Hack => $this->hack_adjustment,
        };
    }

    /**
     * Whether this Runner is Wounded, which decides their die and their share.
     *
     * Read through here so a pool never reaches past the participant to the
     * character for half its inputs and not the other half.
     */
    public function isWounded(): bool
    {
        return $this->character->wounds > 0;
    }
}
