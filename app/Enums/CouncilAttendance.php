<?php

namespace App\Enums;

/**
 * Whether a Corporation took its seat at the Council (rulebook 3.1.2).
 *
 * Unknown is the resting state and is not the same as absent: attendance is
 * something Control observes in the room, so a seat nobody has marked has not
 * been judged either way and costs nothing.
 */
enum CouncilAttendance: string
{
    case Unknown = 'unknown';
    case Present = 'present';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Not marked',
            self::Present => 'Present',
            self::Absent => 'Absent',
        };
    }
}
