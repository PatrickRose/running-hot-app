<?php

namespace Database\Factories;

use App\Models\Corporation;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Corporation>
 */
class CorporationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'name' => fake()->unique()->company(),
            'income' => 10,
            'political_will' => 5,
            'credits' => 0,
        ];
    }
}
