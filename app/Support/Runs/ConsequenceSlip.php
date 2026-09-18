<?php

namespace App\Support\Runs;

use App\Enums\RunConsequence;
use App\Services\RunEngine;
use App\Support\Equation;
use InvalidArgumentException;

/**
 * What the Runners are about to take, written down before anybody takes it
 * (rulebook 3.4.2).
 *
 * A card's consequence is the sentence it prints - "1 alert, 1 wound", "2 tag,
 * end the run" - so it is a set of effects with counts rather than one thing.
 * Two people fill this in between them: Security reads it off the card they are
 * holding, and then spends Alerts to add to it. The Run Leader reads the total
 * and decides who takes it.
 *
 * Pure, for the reason {@see Equation} is: the adding up is small
 * but it is written down in two places otherwise, and a slip that says one
 * thing on screen and another in the ledger would be the worst kind of bug on a
 * run.
 */
readonly class ConsequenceSlip
{
    /**
     * @param  array<string, int>  $counts  times per RunConsequence value, all at least 1
     */
    private function __construct(public array $counts)
    {
        foreach ($counts as $value => $times) {
            if (RunConsequence::tryFrom($value) === null) {
                throw new InvalidArgumentException(sprintf('%s is not a consequence.', $value));
            }

            if ($times < 1) {
                throw new InvalidArgumentException(sprintf(
                    'A slip carries a consequence at least once, not %d of %s.',
                    $times,
                    $value,
                ));
            }
        }
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param  array<string, int|numeric-string>  $counts
     */
    public static function of(array $counts): self
    {
        $kept = [];

        foreach ($counts as $value => $times) {
            $times = (int) $times;

            // A zero is a box somebody left alone rather than a consequence
            // happening no times, so it is dropped at the door. The constructor
            // still refuses one, because a zero reaching it means a caller
            // built the slip wrong.
            if ($times > 0) {
                $kept[$value] = $times;
            }
        }

        return new self($kept);
    }

    /**
     * One more of an effect, which is how an Alert purchase lands on a slip
     * Security has already marked.
     */
    public function plus(RunConsequence $effect, int $times = 1): self
    {
        $counts = $this->counts;
        $counts[$effect->value] = ($counts[$effect->value] ?? 0) + $times;

        return new self($counts);
    }

    public function timesOf(RunConsequence $effect): int
    {
        return $this->counts[$effect->value] ?? 0;
    }

    public function has(RunConsequence $effect): bool
    {
        return $this->timesOf($effect) > 0;
    }

    /**
     * Whether the run stops here unless the Runners buy their way past it.
     */
    public function endsTheRun(): bool
    {
        return $this->has(RunConsequence::EndTheRun);
    }

    public function isEmpty(): bool
    {
        return $this->counts === [];
    }

    /**
     * Everything on the slip *except* the End the Run, which is not applied
     * like the others: it is a question for the Run Leader, and the answer is
     * either the run stopping or {@see RunEngine::ignoreEndTheRun()}.
     *
     * @return array<int, array{effect: RunConsequence, times: int}>
     */
    public function damage(): array
    {
        $parts = [];

        // Read in the enum's own order rather than the order the boxes were
        // filled in, so the ledger reads the way the cards print it: Alerts,
        // then what lands on a Runner, then the Retry that sends them back.
        foreach (RunConsequence::cases() as $effect) {
            if ($effect !== RunConsequence::EndTheRun && $this->has($effect)) {
                $parts[] = ['effect' => $effect, 'times' => $this->timesOf($effect)];
            }
        }

        return $parts;
    }

    /**
     * The slip in the card's own words.
     */
    public function describe(): string
    {
        $parts = [];

        foreach (RunConsequence::cases() as $effect) {
            if (! $this->has($effect)) {
                continue;
            }

            $times = $this->timesOf($effect);

            $parts[] = $effect === RunConsequence::EndTheRun || $effect === RunConsequence::Retry
                ? $effect->label()
                : sprintf('%d %s%s', $times, $effect->label(), $times === 1 ? '' : 's');
        }

        return $parts === [] ? 'nothing' : implode(', ', $parts);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->counts;
    }
}
