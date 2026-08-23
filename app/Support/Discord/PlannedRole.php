<?php

namespace App\Support\Discord;

/**
 * A role the guild should have, described independently of whether it exists.
 */
class PlannedRole
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly int $colour,
        public readonly bool $hoist = true,
        public readonly bool $mentionable = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->colour,
            // Hoisted roles show as their own group in the member list, which
            // is how Control finds a team at a glance during a live game.
            'hoist' => $this->hoist,
            'mentionable' => $this->mentionable,
            // Deliberately no permissions: these roles exist to be addressed
            // and to open channels, never to grant anyone abilities.
            'permissions' => '0',
        ];
    }
}
