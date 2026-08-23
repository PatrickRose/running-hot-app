<?php

namespace Database\Factories;

use App\Enums\DiscordSyncStatus;
use App\Models\DiscordMemberSync;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordMemberSync>
 */
class DiscordMemberSyncFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'user_id' => User::factory(),
            'status' => DiscordSyncStatus::Synced,
            'message' => null,
            'role_ids' => [],
            'synced_at' => now(),
        ];
    }

    /**
     * A player who has signed in but never joined the Discord server, which is
     * the case Control most wants to see on a screen.
     */
    public function notAMember(): static
    {
        return $this->state(fn (): array => [
            'status' => DiscordSyncStatus::NotAMember,
            'message' => 'This player has not joined the game\'s Discord server yet.',
            'role_ids' => [],
        ]);
    }
}
