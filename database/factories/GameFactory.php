<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Running Hot: '.fake()->city(),
            'status' => GameStatus::Draft,
            'stability' => 6,
            'civil_unrest' => 0,
            'setup_seconds' => 900,
            'action_seconds' => 900,
            'team_time_seconds' => 300,
            'auto_advance' => true,
            'discord_webhook_url' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => ['status' => GameStatus::Running]);
    }

    public function manuallyAdvanced(): static
    {
        return $this->state(fn (): array => ['auto_advance' => false]);
    }
}
