<?php

namespace App\Support;

use App\Enums\EquationSide;
use App\Enums\ResearchSuit;
use Illuminate\Validation\ValidationException;

/**
 * The rules of an equation, and what one is worth (rulebook 3.2.1).
 *
 * This is the whole of the research game's arithmetic, and it is deliberately
 * pure: cards in, points out, no database and no player. The reason is the same
 * one that keeps the Protection Card move cost in one method - the rules here
 * are subtle enough to drift if they are written down twice, and the rulebook's
 * own worked examples are the tests.
 *
 * An equation is two sets of cards where every card in a set shares one suit
 * (wild cards taking whichever the set needs) and both sets hold the same
 * number of cards. It is written "7 Leaf / 3 Maths", which is why the two sets
 * are called left and right rather than being numbered.
 *
 * A card may also carry a marking that limits how it is played: a No single
 * card cannot be the only card in its set. That is the one rule here the
 * rulebook does not state - it prints the marking on two of the cards deck
 * customisation sells and never says what it means - so it is the designer's
 * ruling rather than a reading, and it lives in
 * App\Enums\ResearchCardRestriction.
 *
 * Scoring is two separate payments and they follow different rules:
 *
 * - The equation itself pays "in one of the suits that you used ... equal to
 *   the sum of the card values of that set". So you pick a side, and you are
 *   paid that side's total in that side's suit. Picking a side rather than
 *   naming a suit is what makes the rulebook's four examples come out right:
 *   1 + 2 Maths against 3 + 4 Leaf pays either 3 Maths or 7 Leaf.
 * - A balanced equation - both sides summing to the same total - pays a bonus
 *   on top, by cards per side: 1, 3, 6, 10, "and so on". Those are the
 *   triangular numbers, so "and so on" is n(n+1)/2 rather than a table that
 *   runs out. The bonus may be split "in any combination of suits that you
 *   used", which is why it is allocated rather than paid in one suit.
 *
 * Nothing here knows where a card came from beyond whether it was in a hand,
 * which the "at least one card from your hand" rule needs. Whose hand, and
 * whether that player's turn it is, belong to App\Services\ResearchTableService.
 */
class Equation
{
    /**
     * @param  array<int, EquationCard>  $left
     * @param  array<int, EquationCard>  $right
     */
    public function __construct(
        private readonly array $left,
        private readonly array $right,
    ) {}

    /**
     * @return array<int, EquationCard>
     */
    public function side(EquationSide $side): array
    {
        return array_values($side === EquationSide::Left ? $this->left : $this->right);
    }

    /**
     * Refuse anything that is not an equation (rulebook 3.2.1).
     *
     * Every failure is reported against `equation`, because the page shows one
     * builder rather than two fields: a player is told what is wrong with the
     * thing they are holding, not which half of it.
     */
    public function validate(): void
    {
        foreach (EquationSide::all() as $side) {
            if ($this->side($side) === []) {
                throw ValidationException::withMessages([
                    'equation' => 'An equation needs cards on both sides.',
                ]);
            }
        }

        if (count($this->left) !== count($this->right)) {
            throw ValidationException::withMessages([
                'equation' => sprintf(
                    'Both sides need the same number of cards: %d against %d.',
                    count($this->left),
                    count($this->right),
                ),
            ]);
        }

        foreach (EquationSide::all() as $side) {
            if ($this->suitsFor($side) === []) {
                throw ValidationException::withMessages([
                    'equation' => sprintf(
                        'The %s side mixes suits. Every card in a set has to be the same type of research.',
                        strtolower($side->label()),
                    ),
                ]);
            }
        }

        foreach (EquationSide::all() as $side) {
            $cards = $this->side($side);

            foreach ($cards as $card) {
                $minimum = $card->restriction?->minimumSetSize() ?? 1;

                if (count($cards) < $minimum) {
                    throw ValidationException::withMessages([
                        'equation' => sprintf(
                            'A card marked "%s" needs at least %d cards in its set: %s',
                            $card->restriction->label(),
                            $minimum,
                            lcfirst($card->restriction->description()),
                        ),
                    ]);
                }
            }
        }

        if (! $this->usesAHandCard()) {
            throw ValidationException::withMessages([
                'equation' => 'An equation has to use at least one card from your hand.',
            ]);
        }
    }

    /**
     * Whether this equation could be played at all.
     */
    public function isValid(): bool
    {
        try {
            $this->validate();
        } catch (ValidationException) {
            return false;
        }

        return true;
    }

