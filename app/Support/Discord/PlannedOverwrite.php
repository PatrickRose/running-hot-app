<?php

namespace App\Support\Discord;

/**
 * One line of a channel's permission table.
 *
 * The target is a role key from the same blueprint, or the sentinel
 * {@see self::EVERYONE} for the guild's default role. Keys are resolved to
 * snowflakes when the blueprint is applied, so a blueprint stays pure.
 */
class PlannedOverwrite
{
    public const EVERYONE = '@everyone';

    public function __construct(
        public readonly string $target,
        public readonly int $allow = 0,
        public readonly int $deny = 0,
    ) {}
}
