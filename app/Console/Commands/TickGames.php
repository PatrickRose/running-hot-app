<?php

namespace App\Console\Commands;

use App\Enums\GameStatus;
use App\Enums\PhaseStatus;
use App\Models\Phase;
use App\Services\TurnEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Safety net for the turn clock.
 *
 * Phases normally roll over via a delayed AdvancePhase job. If the queue worker
 * was down when a deadline passed, that job is lost; this command catches any
 * phase that has outlived its clock and advances it.
 */
class TickGames extends Command
{
    protected $signature = 'game:tick';

    protected $description = 'Advance any game phase whose clock has run out';

    public function handle(TurnEngine $engine): int
    {
        $overdue = Phase::query()
            ->with('turn.game')
            ->where('status', PhaseStatus::Running)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', Carbon::now())
            ->whereHas('turn.game', fn ($query) => $query
                ->where('status', GameStatus::Running)
                ->where('auto_advance', true))
            ->get();

        foreach ($overdue as $phase) {
            $this->info(sprintf(
                'Advancing %s (turn %d, %s phase).',
                $phase->turn->game->name,
                $phase->turn->number,
                $phase->type->label(),
            ));

            $engine->advance($phase);
        }

        if ($overdue->isEmpty()) {
            $this->info('No phases are overdue.');
        }

        return self::SUCCESS;
    }
}
