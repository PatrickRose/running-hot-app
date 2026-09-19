<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\Game;
use App\Models\ShopListing;
use App\Models\ShopPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopPurchase>
 */
class ShopPurchaseFactory extends Factory
{
    protected $model = ShopPurchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'shop_listing_id' => ShopListing::factory(),
            'phase_id' => null,
            'character_id' => Character::factory(),
            'corporation_id' => null,
            'price_paid' => 5,
        ];
    }
}
