<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\EquipmentCardType;
use App\Models\EquipmentHolding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentHolding>
 */
class EquipmentHoldingFactory extends Factory
{
    protected $model = EquipmentHolding::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'equipment_card_type_id' => EquipmentCardType::factory(),
            'copies' => 1,
        ];
    }
}
