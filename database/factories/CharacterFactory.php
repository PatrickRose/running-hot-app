<?php

namespace Database\Factories;

use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Gang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'user_id' => null,
            'corporation_id' => null,
            'gang_id' => null,
            'name' => fake()->name(),
            'role' => CharacterRole::Runner,
            'brawn' => 4,
            'hack' => 4,
            'body' => 3,
            'credits' => 0,
            'wounds' => 0,
            'tags' => 0,
        ];
    }

    public function runner(?Gang $gang = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => CharacterRole::Runner,
            'gang_id' => $gang->id ?? Gang::factory()->state(['game_id' => $attributes['game_id']]),
        ]);
    }

    public function corporate(CharacterRole $role, ?Corporation $corporation = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => $role,
            'corporation_id' => $corporation->id ?? Corporation::factory()->state(['game_id' => $attributes['game_id']]),
        ]);
    }

    public function wounded(int $wounds = 1): static
    {
        return $this->state(fn (): array => ['wounds' => $wounds]);
    }

    public function tagged(int $tags = 1): static
    {
        return $this->state(fn (): array => ['tags' => $tags]);
    }
}
