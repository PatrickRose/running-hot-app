<?php

namespace App\Services;

use App\Actions\ApplyTeamTimeUpkeep;
use App\Actions\PublishFacilityList;
use App\Enums\GameStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Jobs\AdvancePhase;
use App\Jobs\ClearRunChannels;
use App\Models\Game;
use App\Models\Phase;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

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
        private readonly FacilityDefenceService $facilityDefence,
        private readonly PublishFacilityList $facilityList,
        private readonly CouncilService $council,
        private readonly ResearchTableService $researchTable,
        private readonly RunEngine $runs,
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

            // Three things end with the Action phase. They are independent of
            // one another, so the order here is the order they happen in at the
            // table rather than a dependency between them.
            if ($phase->type === PhaseType::Action) {
                // A run that has not got through by the time the phase is
                // called is unsuccessful (rulebook 3.4.5).
                $this->runs->failUnfinishedRuns($phase->turn, $actor);

                // Any security budget Security did not spend goes back to the
                // Corporation (rulebook 3.3.5). Nothing a failing run does can
                // reach it: the consolation payment of 3.4.4 is Credits the
                // game pays a Character, and the escrow is the Corporation's
                // own Credits coming home, so the two never meet.
                $this->facilityDefence->returnUnspentBudgets($phase->turn, $actor);

                // "The phase end is called" is one of the two ways the research
                // game ends (3.2.1), and the table is only ever open during the
                // Action phase. Nothing else closed it, so a sitting stayed
                // open through Team Time and the next Setup and went on
                // accepting equations.
                $this->closeResearchTable($phase->turn->game);
            }

            $next = $phase->type->next();

            if ($next !== null) {
                return $this->startPhase($phase->turn, $next, $actor);
            }

            // The turn is over, so last turn's conversation comes out of the
            // Facility channels it happened in. They are permanent and reused,
            // so a group going in next turn would otherwise read the last one
            // working out what was in the stack - which 3.4.2 makes Secret and
            // reconnaissance is what you pay to find out. Queued and fail-soft:
            // a Discord outage must never be able to stop the clock.
            //
            // At the end of the turn rather than the end of the Action phase,
            // so the group has Team Time to read back over how it went.
            ClearRunChannels::dispatch($phase->turn->id);

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

        // The Council's recess is a second clock inside the Setup phase, so it
        // keeps the time it had left as well: a game paused four minutes into
        // Setup should not come back to a Council that has already risen.
        $session = $phase->turn->councilSession()->first();

        if ($session !== null) {
            $this->council->shiftRecess($session, max(0, $pausedFor));
        }

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

        // The game ending ends the phase, so it ends the research game too -
        // this is the one path to a completed phase that does not go through
        // advance().
        $this->closeResearchTable($game);

        $game->forceFill(['status' => GameStatus::Finished])->save();

        $this->announcer->gameFinished($game);

        return $game;
    }

    /**
     * Shut the research table, if one is open.
     *
     * Deliberately not fail-soft, where opening one is. Dealing a sitting seeds
     * decks, gathers every card, seats the Corporations and deals them a hand,
     * and none of that may stop a phase from starting - closing one writes a
     * single column, and swallowing a failure here would leave open exactly the
     * table this exists to shut.
     *
     * Scoring is untouched by it. An equation is played in one phase and scored
     * "while other players are taking their turns" (3.2.1), so a closed table
     * still pays out: what closing stops is playing another one.
     */
    protected function closeResearchTable(Game $game): void
    {
        $session = $this->researchTable->currentSession($game);

        if ($session !== null) {
            $this->researchTable->closeSession($session);
        }
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

        // A Facility requisitioned last turn opens now, so the published list
        // is a turn out of date the moment Setup begins. Only ever an edit:
        // refresh does nothing until Control has published one.
        if ($type === PhaseType::Setup) {
            $this->facilityList->refresh($game);

            // The Council takes its seats as Setup opens, and goes into recess
            // five minutes later (rulebook 3.1.1). Opening the sitting here is
            // what anchors that second clock to the phase's own start.
            $this->council->openSession($turn, $now);
        }

        // "During the Action Phase, research players should make their way to
        // the research table" (rulebook 3.2.1), so the cards are dealt as the
        // phase opens rather than waiting on Research Control remembering to.
        // Control re-deals, redraws the order and closes the table from its own
        // panel; this only sets the table.
        //
        // Fail-soft, on the same terms as the announcements: a research game
        // that could not be dealt is a sub-game Control deals by hand, and it
        // must never be a phase that would not start.
        if ($type === PhaseType::Action) {
            try {
                $this->researchTable->openSession($game, $turn);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

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
