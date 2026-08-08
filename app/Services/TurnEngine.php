<?php

namespace App\Services;

use App\Actions\ApplyTeamTimeUpkeep;
use App\Enums\GameStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Jobs\AdvancePhase;
use App\Models\Game;
use App\Models\Phase;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drives the 15/15/5 turn cycle (rulebook 2).
 *
 * The clock is server authoritative. A phase carries an explicit ends_at which
 * extensions and pauses mutate directly, plus a version that is bumped on every
 * mutation so that an already-queued auto-advance job can recognise itself as
 * stale and do nothing.
 */
class TurnEngine
{
    public function __construct(
        private readonly DiscordAnnouncer $announcer,
        private readonly ApplyTeamTimeUpkeep $upkeep,
    ) {}

    /**
     * Begin the game at Turn 1, Setup phase.
     */
    public function start(Game $game, ?User $actor = null): Phase
    {
        if ($game->currentPhase() !== null) {
            throw new RuntimeException('The game has already started.');
        }

        return DB::transaction(function () use ($game, $actor): Phase {
            $game->forceFill(['status' => GameStatus::Running])->save();

            $turn = $game->turns()->create(['number' => 1]);

            return $this->startPhase($turn, PhaseType::Setup, $actor);
        });
    }

    /**
     * Close the current phase and open the next, rolling into a new turn when
     * Team Time ends.
     */
    public function advance(Phase $phase, ?User $actor = null): Phase
    {
        if (! $phase->isActive()) {
            throw new RuntimeException('Only a running or paused phase can be advanced.');
        }

        return DB::transaction(function () use ($phase, $actor): Phase {
            $phase->forceFill([
                'status' => PhaseStatus::Completed,
                'ended_at' => Carbon::now(),
                'paused_at' => null,
                'version' => $phase->version + 1,
            ])->save();

            $this->announcer->phaseEnded($phase);

            $next = $phase->type->next();

            if ($next !== null) {
                return $this->startPhase($phase->turn, $next, $actor);
            }

            $turn = $phase->turn->game->turns()->create([
                'number' => $phase->turn->number + 1,
            ]);

            return $this->startPhase($turn, PhaseType::Setup, $actor);
        });
    }

    public function pause(Phase $phase): Phase
    {
        if ($phase->status !== PhaseStatus::Running) {
            throw new RuntimeException('Only a running phase can be paused.');
        }

        $phase->forceFill([
            'status' => PhaseStatus::Paused,
            'paused_at' => Carbon::now(),
            'version' => $phase->version + 1,
        ])->save();

        $this->announcer->phasePaused($phase);

        return $phase;
    }

    public function resume(Phase $phase): Phase
    {
        if ($phase->status !== PhaseStatus::Paused) {
            throw new RuntimeException('Only a paused phase can be resumed.');
        }

        // Push the deadline out by however long the pause lasted, so the phase
        // keeps the time it had left when Control stopped the clock.
        $pausedFor = $phase->paused_at !== null
            ? (int) $phase->paused_at->diffInSeconds(Carbon::now(), false)
            : 0;

        $phase->forceFill([
            'status' => PhaseStatus::Running,
            'paused_at' => null,
            'ends_at' => $phase->ends_at?->addSeconds(max(0, $pausedFor)),
            'version' => $phase->version + 1,
        ])->save();

        $this->announcer->phaseResumed($phase);
        $this->scheduleAutoAdvance($phase);

        return $phase;
    }

    /**
     * Add (or, with a negative value, remove) time from the running phase.
     */
    public function extend(Phase $phase, int $seconds): Phase
    {
        if (! $phase->isActive()) {
            throw new RuntimeException('Only a running or paused phase can be extended.');
        }

        $phase->forceFill([
            'ends_at' => $phase->ends_at?->addSeconds($seconds),
            'version' => $phase->version + 1,
        ])->save();

        if ($seconds > 0) {
            $this->announcer->phaseExtended($phase, $seconds);
        }

        $this->scheduleAutoAdvance($phase);

        return $phase;
    }

    public function finish(Game $game): Game
    {
        $phase = $game->currentPhase();

        if ($phase !== null) {
            $phase->forceFill([
                'status' => PhaseStatus::Completed,
                'ended_at' => Carbon::now(),
                'paused_at' => null,
                'version' => $phase->version + 1,
            ])->save();
        }

        $game->forceFill(['status' => GameStatus::Finished])->save();

        $this->announcer->gameFinished($game);

        return $game;
    }

    protected function startPhase(Turn $turn, PhaseType $type, ?User $actor = null): Phase
    {
        $game = $turn->game;
        $duration = (int) $game->getAttribute($type->durationColumn());
        $now = Carbon::now();

        $phase = $turn->phases()->create([
            'type' => $type,
            'sequence' => $type->sequenceIndex(),
            'status' => PhaseStatus::Running,
            'starts_at' => $now,
            'ends_at' => $now->copy()->addSeconds($duration),
            'version' => 0,
        ]);

        $phase->setRelation('turn', $turn);

        $this->announcer->phaseStarted($phase);

        // Income and free Wound recovery land as Team Time opens, giving Control
        // the whole phase to review and override them.
        if ($type === PhaseType::TeamTime) {
            $this->upkeep->handle($phase, $actor);
        }

        $this->scheduleAutoAdvance($phase);

        return $phase;
    }

    protected function scheduleAutoAdvance(Phase $phase): void
    {
        if (! $phase->turn->game->auto_advance) {
            return;
        }

        if ($phase->status !== PhaseStatus::Running || $phase->ends_at === null) {
            return;
        }

        // The sync driver runs delayed jobs immediately, which would end the
        // phase the moment it started. There, the scheduled game:tick command is
        // the only thing that may roll a phase over.
        if (config('queue.default') === 'sync') {
            return;
        }

        AdvancePhase::dispatch($phase->id, $phase->version)->delay($phase->ends_at);
    }
}
