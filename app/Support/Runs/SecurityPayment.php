<?php

namespace App\Support\Runs;

use App\Services\FacilityDefenceService;
use InvalidArgumentException;

/**
 * How Security is paying for something during a Run (rulebook 3.3.5, 3.4.2).
 *
 * Two purses, and which one a Credit comes out of matters:
 *
 * - **Alerts** are temporary Credits the Runners handed over by being noisy.
 *   Spending them is free in Credits and costs the Runners nothing, but it
 *   lowers the Alerts standing - which makes every card they have left easier.
 *   That trade is the decision Security is there to make, and it is why every
 *   cost on the screen is a slider rather than a button.
 * - **Budget** is the escrow already taken off the Corporation when it was
 *   placed on the Facility. Spending it moves no tracker, because the Credits
 *   left the Corporation at the moment the budget was set; this only records
 *   how much of the escrow has gone.
 *
 * There is deliberately no third purse for the Corporation's own Credits. A
 * Facility is defended out of what has been put on it, and a budget that has
 * run dry is a decision that has already been made - so company money reaches a
 * Run by *raising the budget*, which escrows it in the open through
 * {@see FacilityDefenceService::setSecurityBudget()} and lands in
 * the ledger there. Letting a payment reach past the budget put the same
 * Credits in two places at once: spent here, and still promised to whatever
 * else the Facility was funded for.
 */
readonly class SecurityPayment
{
    public function __construct(
        public int $alerts,
        public int $budget,
    ) {
        // An invariant rather than a refusal a player could trip: the form
        // already refuses a negative, so reaching here means the caller built
        // one wrong. Same treatment as CardMarking's missing suit.
        if ($alerts < 0 || $budget < 0) {
            throw new InvalidArgumentException(sprintf(
                'A payment cannot be negative: %d Alerts, %d budget.',
                $alerts,
                $budget,
            ));
        }
    }

    /**
     * What the form sent, with anything left blank treated as nothing.
     */
    public static function of(?int $alerts, ?int $budget): self
    {
        return new self(
            alerts: $alerts ?? 0,
            budget: $budget ?? 0,
        );
    }

    /**
     * The whole cost off the Facility's budget.
     *
     * The default everywhere a caller does not say otherwise, because the
     * budget is what a Facility is funded with: a Security player who has
     * placed Credits on a Facility and then switches a card on there means the
     * budget unless they say something else.
     */
    public static function fromBudget(int $amount): self
    {
        return new self(alerts: 0, budget: max(0, $amount));
    }

    public function total(): int
    {
        return $this->alerts + $this->budget;
    }

    /**
     * Whether nothing at all has been named, which is how a caller says "the
     * usual" rather than "pay nothing".
     */
    public function isEmpty(): bool
    {
        return $this->total() === 0;
    }

    /**
     * @return array{alerts: int, budget: int}
     */
    public function toArray(): array
    {
        return [
            'alerts' => $this->alerts,
            'budget' => $this->budget,
        ];
    }

    /**
     * The same thing in a sentence, naming only the purses actually used.
     */
    public function explain(): string
    {
        $parts = [];

        if ($this->alerts > 0) {
            $parts[] = sprintf('%d Alert%s', $this->alerts, $this->alerts === 1 ? '' : 's');
        }

        if ($this->budget > 0) {
            $parts[] = sprintf('%d from the budget', $this->budget);
        }

        return $parts === [] ? 'nothing' : implode(' and ', $parts);
    }
}
