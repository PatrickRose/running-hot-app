<?php

namespace Database\Factories;

use App\Models\FacilityType;
use App\Models\Game;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FacilityType>
 */
class FacilityTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'game_id' => Game::factory(),
            'key' => Str::slug($name),
            'name' => Str::title($name),
            'description' => null,
            'protection_slots_granted' => 0,
            'technology_capacity_granted' => 0,
        ];
    }

    /**
     * One of the types every game starts with.
     */
    public function ofKey(string $key): static
    {
        /** @var array<string, mixed>|null $defaults */
        $defaults = collect(FacilityTypeBlueprint::defaults())
            ->firstWhere('key', $key);

        return $this->state(fn (): array => $defaults ?? ['key' => $key]);
    }

    public function security(): static
    {
        return $this->ofKey(FacilityTypeBlueprint::SECURITY);
    }

    public function corporate(): static
    {
        return $this->ofKey(FacilityTypeBlueprint::CORPORATE);
    }

    public function research(): static
    {
        return $this->ofKey(FacilityTypeBlueprint::RESEARCH);
    }
}
