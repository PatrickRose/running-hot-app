<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\TechnologyType;
use App\Support\TechnologyBlueprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnologyType>
 */
class TechnologyTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            // Unique because a game is seeded with all the real technologies,
            // so a factory row has to sit alongside them without colliding.
            'code' => 'TR'.fake()->unique()->numberBetween(1000, 9999),
            'name' => ucfirst(fake()->unique()->word().' '.fake()->word()),
            'tree' => TechnologyBlueprint::COMMON,
            'description' => 'Something worth looking into.',
            'effect' => 'Your income is increased.',
            'cog_cost' => 0,
            'brain_cost' => 10,
            'leaf_cost' => 8,
            'maths_cost' => 0,
            'prerequisites' => [],
            'copy_strength' => 4,
            'destroy_strength' => 4,
        ];
    }

    /**
     * A technology on one Corporation's own tree rather than the common set.
     */
    public function onTree(string $tree): static
    {
        return $this->state(fn (): array => ['tree' => $tree]);
    }

    /**
     * @param  array<int, string>  $titles
     */
    public function requiring(array $titles): static
    {
        return $this->state(fn (): array => ['prerequisites' => $titles]);
    }

    /**
     * A starting technology, which is on the tree so its split pieces can be
     * tracked rather than because anybody pays for it.
     */
    public function free(): static
    {
        return $this->state(fn (): array => [
            'cog_cost' => 0,
            'brain_cost' => 0,
            'leaf_cost' => 0,
            'maths_cost' => 0,
        ]);
    }
}
