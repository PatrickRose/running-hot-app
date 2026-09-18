<?php

namespace App\Support\Runs;

use App\Services\Dice;

/**
 * The dice the Runners throw at a Protection Card.
 *
 * Rulebook 3.4.2: the Run Leader rolls dice equal to the skill the card asks
 * for, and everyone else adds half of theirs rounded down - or a quarter
 * rounded up if they are Wounded. Five or higher succeeds.
 *
 * **The die size comes from the Run Leader alone.** "If the Run Leader has at
 * least one Wound then they roll d6s, otherwise they roll d8s", and the others
 * "add more dice to the pool" - one pool, so one kind of die. A Wounded Runner
 * who is not leading is punished by contributing a quarter instead of a half,
 * not by shrinking anybody's dice. Which means a Wounded Leader handing over
 * before a hard card is a real tactic, and the application should not quietly
 * average the two.
 *
 * 3.4.5 asks players to work their contribution out in advance because the
 * Action phase is fifteen minutes long. That is this class's whole job: nobody
 * should be dividing by four at the table.
 */
readonly class DicePool
{
    /** Faces on the dice a Runner with no Wounds rolls. */
    public const HEALTHY_DIE = 8;

    /** Faces on the dice a Wounded Run Leader rolls. */
    public const WOUNDED_DIE = 6;

    /** A die of this or more is a success, for both sides. */
    public const SUCCESS_ON = 5;

    /**
     * The die has at least two faces, which is worth saying in the type
     * because {@see Dice} must never be asked for a d1 - a
     * one-sided die is not a die.
     *
     * @param  array<int, int>  $fromOthers  dice added, keyed by character id
     * @param  int<2, max>  $dieFaces
     */
    public function __construct(
        /** Dice from the Run Leader: their full skill. */
        public int $fromLeader,
        public array $fromOthers,
        /** 8, or 6 if the Run Leader is Wounded. */
        public int $dieFaces,
    ) {}

    /**
     * Assemble the pool for one challenge.
     *
     * @param  int  $leaderSkill  the Leader's score in whatever the card asks for
     * @param  array<int, array{skill: int, wounded: bool}>  $others  keyed by character id
     */
    public static function for(int $leaderSkill, bool $leaderWounded, array $others): self
    {
        $contributions = [];

        foreach ($others as $characterId => $runner) {
            $contributions[$characterId] = self::contribution($runner['skill'], $runner['wounded']);
        }

        return new self(
            fromLeader: max(0, $leaderSkill),
            fromOthers: $contributions,
            dieFaces: $leaderWounded ? self::WOUNDED_DIE : self::HEALTHY_DIE,
        );
    }

    /**
     * What one Runner who is not leading adds.
     *
     * Half rounded down while healthy, a quarter rounded up while Wounded. Note
     * the rounding goes opposite ways, which is not a slip in the rulebook: a
     * Wounded Runner with a skill of 1 still brings a die, where a healthy one
     * with a skill of 1 brings none. Being hurt is a penalty on the strong and
     * very nearly nothing on the weak.
     */
    public static function contribution(int $skill, bool $wounded): int
    {
        $skill = max(0, $skill);

        return $wounded
            ? max(0, (int) ceil($skill / 4))
            : max(0, intdiv($skill, 2));
    }

    /**
     * Every die the Runners throw.
     *
     * Added up in a loop with a floor under each addend rather than with
     * array_sum, so that a number of dice reaches the die roller as something
     * that provably cannot be negative. The properties are public and a pool
     * can be built by hand, so this is the place to hold that line.
     *
     * @return int<0, max>
     */
    public function total(): int
    {
        $total = max(0, $this->fromLeader);

        foreach ($this->fromOthers as $dice) {
            $total += max(0, $dice);
        }

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'leader' => $this->fromLeader,
            'others' => $this->fromOthers,
            'die_faces' => $this->dieFaces,
            'total' => $this->total(),
        ];
    }

    /**
     * How many of a set of faces succeeded.
     *
     * Kept here rather than at the roll, so that both sides of a challenge are
     * counted by the same code: Security rolls d8s against the challenge
     * strength and needs 5+ exactly as the Runners do.
     *
     * @param  array<int, int>  $faces
     */
    public static function countSuccesses(array $faces, int $threshold = self::SUCCESS_ON): int
    {
        return count(array_filter($faces, static fn (int $face): bool => $face >= $threshold));
    }

    /**
     * Whether the Runners beat Security.
     *
     * A tie goes to Security (3.4.2, footnote 12), so the Runners have to win
     * outright. This is one line and could be inlined at the call site, which
     * is exactly why it is not: the tie is the part that would be got wrong.
     */
    public static function runnersWin(int $runnerSuccesses, int $securitySuccesses): bool
    {
        return $runnerSuccesses > $securitySuccesses;
    }
}
