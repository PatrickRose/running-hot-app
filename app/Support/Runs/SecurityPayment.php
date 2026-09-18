<?php

namespace App\Support\Runs;

use InvalidArgumentException;

/**
 * How Security is paying for something during a Run (rulebook 3.3.5, 3.4.2).
 *
 * Three purses, and which one a Credit comes out of matters:
 *
 * - **Alerts** are temporary Credits the Runners handed over by being noisy.
 *   Spending them is free in Credits and costs the Runners nothing, but it
 *   lowers the Alerts standing - which makes every card they have left easier.
 *   That trade is the decision Security is there to make.
 * - **Budget** is the escrow already taken off the Corporation when it was
 *   placed on the Facility. Spending it moves no tracker, because the Credits
 *   left the Corporation at the moment the budget was set; this only records
 *   how much of the escrow has gone.
 * - **Company money** is the Corporation's own Credits, reached past the
 *   budget. It is the one of the three that *is* a tracker movement, because
 *   Credits genuinely leave the Corporation at that moment - so it lands in the
 *   ledger like any other spend, with the Security player's name against it.
 *
 * Splitting them was not possible before: Alerts were spent first and the
 * budget covered the rest, and there was no way to reach company money at all,
 * so a Facility whose budget ran dry could not defend itself however rich the
 * Corporation was. Naming all three is what lets Security say "two Alerts and
 * the rest from the company" and mean it.
 */
readonly class SecurityPayment
{
    public function __construct(
        public int $alerts,
        public int $budget,
        public int $company,
    ) {
        // An invariant rather than a refusal a player could trip: the form
        // already refuses a negative, so reaching here means the caller built
        // one wrong. Same treatment as CardMarking's missing suit.
        if ($alerts < 0 || $budget < 0 || $company < 0) {
            throw new InvalidArgumentException(sprintf(
                'A payment cannot be negative: %d Alerts, %d budget, %d company.',
                $alerts,
                $budget,
                $company,
            ));
        }
    }

    /**
     * What the form sent, with anything left blank treated as nothing.
     */
    public static function of(?int $alerts, ?int $budget, ?int $company): self
    {
        return new self(
            alerts: $alerts ?? 0,
            budget: $budget ?? 0,
            company: $company ?? 0,
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
        return new self(alerts: 0, budget: max(0, $amount), company: 0);
    }

    public function total(): int
    {
        return $this->alerts + $this->budget + $this->company;
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
     * @return array{alerts: int, budget: int, company: int}
     */
    public function toArray(): array
    {
        return [
            'alerts' => $this->alerts,
            'budget' => $this->budget,
            'company' => $this->company,
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

        if ($this->company > 0) {
            $parts[] = sprintf('%d from the company', $this->company);
        }

        return $parts === [] ? 'nothing' : implode(' and ', $parts);
    }
}
