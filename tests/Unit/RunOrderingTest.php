<?php

namespace Tests\Unit;

use App\Support\Runs\RunGroup;
use App\Support\Runs\RunOrdering;
use Tests\Support\FakeDice;
use Tests\TestCase;

/**
 * Who goes first when several groups hit the same Facility (rulebook 3.4.1).
 *
 * Seven tiebreakers, applied in order, and each test here holds every earlier
 * rule level so that exactly one of them can be the thing that decides. That is
 * the only way to be sure rule 5 is being tested rather than rule 3 quietly
 * settling it first.
 */
class RunOrderingTest extends TestCase
{
    private function ordering(FakeDice $dice): RunOrdering
    {
        return new RunOrdering($dice);
    }

    /**
     * Every group rolls, whether or not the roll ends up mattering, so a test
     * fixing one earlier rule still has to feed the dice.
     */
    private function dice(int $groups = 2, int $face = 4): FakeDice
    {
        return (new FakeDice)->willRoll($groups, $face);
    }

    private function group(
        int $runId,
        int $size = 2,
        int $notorious = 0,
        int $brawn = 6,
        int $hack = 6,
        int $leaderBrawn = 3,
        int $leaderHack = 3,
    ): RunGroup {
        return new RunGroup(
            runId: $runId,
            size: $size,
            membersOfMostNotoriousGang: $notorious,
            combinedBrawn: $brawn,
            combinedHack: $hack,
            leaderBrawn: $leaderBrawn,
            leaderHack: $leaderHack,
        );
    }

    /**
     * Rule 1, and the one that reads backwards: fewest goes first. A smaller
     * group is the harder run, so it gets the cold Facility.
     */
    public function test_the_smallest_group_goes_first(): void
    {
        $places = $this->ordering($this->dice())->order([
            $this->group(runId: 10, size: 4),
            $this->group(runId: 20, size: 2),
        ]);

        $this->assertSame([20, 10], array_column($places, 'runId'));
        $this->assertSame([1, 2], array_column($places, 'position'));
        $this->assertSame('Fewest Runners', $places[0]->reason);
        $this->assertSame(1, $places[0]->rule);
    }

    /**
     * Rule 2, with sizes level. Counted against the most notorious gang in the
     * game, so a group with more of its members goes first.
     */
    public function test_the_most_notorious_gang_breaks_a_tie_on_size(): void
    {
        $places = $this->ordering($this->dice())->order([
            $this->group(runId: 10, size: 3, notorious: 1),
            $this->group(runId: 20, size: 3, notorious: 2),
        ]);

        $this->assertSame([20, 10], array_column($places, 'runId'));
        $this->assertSame(2, $places[0]->rule);
    }

    /**
     * The tiebreak can be a no-op: if the most notorious gang has nobody here,
     * every group scores zero and it falls through to combined Brawn.
     */
    public function test_a_gang_with_nobody_here_settles_nothing(): void
    {
        $places = $this->ordering($this->dice())->order([
            $this->group(runId: 10, size: 3, notorious: 0, brawn: 5),
            $this->group(runId: 20, size: 3, notorious: 0, brawn: 9),
        ]);

        $this->assertSame([20, 10], array_column($places, 'runId'));
        $this->assertSame(3, $places[0]->rule);
        $this->assertSame('Highest combined Brawn', $places[0]->reason);
    }

    public function test_combined_hack_breaks_a_tie_on_brawn(): void
    {
        $places = $this->ordering($this->dice())->order([
            $this->group(runId: 10, size: 3, brawn: 7, hack: 4),
            $this->group(runId: 20, size: 3, brawn: 7, hack: 8),
        ]);

        $this->assertSame([20, 10], array_column($places, 'runId'));
        $this->assertSame(4, $places[0]->rule);
    }

    /**
     * Rules 5 and 6 drop from the whole group to the Run Leader alone, which is
     * why two groups with identical combined scores can still be separated.
     */
    public function test_the_leaders_brawn_breaks_a_tie_on_the_group(): void
    {
        $places = $this->ordering($this->dice())->order([
            $this->group(runId: 10, size: 2, brawn: 8, hack: 8, leaderBrawn: 2),
            $this->group(runId: 20, size: 2, brawn: 8, hack: 8, leaderBrawn: 6),
        ]);

        $this->assertSame([20, 10], array_column($places, 'runId'));
        $this->assertSame(5, $places[0]->rule);
    }

    public function test_the_leaders_hack_breaks_a_tie_on_their_brawn(): void
    {
        $places = $this->ordering($this->dice())->order([
            $this->group(runId: 10, size: 2, brawn: 8, hack: 8, leaderBrawn: 4, leaderHack: 1),
            $this->group(runId: 20, size: 2, brawn: 8, hack: 8, leaderBrawn: 4, leaderHack: 7),
        ]);

        $this->assertSame([20, 10], array_column($places, 'runId'));
        $this->assertSame(6, $places[0]->rule);
    }

