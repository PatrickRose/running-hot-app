<?php

namespace Database\Factories;

use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Models\Phase;
use App\Models\Turn;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Phase>
 */
class PhaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = Carbon::now();

        return [
            'turn_id' => Turn::factory(),
            'type' => PhaseType::Setup,
            'sequence' => PhaseType::Setup->sequenceIndex(),
            'status' => PhaseStatus::Running,
            'starts_at' => $now,
            'ends_at' => $now->copy()->addSeconds(900),
            'version' => 0,
        ];
    }

    public function ofType(PhaseType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'sequence' => $type->sequenceIndex(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (): array => [
            'status' => PhaseStatus::Paused,
            'paused_at' => Carbon::now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => Carbon::now()->subSeconds(1000),
            'ends_at' => Carbon::now()->subSeconds(100),
        ]);
    }
}
