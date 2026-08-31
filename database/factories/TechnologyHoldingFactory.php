<?php

namespace Database\Factories;

use App\Enums\TechnologyHoldingStatus;
use App\Enums\TechnologyOrigin;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TechnologyHolding>
 */
class TechnologyHoldingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'corporation_id' => Corporation::factory(),
            'technology_type_id' => TechnologyType::factory(),
            'status' => TechnologyHoldingStatus::Researched,
            'origin' => TechnologyOrigin::Researched,
            'discount_percent' => 0,
            'researched_at' => Carbon::now(),
        ];
    }

    /**
     * A card in a Corporation's hands that nobody has paid for yet - a copy, or
     * one a Run brought back (rulebook 3.2.6).
     */
    public function claimed(TechnologyOrigin $origin = TechnologyOrigin::GoodCopy): static
    {
        return $this->state(fn (): array => [
            'status' => TechnologyHoldingStatus::Claimed,
            'origin' => $origin,
            'discount_percent' => $origin->defaultDiscountPercent(),
            'researched_at' => null,
        ]);
    }
}
