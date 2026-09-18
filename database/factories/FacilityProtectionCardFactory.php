<?php

namespace Database\Factories;

use App\Enums\ProtectionKind;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\ProtectionCardType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacilityProtectionCard>
 */
class FacilityProtectionCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'kind' => ProtectionKind::Physical,
            // Position 1 is the card Runners meet first (rulebook 3.3.4).
            'position' => 1,
        ];
    }

    /**
     * Take the card type from the Facility's own game.
     *
     * Protection Card types are seeded per game, so a type from another game
     * would be a card this Facility could never hold.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (FacilityProtectionCard $card): void {
            if ($card->getAttribute('protection_card_type_id') === null) {
                $card->setAttribute('protection_card_type_id', ProtectionCardType::factory()->create([
                    'game_id' => $card->facility->game_id,
                    'kind' => $card->kind,
                ])->id);
            }
        });
    }

    public function cyber(): static
    {
        return $this->state(fn (): array => ['kind' => ProtectionKind::Cyber]);
    }
}
