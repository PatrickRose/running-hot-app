<?php

namespace App\Jobs;

use App\Enums\PhaseStatus;
use App\Models\Phase;
use App\Services\TurnEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Rolls a phase over when its clock runs out.
 *
 * Queued with a delay until the phase's ends_at. Because Control may extend,
 * pause or advance the phase by hand in the meantime, the job carries the
 * version it was scheduled against and stands down if that no longer matches.
 */
class AdvancePhase implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public int $phaseId,
        public int $version,
    ) {}

    public function handle(TurnEngine $engine): void
    {
        $phase = Phase::query()->with('turn.game')->find($this->phaseId);

        if ($phase === null || $phase->version !== $this->version) {
            return;
        }

        if ($phase->status !== PhaseStatus::Running) {
            return;
        }

        // Never cut a phase short. If this fired early, stand down and let the
        // game:tick backstop pick the phase up once it is genuinely overdue.
        // Re-dispatching here would spin forever on the sync queue driver.
        if ($phase->ends_at !== null && Carbon::now()->lessThan($phase->ends_at)) {
            return;
        }

        $engine->advance($phase);
    }
}