    /**
     * The suits one side may count as.
     *
     * One suit for a set with any real card in it, all four for a set that is
     * nothing but wilds, and none at all for a set that mixes suits - which is
     * how validate() spots an illegal set.
     *
     * @return array<int, ResearchSuit>
     */
    public function suitsFor(EquationSide $side): array
    {
        $suits = [];

        foreach ($this->side($side) as $card) {
            if ($card->suit !== null) {
                $suits[$card->suit->value] = $card->suit;
            }
        }

        if ($suits === []) {
            return $this->side($side) === [] ? [] : ResearchSuit::all();
        }

        return count($suits) === 1 ? array_values($suits) : [];
    }

    /**
     * Every suit this equation used, which is what the bonus may be split
     * across.
     *
     * @return array<int, ResearchSuit>
     */
    public function suitsUsed(): array
    {
        $suits = [];

        foreach (EquationSide::all() as $side) {
            foreach ($this->suitsFor($side) as $suit) {
                $suits[$suit->value] = $suit;
            }
        }

        return array_values($suits);
    }

    public function sumFor(EquationSide $side): int
    {
        return array_sum(array_map(
            fn (EquationCard $card): int => $card->value,
            $this->side($side),
        ));
    }

    /**
     * How many cards are on a side. Both sides hold the same number, so a valid
     * equation has one answer.
     */
    public function cardsPerSide(): int
    {
        return max(count($this->left), count($this->right));
    }

    /**
     * Whether both sides total the same, which is what earns the bonus.
     */
    public function isBalanced(): bool
    {
        return $this->sumFor(EquationSide::Left) === $this->sumFor(EquationSide::Right);
    }

    /**
     * The bonus a balanced equation pays (rulebook 3.2.1).
     *
     * 1, 3, 6, 10 for one to four cards a side, and then "and so on" - the
     * triangular numbers, extended by formula rather than by a table, because
     * deck customisation adds cards and nothing caps how wide an equation can
     * get.
     */
    public function balancedBonus(): int
    {
        if (! $this->isBalanced()) {
            return 0;
        }

        $cards = $this->cardsPerSide();

        return (int) ($cards * ($cards + 1) / 2);
    }

    /**
     * What one side pays if it is the side you take your points from.
     */
    public function scoreFor(EquationSide $side): int
    {
        return $this->sumFor($side);
    }

    /**
     * Check a proposed payout, and return it as points by suit.
     *
     * Both halves are checked here rather than at the call site so that the
     * split bonus cannot quietly pay out more than it is worth: an allocation
     * naming a suit the equation never used, or adding up to the wrong total,
     * is refused.
     *
     * @param  array<string, int>  $bonusAllocation  points by suit value
     * @return array<string, int> points by suit value, the equation and the
     *                            bonus added together
     */
    public function award(EquationSide $side, ResearchSuit $suit, array $bonusAllocation = []): array
    {
        $available = $this->suitsFor($side);

        if (! in_array($suit, $available, true)) {
            throw ValidationException::withMessages([
                'suit' => sprintf(
                    'The %s side of this equation is not %s.',
                    strtolower($side->label()),
                    $suit->label(),
                ),
            ]);
        }

        $bonus = $this->balancedBonus();
        $allocated = 0;
        $used = array_map(fn (ResearchSuit $used): string => $used->value, $this->suitsUsed());

        foreach ($bonusAllocation as $suitValue => $points) {
            if ($points < 0) {
                throw ValidationException::withMessages([
                    'bonus' => 'A share of the bonus cannot be negative.',
                ]);
            }

            if ($points > 0 && ! in_array($suitValue, $used, true)) {
                throw ValidationException::withMessages([
                    'bonus' => sprintf(
                        'The bonus can only be taken in a suit this equation used, and it did not use %s.',
                        ResearchSuit::from($suitValue)->label(),
                    ),
                ]);
            }

            $allocated += $points;
        }

        if ($allocated !== $bonus) {
            throw ValidationException::withMessages([
                'bonus' => $bonus === 0
                    ? 'This equation is not balanced, so it pays no bonus.'
                    : sprintf('The %d bonus point(s) all have to be taken somewhere.', $bonus),
            ]);
        }

        $award = [];

        foreach (ResearchSuit::all() as $each) {
            $award[$each->value] = ($each === $suit ? $this->scoreFor($side) : 0)
                + (int) ($bonusAllocation[$each->value] ?? 0);
        }

        return $award;
    }

    /**
     * Whether at least one card came out of the player's own hand.
     */
    private function usesAHandCard(): bool
    {
        foreach ([...$this->left, ...$this->right] as $card) {
            if ($card->fromHand) {
                return true;
            }
        }

        return false;
    }
}
