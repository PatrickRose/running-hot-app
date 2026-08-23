<?php

namespace Database\Factories;

use App\Enums\ProtectionCardAvailability;
use App\Enums\ProtectionKind;
use App\Enums\RunnerSkill;
use App\Models\Game;
use App\Models\ProtectionCardType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProtectionCardType>
 */
class ProtectionCardTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'name' => ucfirst(fake()->unique()->word().' '.fake()->word()),
            'kind' => ProtectionKind::Physical,
            'cost' => fake()->numberBetween(1, 6),
            'challenge_skill' => RunnerSkill::Brawn,
            'challenge_strength' => 2,
            'consequence' => 'One Wound.',
            'charge_cost' => null,
            'charge_consequence' => null,
            'availability' => ProtectionCardAvailability::Available,
        ];
    }

    public function ofKind(ProtectionKind $kind): static
    {
        return $this->state(fn (): array => [
            'kind' => $kind,
            'challenge_skill' => $kind === ProtectionKind::Cyber
                ? RunnerSkill::Hack
                : RunnerSkill::Brawn,
        ]);
    }

    public function cyber(): static
    {
        return $this->ofKind(ProtectionKind::Cyber);
    }

    public function withCharge(int $cost = 1): static
    {
        return $this->state(fn (): array => [
            'charge_cost' => $cost,
            'charge_consequence' => 'One Tag.',
        ]);
    }
}
