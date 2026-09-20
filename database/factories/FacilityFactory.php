<?php

namespace Database\Factories;

use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
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
                $facility->setAttribute('game_id', $facility->corporation?->game_id);
            }
        });
    }

    /**
     * A Plot Facility: one Control built, belonging to nobody.
     *
     * The game has to be given, because there is no Corporation to read it off.
     */
    public function plot(Game $game): static
    {
        return $this->state(fn (): array => [
            'game_id' => $game->id,
            'corporation_id' => null,
        ]);
    }

    /**
     * Still being built, and so not yet usable.
     */
    public function buildingUntilTurn(int $turnNumber): static
    {
        return $this->state(fn (): array => ['available_from_turn' => $turnNumber]);
    }
}
