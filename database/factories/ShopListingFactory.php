<?php

namespace Database\Factories;

use App\Enums\ShopListingStatus;
use App\Models\EquipmentCardType;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Models\ShopListing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopListing>
 */
class ShopListingFactory extends Factory
{
    protected $model = ShopListing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'stockable_type' => (new ProtectionCardType)->getMorphClass(),
            'stockable_id' => ProtectionCardType::factory(),
            'price' => 5,
            'stock' => 3,
            'status' => ShopListingStatus::OnSale,
            'notes' => null,
        ];
    }

    /**
     * A line at the Runners' market instead (rulebook 2.1).
     */
    public function equipment(?EquipmentCardType $card = null): static
    {
        return $this->state(fn (): array => [
            'stockable_type' => (new EquipmentCardType)->getMorphClass(),
            'stockable_id' => $card->id ?? EquipmentCardType::factory(),
        ]);
    }

    /**
     * A line for a named Protection Card.
     */
    public function protection(ProtectionCardType $card): static
    {
        return $this->state(fn (): array => [
            'stockable_type' => (new ProtectionCardType)->getMorphClass(),
            'stockable_id' => $card->id,
        ]);
    }

    /**
     * On the list but not yet for sale - 3.3.3's "rumoured to be in progress".
     */
    public function rumoured(): static
    {
        return $this->state(fn (): array => ['status' => ShopListingStatus::Rumoured]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (): array => ['status' => ShopListingStatus::Withdrawn]);
    }

    /**
     * A line that never runs out, which is what a null stock means.
     */
    public function unlimited(): static
    {
        return $this->state(fn (): array => ['stock' => null]);
    }

    public function soldOut(): static
    {
        return $this->state(fn (): array => ['stock' => 0]);
    }
}
