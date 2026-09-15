<?php

namespace App\Policies;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Run;
use App\Models\User;

/**
 * Who may do what on a run (rulebook 3.4).
 *
 * A run has two sides and they do different things, so this is not one
 * permission but three: the group, the Run Leader within it, and the Security
 * player defending the Facility. Control sits above all of them.
 *
 * The interesting question here is not "may I act" but "may I *look*", because
 * a run is the most secretive thing in the game. Two separate secrets are being
 * kept:
 *
 * - **Which Facility a group is running against** is chosen in Secret (3.4.1),
 *   and Security chooses its budgets in Secret at the same time. So a Security
 *   player may not see a submitted run at all - only one that has actually
 *   started, by which point the Runners are standing in their Facility.
 * - **How many Protection Cards a Facility holds** is Secret (3.4.1, footnote
 *   11), which is why the Runners' own view of their run has to be built
 *   separately rather than hidden less of. That is the presenter's job; this
 *   class only decides who gets which view.
 */
class RunPolicy
{
    /**
     * Control of this game does everything.
     *
     * Not as a convenience: the whole point of "players drive it, Control steps
     * in" is that an absent player never stops a run. A Runner who has gone to
     * get a drink mid-Facility would otherwise hold up both sides for the rest
     * of the phase.
     *
     * Control of *this* game, though. A seat on Saturday's game is not a seat
     * on somebody else's, and the run says which game is being asked about.
     */
    public function before(User $user, string $ability, mixed $subject = null): ?bool
    {
        $isControl = match (true) {
            $subject instanceof Run => $user->isControlFor($subject->game),
            $subject instanceof Game => $user->isControlFor($subject),
            default => $user->isControl(),
        };

        return $isControl ? true : null;
    }

    /**
     * Put in for a run (rulebook 3.4.1).
     *
     * Runners and Freelancers both, because 3.4 hands the Facility game to
     * "Runners" as a side rather than to one role - a Freelancer is in a gang
     * and goes on runs like anybody else. Only the Run Leader submits for a
     * group, but who leads is the group's own decision, so anybody who could
     * lead one may submit one.
     */
    public function submit(User $user, Game $game): bool
    {
        if ($game->status !== GameStatus::Running) {
            return false;
        }

        return $game->characters()
            ->where('user_id', $user->id)
            ->whereIn('role', [CharacterRole::Runner, CharacterRole::Freelancer])
            ->exists();
    }

    /**
     * See this run at all.
     *
     * Anybody on it, and the Security player of the Facility it is hitting once
     * it has actually begun. Not before: the target is Secret while it is being
     * chosen, and Security is setting budgets blind at the same moment.
     */
    public function view(User $user, Run $run): bool
    {
        return $this->isOnRun($user, $run) || $this->isDefending($user, $run);
    }

    /**
     * Act as the group: leave it, or be the person a consequence lands on.
     *
     * Any Runner still on the run, because walking away at the Breather is each
     * Runner's own decision - "Each Runner, starting with the Run Leader, may
     * take this opportunity to leave" - and not the Leader's to make for them.
     */
    public function act(User $user, Run $run): bool
    {
        return $this->isOnRun($user, $run, activeOnly: true);
    }

    /**
     * Act as the Run Leader.
     *
     * The Leader rolls the dice, decides who takes a consequence, decides
     * whether to shrug off an End the Run, and says when the group moves on.
     * 3.4.2 gives them the last word on an ambiguous target too, so where the
     * rulebook needs one Runner to decide, it is this one.
     */
    public function lead(User $user, Run $run): bool
    {
        if (! $this->runnable($run)) {
            return false;
        }

        if ($run->run_leader_character_id === null) {
            return false;
        }

        return $run->game->characters()
            ->where('user_id', $user->id)
            ->whereKey($run->run_leader_character_id)
            ->exists();
    }

    /**
     * Act as Security: switch cards on, Boost them, pay a Charge, spend Alerts.
     *
     * The Corporation's own Security seat and nobody else's. The CEO and the
     * Research player hold Corporate seats and may watch, exactly as they may
     * read their own stacks - but a Facility being defended by three people at
     * once is a Facility nobody can account for, and the Credits come out of a
     * budget one of them placed.
     */
    public function defend(User $user, Run $run): bool
    {
        return $this->runnable($run) && $this->isDefending($user, $run);
    }

    /**
     * Order the groups queueing at a Facility (rulebook 3.4.1).
     *
     * Control's alone, and the one ability here that no player has. The
     * rulebook has the groups submit to Facility Control, who then decides -
     * and a group that could order the queue itself could put itself first.
     */
    public function order(User $user, Game $game): bool
    {
        return false;
    }

    /**
     * Whether this user holds a character on the run.
     */
    private function isOnRun(User $user, Run $run, bool $activeOnly = false): bool
    {
        $participants = $activeOnly
            ? $run->activeParticipants()
            : $run->participants;

        $characterIds = $participants->pluck('character_id')->all();

        if ($characterIds === []) {
            return false;
        }

        return $run->game->characters()
            ->where('user_id', $user->id)
            ->whereIn('id', $characterIds)
            ->exists();
    }

    /**
     * Whether this user is the Security player of the Facility being hit, on a
     * run that has actually started.
     */
    private function isDefending(User $user, Run $run): bool
    {
        if ($run->started_at === null) {
            return false;
        }

        return $run->game->characters()
            ->where('user_id', $user->id)
            ->where('corporation_id', $run->facility->corporation_id)
            ->where('role', CharacterRole::Security)
            ->exists();
    }

    /**
     * Whether there is a live run here to act on at all.
     */
    private function runnable(Run $run): bool
    {
        return $run->game->status === GameStatus::Running
            && ! $run->status->isFinished();
    }
}
