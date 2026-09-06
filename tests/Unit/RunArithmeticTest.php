<?php

namespace Tests\Unit;

use App\Enums\ProtectionKind;
use App\Support\Runs\ActivationCost;
use App\Support\Runs\AlertSchedule;
use App\Support\Runs\ChallengeStrength;
use App\Support\Runs\DicePool;
use App\Support\Runs\RunRewards;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The arithmetic of a Run (rulebook 3.4).
 *
 * Every table the rulebook prints is asserted against here, value for value,
 * and every worked example it gives is a test of its own. This is the part of
 * the Run that nobody should be doing at the table under a fifteen-minute
 * clock, which makes it the part that has to be right.
 */
class RunArithmeticTest extends TestCase
{
    /**
     * The whole printed table: 1 to 6 Runners.
     */
    #[DataProvider('printedGroupBonuses')]
    public function test_the_group_size_bonus_matches_the_printed_table(int $runners, int $expected): void
    {
        $this->assertSame($expected, AlertSchedule::forGroupSize($runners));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function printedGroupBonuses(): array
    {
        return [
            'a lone Runner brings nothing' => [1, 0],
            '2 Runners' => [2, 1],
            '3 Runners' => [3, 2],
            '4 Runners' => [4, 4],
            '5 Runners' => [5, 7],
            '6 Runners' => [6, 11],
        ];
    }

    /**
     * Past six the rulebook says "see help sheet on the day", so these are our
     * reading of the run 0, 1, 2, 4, 7, 11 — each extra Runner past the second
     * costing one more Alert than the last did.
     */
    public function test_the_group_size_bonus_extends_past_the_printed_table(): void
    {
        $this->assertSame(16, AlertSchedule::forGroupSize(7));
        $this->assertSame(22, AlertSchedule::forGroupSize(8));
        $this->assertSame(29, AlertSchedule::forGroupSize(9));

        // And the interface can say which of those is a proposal.
        $this->assertFalse(AlertSchedule::groupBonusIsExtrapolated(6));
        $this->assertTrue(AlertSchedule::groupBonusIsExtrapolated(7));
    }

    /**
     * Control's number always wins, because past six the book sends them to a
     * help sheet this application has never seen.
     */
    public function test_control_can_override_the_group_size_bonus(): void
    {
        $this->assertSame(3, AlertSchedule::forGroupSize(6, override: 3));
        $this->assertSame(0, AlertSchedule::forGroupSize(9, override: 0));
        $this->assertSame(0, AlertSchedule::forGroupSize(4, override: -5));
    }

    public function test_every_tag_the_group_carries_generates_an_alert(): void
    {
        $this->assertSame(0, AlertSchedule::forTags([0, 0, 0]));
        $this->assertSame(4, AlertSchedule::forTags([2, 1, 0, 1]));
    }

    /**
     * A group of three carrying four Tags between them opens on six Alerts:
     * four for the Tags and two for being three.
     */
    public function test_a_run_opens_on_tags_plus_the_group_bonus(): void
    {
        $this->assertSame(6, AlertSchedule::atStart([2, 1, 1]));

        // A lone Runner with no Tags is the quietest a run can start.
        $this->assertSame(0, AlertSchedule::atStart([0]));
    }

    /**
     * The printed thresholds are 1, 3 and 6 Alerts for +1, +2 and +3, and "and
     * so on" continues triangularly: 10 for +4, 15 for +5.
     */
    #[DataProvider('alertStrengthCurve')]
    public function test_alerts_strengthen_every_card_on_a_triangular_curve(int $alerts, int $expected): void
    {
        $this->assertSame($expected, AlertSchedule::strengthBonus($alerts));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function alertStrengthCurve(): array
    {
        return [
            'no alerts, no bonus' => [0, 0],
            'one alert is the first step' => [1, 1],
            'two is still one' => [2, 1],
            'three is the second step' => [3, 2],
            'five is still two' => [5, 2],
            'six is the third step' => [6, 3],
            'nine is still three' => [9, 3],
            'ten is the fourth step' => [10, 4],
            'fifteen is the fifth' => [15, 5],
            'twenty is still five' => [20, 5],
            'twenty-one is the sixth' => [21, 6],
        ];
    }

    /**
     * For the Security player weighing whether to spend: how close the Runners
     * are to making every remaining card harder.
     */
    public function test_the_next_alert_threshold_is_reported(): void
    {
        $this->assertSame(1, AlertSchedule::nextStrengthThreshold(0));
        $this->assertSame(3, AlertSchedule::nextStrengthThreshold(1));
        $this->assertSame(6, AlertSchedule::nextStrengthThreshold(3));
        $this->assertSame(10, AlertSchedule::nextStrengthThreshold(6));
    }

    /**
     * The rulebook works this one through card by card, and the off-by-one is
     * easy: the first and second Active cards get nothing, the third and fourth
     * get +1, the fifth and sixth get +2.
     */
    #[DataProvider('cardsPassedBonus')]
    public function test_passing_cards_strengthens_the_next_one(int $cardsPassed, int $expected): void
    {
        $this->assertSame($expected, ChallengeStrength::fromCardsPassed($cardsPassed));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function cardsPassedBonus(): array
    {
        return [
            'facing the first card, none passed' => [0, 0],
            'facing the second, one passed' => [1, 0],
            'facing the third, two passed' => [2, 1],
            'facing the fourth, three passed' => [3, 1],
            'facing the fifth, four passed' => [4, 2],
            'facing the sixth, five passed' => [5, 2],
            'facing the seventh, six passed' => [6, 3],
        ];
    }

    /**
     * All three sources stack, and the breakdown says where each point came
     * from — which is the difference between a ruling and an argument.
     */
    public function test_the_three_sources_of_strength_stack_and_are_itemised(): void
    {
        $strength = ChallengeStrength::for(printed: 3, cardsPassed: 4, alerts: 3, boosts: 1);

        $this->assertSame(3, $strength->printed);
        $this->assertSame(2, $strength->fromCardsPassed);
        $this->assertSame(2, $strength->fromAlerts);
        $this->assertSame(1, $strength->fromBoosts);
        $this->assertSame(8, $strength->total());

        $this->assertSame(
            '8 — 3 printed, +2 for cards passed, +2 from Alerts, +1 Boosted',
            $strength->explain(),
        );
    }

    /**
     * A card met first, with nothing standing, is only what it prints — and its
     * explanation says so rather than listing three zeroes.
     */
    public function test_an_untouched_card_is_only_what_it_prints(): void
    {
        $strength = ChallengeStrength::for(printed: 6, cardsPassed: 0, alerts: 0, boosts: 0);

        $this->assertSame(6, $strength->total());
        $this->assertSame('6 — 6 printed', $strength->explain());
        $this->assertSame(
            ['printed' => 6, 'cards_passed' => 0, 'alerts' => 0, 'boosts' => 0, 'total' => 6],
            $strength->toArray(),
        );
    }

    /**
     * Physical activation is free however deep the stack; a cyber card costs
     * the number of cyber cards already Active.
     */
    public function test_activating_a_physical_card_is_always_free(): void
    {
        $this->assertSame(0, ActivationCost::for(ProtectionKind::Physical, 0));
        $this->assertSame(0, ActivationCost::for(ProtectionKind::Physical, 4));
    }

    public function test_a_cyber_card_costs_the_cyber_cards_already_active(): void
    {
        $this->assertSame(0, ActivationCost::for(ProtectionKind::Cyber, 0));
        $this->assertSame(1, ActivationCost::for(ProtectionKind::Cyber, 1));
        $this->assertSame(2, ActivationCost::for(ProtectionKind::Cyber, 2));
    }

    /**
     * What a Security player needs at the start of the phase, when 3.4.5 asks
     * them to set a budget quickly: five cyber cards from cold is 0+1+2+3+4.
     */
    public function test_a_whole_cyber_stack_from_cold_is_triangular(): void
    {
        $this->assertSame(10, ActivationCost::forRemainingCyberStack(5));
        $this->assertSame(0, ActivationCost::forRemainingCyberStack(1));

        // Two more on a stack that already has three running: 3 + 4.
        $this->assertSame(7, ActivationCost::forRemainingCyberStack(2, activeCyberCards: 3));
    }

    /**
     * The Run Leader brings their full skill; everyone else brings half rounded
     * down, or a quarter rounded up while Wounded.
     */
    public function test_the_pool_is_the_leader_in_full_and_everyone_else_in_part(): void
    {
        $pool = DicePool::for(leaderSkill: 5, leaderWounded: false, others: [
            7 => ['skill' => 4, 'wounded' => false],
            9 => ['skill' => 5, 'wounded' => true],
        ]);

        $this->assertSame(5, $pool->fromLeader);
        $this->assertSame([7 => 2, 9 => 2], $pool->fromOthers);
        $this->assertSame(9, $pool->total());
        $this->assertSame(DicePool::HEALTHY_DIE, $pool->dieFaces);
    }

    /**
     * The rounding goes opposite ways, which is not a slip: a Wounded Runner
     * with a skill of 1 still brings a die where a healthy one brings none.
     */
    #[DataProvider('contributions')]
    public function test_a_supporting_runner_contributes_half_or_a_quarter(int $skill, bool $wounded, int $expected): void
    {
        $this->assertSame($expected, DicePool::contribution($skill, $wounded));
    }

    /**
     * @return array<string, array{int, bool, int}>
     */
    public static function contributions(): array
    {
        return [
            'healthy 4 gives half' => [4, false, 2],
            'healthy 5 rounds down' => [5, false, 2],
            'healthy 1 gives nothing' => [1, false, 0],
            'wounded 4 gives a quarter' => [4, true, 1],
            'wounded 5 rounds up' => [5, true, 2],
            'wounded 1 still gives a die' => [1, true, 1],
            'no skill, no dice' => [0, false, 0],
        ];
    }

    /**
     * The die size comes from the Run Leader alone: the others add dice to that
     * pool rather than bringing their own kind. Which makes a Wounded Leader
     * handing over before a hard card a real tactic.
     */
    public function test_a_wounded_leader_makes_the_whole_pool_d6s(): void
    {
        $wounded = DicePool::for(leaderSkill: 5, leaderWounded: true, others: [
            7 => ['skill' => 6, 'wounded' => false],
        ]);

        $this->assertSame(DicePool::WOUNDED_DIE, $wounded->dieFaces);
        $this->assertSame(8, $wounded->total());

        // The same group with a healthy Leader throws the same number of dice,
        // and better ones.
        $healthy = DicePool::for(leaderSkill: 5, leaderWounded: false, others: [
            7 => ['skill' => 6, 'wounded' => false],
        ]);

        $this->assertSame(DicePool::HEALTHY_DIE, $healthy->dieFaces);
        $this->assertSame(8, $healthy->total());
    }

    public function test_five_or_higher_succeeds(): void
    {
        $this->assertSame(3, DicePool::countSuccesses([5, 8, 1, 4, 6, 2]));
        $this->assertSame(0, DicePool::countSuccesses([1, 2, 3, 4]));
        $this->assertSame(0, DicePool::countSuccesses([]));
    }

    /**
     * A tie goes to Security, so the Runners have to win outright — the part
     * that would otherwise be got wrong.
     */
    public function test_a_tie_goes_to_security(): void
    {
        $this->assertTrue(DicePool::runnersWin(3, 2));
        $this->assertFalse(DicePool::runnersWin(2, 2));
        $this->assertFalse(DicePool::runnersWin(1, 2));
        $this->assertFalse(DicePool::runnersWin(0, 0));
    }

    /**
     * A run that failed still pays 1 Credit per 3 cards passed, rounding up —
     * so getting past a single card still pays something.
     */
    #[DataProvider('failedRunPayouts')]
    public function test_a_failed_run_pays_for_the_cards_it_got_past(int $cardsPassed, int $expected): void
    {
        $this->assertSame($expected, RunRewards::forFailedRun($cardsPassed));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function failedRunPayouts(): array
    {
        return [
            'stopped at the first card' => [0, 0],
            'one card' => [1, 1],
            'three cards' => [3, 1],
            'four cards' => [4, 2],
            'six cards' => [6, 2],
            'seven cards' => [7, 3],
        ];
    }
}
