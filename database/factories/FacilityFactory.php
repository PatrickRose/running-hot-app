<?php

namespace Database\Factories;

use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'corporation_id' => Corporation::factory(),
            'facility_type_id' => FacilityType::factory(),
            'name' => fake()->unique()->streetName().' Site',
            'available_from_turn' => 1,
        ];
    }

    /**
     * Keep the Facility, its Corporation and its type in the same game.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Facility $facility): void {
            if ($facility->getAttribute('game_id') === null) {
                $facility->setAttribute('game_id', $facility->corporation->game_id);
            }
        });
    }

    /**
     * Still being built, and so not yet usable.
     */
    public function buildingUntilTurn(int $turnNumber): static
    {
        return $this->state(fn (): array => ['available_from_turn' => $turnNumber]);
    }
}
