<?php

namespace App\Support\Discord;

use App\Enums\DiscordResourceKind;

/**
 * A category or channel the guild should have.
 */
class PlannedChannel
{
    /**
     * @param  array<int, PlannedOverwrite>  $overwrites
     */
    public function __construct(
        public readonly string $key,
        public readonly DiscordResourceKind $kind,
        public readonly string $name,
        public readonly ?string $parentKey = null,
        public readonly array $overwrites = [],
        public readonly ?string $topic = null,
    ) {}
}
