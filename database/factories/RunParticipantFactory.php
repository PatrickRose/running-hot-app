<?php

namespace Database\Factories;

use App\Enums\RunDeparture;
use App\Models\Character;
use App\Models\Run;
use App\Models\RunParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunParticipant>
 */
class RunParticipantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => Run::factory(),
            'position' => 1,
        ];
    }

    /**
     * Keep the Runner in the same game as the run they are on.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (RunParticipant $participant): void {
            if ($participant->getAttribute('character_id') === null) {
                $participant->setAttribute('character_id', Character::factory()->create([
                    'game_id' => $participant->run->game_id,
                ])->id);
            }
        });
    }

    public function left(): static
    {
        return $this->state(fn (): array => [
            'left_at' => now(),
            'left_reason' => RunDeparture::Left,
        ]);
    }

    public function incapacitated(): static
    {
        return $this->state(fn (): array => [
            'left_at' => now(),
            'left_reason' => RunDeparture::Incapacitated,
        ]);
    }
}
