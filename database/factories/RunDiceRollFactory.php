<?php

namespace Database\Factories;

use App\Enums\DiceRoller;
use App\Models\Run;
use App\Models\RunDiceRoll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunDiceRoll>
 */
class RunDiceRollFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faces = [3, 8, 1, 5];

        return [
            'run_id' => Run::factory(),
            'roller' => DiceRoller::Runners,
            'pool' => count($faces),
            'die_faces' => 8,
            'threshold' => 5,
            'faces' => $faces,
            'successes' => count(array_filter($faces, fn (int $face): bool => $face >= 5)),
            'reason' => 'Breaking a Protection Card',
        ];
    }
}
