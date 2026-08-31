<?php

namespace Database\Factories;

use App\Enums\ResearchSuit;
use App\Enums\ResearchZone;
use App\Models\Game;
use App\Models\ResearchCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchCard>
 */
class ResearchCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            // The public deck by default: a private card needs a Corporation,
            // and a factory that invented one would put a deck in the hands of
            // a Corporation nobody asked for.
            'corporation_id' => null,
            'suit' => fake()->randomElement(ResearchSuit::all()),
            'value' => fake()->numberBetween(1, 5),
            'zone' => ResearchZone::Deck,
            'position' => 0,
        ];
    }

    /**
     * A card of no suit, which counts as any (rulebook 3.2.1).
     */
    public function wild(): static
    {
        return $this->state(fn (): array => ['suit' => null]);
    }

    public function inZone(ResearchZone $zone): static
    {
        return $this->state(fn (): array => ['zone' => $zone]);
    }
}
