<?php

namespace Database\Factories;

use App\Enums\DiscordResourceKind;
use App\Models\DiscordResource;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordResource>
 */
class DiscordResourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'kind' => DiscordResourceKind::Role,
            'key' => 'role:'.fake()->unique()->slug(2),
            // Snowflakes are always strings, never integers: see the migration.
            'discord_id' => (string) fake()->unique()->numberBetween(100000000000000000, 999999999999999999),
            'name' => fake()->words(2, true),
        ];
    }

    public function channel(): static
    {
        return $this->state(fn (): array => [
            'kind' => DiscordResourceKind::TextChannel,
            'key' => 'channel:'.fake()->unique()->slug(2),
        ]);
    }
}
