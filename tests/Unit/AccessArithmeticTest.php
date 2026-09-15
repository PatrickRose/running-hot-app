<?php

namespace Tests\Unit;

use App\Support\Runs\AccessCheck;
use App\Support\Runs\RunRewards;
use PHPUnit\Framework\TestCase;

/**
 * The sums behind an access (rulebook 3.4.3).
 *
 * Every figure here is printed in the rulebook, so the tests are written
 * against its own sentences rather than against the implementation. Where the
 * book says "and so on" the continuation is named in the test that covers it.
 */
class AccessArithmeticTest extends TestCase
{
    /**
     * "5 cards or less: the number of cards. 6 cards: 7 Credits. 7 cards: 8.
     * 8 cards: 10. 9 cards: 12. 10 cards: 15."
     *
     * A printed list rather than a curve - the steps are 1, 2, 2, 3, 3 - so it
     * is checked row by row.
     */
    public function test_the_credits_card_pays_for_the_protection_cards_standing(): void
    {
        foreach ([0, 1, 2, 3, 4, 5] as $cards) {
            $this->assertSame($cards, RunRewards::fromProtectionCards($cards));
        }

        $this->assertSame(7, RunRewards::fromProtectionCards(6));
        $this->assertSame(8, RunRewards::fromProtectionCards(7));
        $this->assertSame(10, RunRewards::fromProtectionCards(8));
        $this->assertSame(12, RunRewards::fromProtectionCards(9));
        $this->assertSame(15, RunRewards::fromProtectionCards(10));
    }

    /**
     * "11+ cards: an additional 3 Credits for each card over 10 (so 11 gives
     * you 18, 12 gives you 21 and so on)."
     */
    public function test_the_credits_card_keeps_paying_past_ten_cards(): void
    {
        $this->assertSame(18, RunRewards::fromProtectionCards(11));
        $this->assertSame(21, RunRewards::fromProtectionCards(12));
        $this->assertSame(30, RunRewards::fromProtectionCards(15));
    }

    /**
     * "1 technology: 0. 2: 1. 3: 3. 4: 6. 5: 10. And so on." - the triangular
     * numbers, and counted "including the Credits card", so a Facility storing
     * one technology is the two-technology row.
     */
    public function test_the_credits_card_pays_for_what_is_stored(): void
    {
        $this->assertSame(0, RunRewards::fromTechnologies(0));
        $this->assertSame(1, RunRewards::fromTechnologies(1));
        $this->assertSame(3, RunRewards::fromTechnologies(2));
        $this->assertSame(6, RunRewards::fromTechnologies(3));
        $this->assertSame(10, RunRewards::fromTechnologies(4));

        // And so on: six technologies stored is seven counted, T(6) = 21.
        $this->assertSame(21, RunRewards::fromTechnologies(6));
    }

    public function test_the_two_halves_of_the_credits_card_add_together(): void
    {
        // Eight Protection Cards standing is 10, and three technologies stored
        // is four counted, which is 6.
        $this->assertSame(16, RunRewards::forCreditsCard(protectionCards: 8, technologies: 3));
    }

    /**
     * "If you roll no successes, your copy attempt fails. If you roll some
     * successes, you create a weak copy (25% discount). If you roll four
     * successes, you create a good copy (50% discount)."
     *
     * Null is a failed attempt rather than a copy worth nothing: a failure
     * leaves the card on the list to be tried again.
     */
    public function test_a_copy_is_worth_what_the_dice_bought(): void
    {
        $this->assertNull(AccessCheck::copyDiscount(0));
        $this->assertSame(25, AccessCheck::copyDiscount(1));
        $this->assertSame(25, AccessCheck::copyDiscount(3));
        $this->assertSame(50, AccessCheck::copyDiscount(4));
        $this->assertSame(50, AccessCheck::copyDiscount(9));
    }

    /**
     * Destroying has four printed bands at 2, 4, 8 and 12 successes, and only
     * the last one takes the technology away: everything below it "leaves
     * traces" the Corporation can research again at a discount.
     */
    public function test_destroying_reads_off_four_bands_and_only_the_last_is_total(): void
    {
        $this->assertNull(AccessCheck::destroyBand(1));
        $this->assertSame(2, AccessCheck::destroyBand(2));
        $this->assertSame(2, AccessCheck::destroyBand(3));
        $this->assertSame(4, AccessCheck::destroyBand(4));
        $this->assertSame(4, AccessCheck::destroyBand(7));
        $this->assertSame(8, AccessCheck::destroyBand(8));
        $this->assertSame(12, AccessCheck::destroyBand(12));

        $this->assertFalse(AccessCheck::destroyIsTotal(null));
        $this->assertFalse(AccessCheck::destroyIsTotal(2));
        $this->assertFalse(AccessCheck::destroyIsTotal(8));
        $this->assertTrue(AccessCheck::destroyIsTotal(12));
    }

    /**
     * Stealing is the one access with a score to beat rather than bands, and
     * the game runs it at 8 for every technology.
     */
    public function test_stealing_beats_a_flat_score_of_eight(): void
    {
        $this->assertSame(8, AccessCheck::STEAL_STRENGTH);

        $this->assertFalse(AccessCheck::stealSucceeds(7));
        $this->assertTrue(AccessCheck::stealSucceeds(8));
        $this->assertTrue(AccessCheck::stealSucceeds(11));
    }
}
