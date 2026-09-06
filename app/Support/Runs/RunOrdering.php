<?php

namespace App\Support\Runs;

use App\Services\Dice;

/**
 * Who goes first when several groups hit the same Facility (rulebook 3.4.1).
 *
 * Ordering is not cosmetic. Cards the first group activates stay Active for the
 * rest of the phase and Boosts stay bought, so a later group walks into a
 * Facility that is already warmed up and pays for none of it - and may find
 * what they came for already gone. Going first is worth real Credits, which is
 * why the rulebook lets groups sell their place ("or be otherwise monetarily
 * convinced") and why the fallback is seven deterministic tiebreakers rather
 * than a shrug.
 *
 * Six of the seven are pure arithmetic. The seventh is a d8, which is why this
 * takes a Dice: the roll has to be server-side and recorded like every other,
 * and the order it produces is not reproducible from the roster afterwards.
 * That is also why every result carries the rule that decided it.
 *
 * Footnote 10 sends anything unusual to Control, so nothing here is final: a
 * group may cede its place outright, and Control may reorder by hand.
 */
class RunOrdering
{
    /**
     * The seven priorities, in the order the rulebook lists them.
     *
     * Named so a result can say which one settled it, since "why did they go
     * first?" is asked at the table rather than in a code review.
     */
    public const RULES = [
        1 => 'Fewest Runners',
        2 => 'Most Runners from the most notorious gang',
        3 => 'Highest combined Brawn',
        4 => 'Highest combined Hack',
        5 => "Run Leader's Brawn",
        6 => "Run Leader's Hack",
        7 => 'Run Leader rolled highest on a d8',
    ];

    public function __construct(private Dice $dice) {}

    /**
     * Put the groups in the order they go in.
     *
     * @param  array<int, RunGroup>  $groups
     * @return array<int, RunPlace>
     */
    public function order(array $groups): array
    {
        if ($groups === []) {
            return [];
        }

        // Rolled once per group up front rather than lazily inside the sort.
        // A comparison sort asks about the same pair more than once, and a die
        // that answered differently each time would not be a tiebreak at all.
        $rolls = [];

        foreach ($groups as $group) {
            $rolls[$group->runId] = $this->dice->roll(1, 8)[0] ?? 0;
        }

        // usort reindexes as it sorts, so $groups is a list from here on.
        usort($groups, fn (RunGroup $a, RunGroup $b): int => self::compare($a, $b, $rolls)['order']);

        $places = [];

        foreach ($groups as $index => $group) {
            // Which rule put this group where it is: the one that separated it
            // from the group ahead of it, or - for the group that went first,
            // which has nothing ahead - the one that kept the second behind.
            //
            // Worked out from the finished order rather than remembered during
            // the sort, because a comparison sort asks about pairs in whatever
            // order it likes and never promises to end on the adjacent one.
            [$ahead, $behind] = $index === 0
                ? [$group, $groups[1] ?? null]
                : [$groups[$index - 1], $group];

            $rule = $behind === null
                ? null
                : self::compare($ahead, $behind, $rolls)['rule'];

            $places[] = new RunPlace(
                runId: $group->runId,
                position: $index + 1,
                rule: $rule,
                reason: $rule === null ? 'The only group here' : self::RULES[$rule],
                roll: $rolls[$group->runId],
            );
        }

        return $places;
    }

    /**
     * Compare two groups, and say which rule separated them.
     *
     * Every rule bar the first is "highest wins", and the first is "fewest
     * wins" - which is the one that reads backwards and so the one to get
     * wrong. A smaller group goes first because it is the harder run: the
     * rewards are split fewer ways and the Alerts a group generates rise
     * steeply with its size.
     *
     * @param  array<int, int>  $rolls
     * @return array{order: int, rule: int}
     */
    private static function compare(RunGroup $a, RunGroup $b, array $rolls): array
    {
        if ($a->size !== $b->size) {
            return ['order' => $a->size <=> $b->size, 'rule' => 1];
        }

        $comparisons = [
            2 => [$a->membersOfMostNotoriousGang, $b->membersOfMostNotoriousGang],
            3 => [$a->combinedBrawn, $b->combinedBrawn],
            4 => [$a->combinedHack, $b->combinedHack],
            5 => [$a->leaderBrawn, $b->leaderBrawn],
            6 => [$a->leaderHack, $b->leaderHack],
            7 => [$rolls[$a->runId] ?? 0, $rolls[$b->runId] ?? 0],
        ];

        foreach ($comparisons as $rule => [$left, $right]) {
            if ($left !== $right) {
                return ['order' => $right <=> $left, 'rule' => $rule];
            }
        }

        // Two identical groups whose Leaders rolled the same number. The
        // rulebook does not go further, and neither does this: they keep the
        // order they were submitted in, and Control settles it if anybody
        // minds.
        return ['order' => 0, 'rule' => 7];
    }
}
