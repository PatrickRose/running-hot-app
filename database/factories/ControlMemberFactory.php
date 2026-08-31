<?php

namespace Database\Factories;

use App\Models\ControlMember;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ControlMember>
 */
class ControlMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'user_id' => null,
            'discord_username' => fake()->unique()->userName(),
        ];
    }
}
