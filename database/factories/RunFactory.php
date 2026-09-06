<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\Character;
use App\Models\Facility;
use App\Models\Run;
use App\Models\Turn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
class RunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'status' => RunStatus::Submitted,
        ];
    }

    /**
     * Keep the run, its Facility, its turn and its leader in the same game.
     *
     * A run reaches into four tables that all carry a game_id, and a factory
     * that let them drift would make every test that touched two of them lie.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Run $run): void {
            $facility = $run->facility;

            if ($run->getAttribute('game_id') === null) {
                $run->setAttribute('game_id', $facility->game_id);
            }

            if ($run->getAttribute('turn_id') === null) {
                $run->setAttribute('turn_id', Turn::factory()->create([
                    'game_id' => $run->getAttribute('game_id'),
                ])->id);
            }

            if ($run->getAttribute('run_leader_character_id') === null) {
                $run->setAttribute('run_leader_character_id', Character::factory()->create([
                    'game_id' => $run->getAttribute('game_id'),
                ])->id);
            }
        });
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Succeeded,
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Failed,
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
        ]);
    }
}
