<?php

namespace Database\Factories;

use App\Enums\ProtectionCardAvailability;
use App\Enums\ProtectionKind;
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
            // Unique because the code is what identifies a card, and a game's
            // catalogue is seeded with all eighty-three real ones - a factory
            // card has to sit alongside them without colliding.
            'code' => 'TC'.fake()->unique()->numberBetween(1000, 9999),
            'name' => ucfirst(fake()->unique()->word().' '.fake()->word()),
            'kind' => ProtectionKind::Physical,
            'cost' => fake()->numberBetween(1, 6),
            // As a card prints it. The cards say "Brute" where this application
            // says Brawn.
            'challenge' => 'Brute (2)',
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
            'challenge' => $kind === ProtectionKind::Cyber
                ? 'Hack (2)'
                : 'Brute (2)',
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

    /**
     * A card no Corporation has been given a copy of.
     *
     * Installing needs a copy in hand, so a test that installs has to say where
     * the copy came from. This is the default state - a card exists in the
     * catalogue long before anybody owns one.
     */
    public function heldBy(int $corporationId, int $copies = 1): static
    {
        return $this->afterCreating(function (ProtectionCardType $cardType) use ($corporationId, $copies): void {
            $cardType->holdings()->create([
                'corporation_id' => $corporationId,
                'copies' => $copies,
            ]);
        });
    }
}
