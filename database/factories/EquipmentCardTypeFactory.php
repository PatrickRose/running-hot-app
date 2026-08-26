<?php

namespace Database\Factories;

use App\Enums\EquipmentCategory;
use App\Models\EquipmentCardType;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentCardType>
 */
class EquipmentCardTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            // Unique because a game is seeded with all seventy-four real cards,
            // so a factory card has to sit alongside them without colliding.
            'code' => 'TE'.fake()->unique()->numberBetween(1000, 9999),
            'name' => ucfirst(fake()->unique()->word().' '.fake()->word()),
            'category' => EquipmentCategory::SingleUse,
            'effect' => '+1 die for next roll',
            'cost' => fake()->numberBetween(1, 10),
        ];
    }

    public function permanent(): static
    {
        return $this->state(fn (): array => [
            'category' => EquipmentCategory::Permanent,
            'effect' => '+2 Brute',
        ]);
    }

    public function thisRun(): static
    {
        return $this->state(fn (): array => [
            'category' => EquipmentCategory::ThisRun,
            'effect' => '-1 wound for the duration of this run',
        ]);
    }

    /**
     * A card the market does not sell, granted by a technology or a Facility's
     * access effect instead.
     */
    public function notOnSale(): static
    {
        return $this->state(fn (): array => ['cost' => null]);
    }
}
