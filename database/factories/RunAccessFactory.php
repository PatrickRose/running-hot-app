<?php

namespace Database\Factories;

use App\Enums\RunAccessKind;
use App\Models\Character;
use App\Models\Run;
use App\Models\RunAccess;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunAccess>
 */
class RunAccessFactory extends Factory
{
    protected $model = RunAccess::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => Run::factory(),
            'character_id' => Character::factory(),
            'kind' => RunAccessKind::Plot,
        ];
    }
}