    /**
     * Rule 7, with every other rule level: the Leaders roll a d8 and the higher
     * goes first. Rolled once each rather than inside the comparison, because a
     * die that answered differently each time it was asked would not be a
     * tiebreak at all.
     */
    public function test_identical_groups_are_separated_by_a_d8(): void
    {
        $dice = (new FakeDice)->will([2, 7]);

        $places = $this->ordering($dice)->order([
            $this->group(runId: 10),
            $this->group(runId: 20),
        ]);

        $this->assertSame([20, 10], array_column($places, 'runId'));
        $this->assertSame(7, $places[0]->rule);
        $this->assertSame(7, $places[0]->roll);
        $this->assertSame(2, $places[1]->roll);

        // One d8 per group, and no more.
        $this->assertCount(2, $dice->rolls);
        $this->assertSame(['count' => 1, 'faces' => 8], [
            'count' => $dice->rolls[0]['count'],
            'faces' => $dice->rolls[0]['faces'],
        ]);
    }

    /**
     * The roll is named in the reason only when it was the thing that decided
     * the order. Every group rolls, and reporting a die that changed nothing
     * would invite an argument about a number that never counted.
     */
    public function test_the_roll_is_only_explained_when_it_mattered(): void
    {
        $decided = $this->ordering((new FakeDice)->will([2, 7]))->order([
            $this->group(runId: 10),
            $this->group(runId: 20),
        ]);

        $this->assertSame('Run Leader rolled highest on a d8 (7)', $decided[0]->explain());

        $sizeDecided = $this->ordering($this->dice())->order([
            $this->group(runId: 10, size: 4),
            $this->group(runId: 20, size: 2),
        ]);

        $this->assertSame('Fewest Runners', $sizeDecided[0]->explain());
    }

    /**
     * Every rule is held level so that only the one under test can decide, and
     * the queue comes out in the order the rules are written.
     */
    public function test_the_rules_apply_in_the_order_the_rulebook_lists_them(): void
    {
        $places = $this->ordering((new FakeDice)->willRoll(3, 4))->order([
            // Biggest group: last, on rule 1, whatever else it has.
            $this->group(runId: 30, size: 5, notorious: 9, brawn: 99, hack: 99),
            // Level on size with 20, but fewer of the notorious gang.
            $this->group(runId: 20, size: 2, notorious: 0, brawn: 99, hack: 99),
            $this->group(runId: 10, size: 2, notorious: 2, brawn: 1, hack: 1),
        ]);

        $this->assertSame([10, 20, 30], array_column($places, 'runId'));
        $this->assertSame(2, $places[0]->rule);
        $this->assertSame(1, $places[2]->rule);
    }

    /**
     * A group running alone still gets a place, and says so rather than naming
     * a rule that never had anything to decide.
     */
    public function test_a_lone_group_needs_no_tiebreak(): void
    {
        $places = $this->ordering($this->dice(1))->order([$this->group(runId: 10)]);

        $this->assertCount(1, $places);
        $this->assertSame(1, $places[0]->position);
        $this->assertNull($places[0]->rule);
        $this->assertSame('The only group here', $places[0]->explain());
    }

    public function test_nobody_running_orders_nothing(): void
    {
        $this->assertSame([], $this->ordering(new FakeDice)->order([]));
    }

    /**
     * Two identical groups whose Leaders roll the same number. The rulebook
     * does not go further and neither does this: they keep the order they were
     * submitted in, and Control settles it if anybody minds.
     */
    public function test_a_dead_heat_keeps_the_order_it_arrived_in(): void
    {
        $places = $this->ordering((new FakeDice)->will([5, 5]))->order([
            $this->group(runId: 10),
            $this->group(runId: 20),
        ]);

        $this->assertSame([10, 20], array_column($places, 'runId'));
    }

    /**
     * The profile is built from a group's characters, and only members of the
     * most notorious gang count towards rule 2 — including where two gangs are
     * tied at the top, since the rulebook names only one and says nothing about
     * a tie.
     */
    public function test_a_profile_counts_members_of_every_gang_tied_at_the_top(): void
    {
        $group = RunGroup::of(
            runId: 10,
            members: [
                ['gang_id' => 1, 'brawn' => 3, 'hack' => 2],
                ['gang_id' => 2, 'brawn' => 4, 'hack' => 5],
                ['gang_id' => 3, 'brawn' => 1, 'hack' => 1],
                ['gang_id' => null, 'brawn' => 2, 'hack' => 2],
            ],
            leader: ['gang_id' => 1, 'brawn' => 3, 'hack' => 2],
            mostNotoriousGangIds: [1, 2],
        );

        $this->assertSame(4, $group->size);
        $this->assertSame(2, $group->membersOfMostNotoriousGang);
        $this->assertSame(10, $group->combinedBrawn);
        $this->assertSame(10, $group->combinedHack);
        $this->assertSame(3, $group->leaderBrawn);
        $this->assertSame(2, $group->leaderHack);
    }

    /**
     * A Freelancer belongs to no gang (rulebook 2.3.2), so they can never count
     * towards rule 2 however notorious the company they keep.
     */
    public function test_a_freelancer_never_counts_towards_the_notoriety_rule(): void
    {
        $group = RunGroup::of(
            runId: 10,
            members: [
                ['gang_id' => null, 'brawn' => 4, 'hack' => 4],
                ['gang_id' => null, 'brawn' => 3, 'hack' => 3],
            ],
            leader: ['gang_id' => null, 'brawn' => 4, 'hack' => 4],
            mostNotoriousGangIds: [1],
        );

        $this->assertSame(0, $group->membersOfMostNotoriousGang);
    }
}
