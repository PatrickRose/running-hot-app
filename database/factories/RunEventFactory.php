<?php

namespace Database\Factories;

use App\Enums\RunStep;
use App\Models\Run;
use App\Models\RunEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunEvent>
 */
class RunEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => Run::factory(),
            'pass' => 1,
            'step' => RunStep::Activate,
            'type' => 'card.activated',
            'description' => 'Something happened.',
        ];
    }
}
