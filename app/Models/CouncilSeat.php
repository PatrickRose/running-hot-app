<?php

namespace App\Models;

use App\Enums\CouncilAttendance;
use App\Enums\PhaseType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * A Corporation's seat at one sitting of the Council, and whether anybody took
 * it (rulebook 3.1.2).
 *
 * @property int $id
 * @property int $council_session_id
 * @property int $corporation_id
 * @property CouncilAttendance $setup_attendance
 * @property CouncilAttendance $action_attendance
 * @property Carbon|null $setup_penalty_applied_at
 * @property Carbon|null $action_penalty_applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CouncilSession $session
 * @property-read Corporation $corporation
 */
#[Fillable([
    'council_session_id', 'corporation_id',
    'setup_attendance', 'action_attendance',
    'setup_penalty_applied_at', 'action_penalty_applied_at',
])]
class CouncilSeat extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'setup_attendance' => CouncilAttendance::class,
            'action_attendance' => CouncilAttendance::class,
            'setup_penalty_applied_at' => 'datetime',
            'action_penalty_applied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CouncilSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CouncilSession::class, 'council_session_id');
    }

    /** @return BelongsTo<Corporation, $this> */
    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    /**
     * Attendance is judged for each half of the Council separately, because the
     * rulebook penalises failing to appear for either.
     */
    public function attendanceFor(PhaseType $phase): CouncilAttendance
    {
        return $this->getAttribute(self::columnFor($phase, 'attendance'));
    }

    public function penaltyAppliedFor(PhaseType $phase): ?CarbonInterface
    {
        return $this->getAttribute(self::columnFor($phase, 'penalty_applied_at'));
    }

    /**
     * Team Time has no Council in it, so it has no seat to miss.
     */
    public static function columnFor(PhaseType $phase, string $suffix): string
    {
        return match ($phase) {
            PhaseType::Setup => 'setup_'.$suffix,
            PhaseType::Action => 'action_'.$suffix,
            default => throw new InvalidArgumentException('The Council does not sit during '.$phase->label().'.'),
        };
    }
}
