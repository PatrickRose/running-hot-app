<?php

namespace Database\Factories;

use App\Models\FacilityCardActivation;
use App\Models\FacilityProtectionCard;
use App\Models\Turn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacilityCardActivation>
 */
class FacilityCardActivationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'turn_id' => Turn::factory(),
            'facility_protection_card_id' => FacilityProtectionCard::factory(),
        ];
    }

    /**
     * The card is warmed up, which is what makes going second worse.
     */
    public function active(): static
    {
        return $this->state(fn (): array => ['activated_at' => now()]);
    }

    public function boosted(int $boosts = 1): static
    {
        return $this->state(fn (): array => [
            'activated_at' => now(),
            'boosts' => $boosts,
            // Cumulative: 1, then 2, then 3 Credits.
            'boost_credits_spent' => (int) ($boosts * ($boosts + 1) / 2),
        ]);
    }
}
