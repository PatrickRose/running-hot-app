<?php

namespace App\Support\Runs;

/**
 * One group queueing at a Facility, reduced to the six things that order it.
 *
 * A value object rather than the Run itself, so that {@see RunOrdering} is
 * arithmetic over numbers and can be tested by writing the numbers down. The
 * mapping from Runs and Characters to these is a separate, thinner job.
 */
readonly class RunGroup
{
    public function __construct(
        public int $runId,
        /** Fewest goes first, so this is the first thing compared. */
        public int $size,
        /**
         * How many of this group belong to the most notorious gang in the game.
         *
         * Counted against the whole game's gangs rather than only those in the
         * queue, which is what "the gang with the highest Notoriety" says. That
         * means the tiebreak can be a no-op - if the most notorious gang has
         * nobody here, every group scores zero and it falls through to Brawn -
         * and a no-op is the right outcome rather than a bug.
         */
        public int $membersOfMostNotoriousGang,
        public int $combinedBrawn,
        public int $combinedHack,
        public int $leaderBrawn,
        public int $leaderHack,
    ) {}

    /**
     * Build a profile from a group's characters.
     *
     * @param  array<int, array{gang_id: int|null, brawn: int, hack: int}>  $members
     * @param  array{gang_id: int|null, brawn: int, hack: int}  $leader
     * @param  array<int, int>  $mostNotoriousGangIds  gang ids tied on the highest Notoriety
     */
    public static function of(int $runId, array $members, array $leader, array $mostNotoriousGangIds): self
    {
        $notorious = 0;
        $brawn = 0;
        $hack = 0;

        foreach ($members as $member) {
            if ($member['gang_id'] !== null && in_array($member['gang_id'], $mostNotoriousGangIds, true)) {
                $notorious++;
            }

            $brawn += $member['brawn'];
            $hack += $member['hack'];
        }

        return new self(
            runId: $runId,
            size: count($members),
            membersOfMostNotoriousGang: $notorious,
            combinedBrawn: $brawn,
            combinedHack: $hack,
            leaderBrawn: $leader['brawn'],
            leaderHack: $leader['hack'],
        );
    }
}
