<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Gang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gang>
 */
class GangFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'name' => fake()->unique()->words(2, true),
            'notoriety' => 0,
        ];
    }
}
