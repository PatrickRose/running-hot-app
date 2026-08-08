<?php

namespace App\Enums;

/**
 * The three phases of a Running Hot turn (rulebook 2).
 */
enum PhaseType: string
{
    case Setup = 'setup';
    case Action = 'action';
    case TeamTime = 'team_time';

    public function label(): string
    {
        return match ($this) {
            self::Setup => 'Setup',
            self::Action => 'Action',
            self::TeamTime => 'Team Time',
        };
    }

    /**
     * The order phases run in within a single turn.
     *
     * @return array<int, self>
     */
    public static function sequence(): array
    {
        return [self::Setup, self::Action, self::TeamTime];
    }

    public function sequenceIndex(): int
    {
        return match ($this) {
            self::Setup => 0,
            self::Action => 1,
            self::TeamTime => 2,
        };
    }

    /**
     * The phase that follows this one, or null if this ends the turn.
     */
    public function next(): ?self
    {
        return self::sequence()[$this->sequenceIndex() + 1] ?? null;
    }

    /**
     * The column on the game holding this phase's configured duration.
     */
    public function durationColumn(): string
    {
        return match ($this) {
            self::Setup => 'setup_seconds',
            self::Action => 'action_seconds',
            self::TeamTime => 'team_time_seconds',
        };
    }
}
