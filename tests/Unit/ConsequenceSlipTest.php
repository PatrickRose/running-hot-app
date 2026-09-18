<?php

namespace Tests\Unit;

use App\Enums\RunConsequence;
use App\Support\Runs\ConsequenceSlip;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * What the Runners are about to take, written down before anybody takes it
 * (rulebook 3.4.2).
 */
class ConsequenceSlipTest extends TestCase
{
    public function test_a_blank_box_is_not_a_consequence_happening_no_times(): void
    {
        $slip = ConsequenceSlip::of(['wound' => 1, 'tag' => 0, 'alert' => 0]);

        $this->assertSame(['wound' => 1], $slip->toArray());
        $this->assertFalse($slip->has(RunConsequence::Tag));
    }

    public function test_an_effect_it_does_not_know_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConsequenceSlip::of(['explosion' => 1]);
    }

    /**
     * How an Alert purchase lands on a slip Security has already marked.
     */
    public function test_buying_one_more_adds_to_what_is_there(): void
    {
        $slip = ConsequenceSlip::of(['wound' => 1])
            ->plus(RunConsequence::Wound)
            ->plus(RunConsequence::Tag);

        $this->assertSame(2, $slip->timesOf(RunConsequence::Wound));
        $this->assertSame(1, $slip->timesOf(RunConsequence::Tag));
    }

    /**
     * The End the Run is not applied with the rest: it is the question the Run
     * Leader answers, so it is held back from the damage.
     */
    public function test_the_end_the_run_is_kept_out_of_the_damage(): void
    {
        $slip = ConsequenceSlip::of(['wound' => 2, 'end_the_run' => 1]);

        $this->assertTrue($slip->endsTheRun());
        $this->assertSame(
            [['effect' => RunConsequence::Wound, 'times' => 2]],
            $slip->damage(),
        );
    }

    /**
     * Read in the enum's own order rather than the order the boxes were filled
     * in, so the ledger reads the way the cards print it.
     */
    public function test_it_reads_in_the_order_a_card_prints_it(): void
    {
        $slip = ConsequenceSlip::of(['wound' => 1, 'alert' => 2]);

        $this->assertSame('2 Alerts, 1 Wound', $slip->describe());
    }

    /**
     * Retry and End the Run are not counted in the sentence: a card says "2
     * alerts, 1 wound" and never "two Retries".
     */
    public function test_the_uncountable_ones_are_not_counted(): void
    {
        $slip = ConsequenceSlip::of(['retry' => 1, 'end_the_run' => 1]);

        $this->assertSame('Retry, End the Run', $slip->describe());
    }

    public function test_an_empty_slip_says_so(): void
    {
        $slip = ConsequenceSlip::empty();

        $this->assertTrue($slip->isEmpty());
        $this->assertSame('nothing', $slip->describe());
        $this->assertSame([], $slip->damage());
    }
}
