<?php

namespace Tests\Unit;

use App\Support\Runs\SecurityPayment;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The two purses a Security player pays from (rulebook 3.3.5, 3.4.2).
 */
class SecurityPaymentTest extends TestCase
{
    public function test_it_adds_the_two_purses_together(): void
    {
        $payment = new SecurityPayment(alerts: 2, budget: 3);

        $this->assertSame(5, $payment->total());
        $this->assertFalse($payment->isEmpty());
        $this->assertSame(
            ['alerts' => 2, 'budget' => 3],
            $payment->toArray(),
        );
    }

    /**
     * Naming nothing is how a caller says "the usual" rather than "pay
     * nothing", which is what lets the budget stay the default purse.
     */
    public function test_naming_nothing_is_empty_rather_than_zero_of_each(): void
    {
        $this->assertTrue(SecurityPayment::of(null, null)->isEmpty());
        $this->assertFalse(SecurityPayment::of(0, 1)->isEmpty());
    }

    public function test_the_budget_is_the_default_purse(): void
    {
        $payment = SecurityPayment::fromBudget(4);

        $this->assertSame(0, $payment->alerts);
        $this->assertSame(4, $payment->budget);
    }

    /**
     * An invariant rather than a refusal a player could trip: the form already
     * refuses a negative, so reaching here means a caller built one wrong.
     */
    public function test_a_negative_purse_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SecurityPayment(alerts: -1, budget: 0);
    }

    /**
     * The sentence names only the purses actually used, because "2 Alerts and
     * 0 from the budget" is a worse way of saying two Alerts.
     */
    public function test_it_explains_only_what_was_used(): void
    {
        $this->assertSame('2 Alerts', (new SecurityPayment(2, 0))->explain());
        $this->assertSame('1 Alert and 3 from the budget', (new SecurityPayment(1, 3))->explain());
        $this->assertSame('3 from the budget', (new SecurityPayment(0, 3))->explain());
        $this->assertSame('nothing', (new SecurityPayment(0, 0))->explain());
    }
}
