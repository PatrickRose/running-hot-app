<?php

namespace Tests\Unit;

use App\Enums\EquationSide;
use App\Enums\ResearchCardRestriction;
use App\Enums\ResearchSuit;
use App\Support\Equation;
use App\Support\EquationCard;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The rules of an equation, held to the rulebook's own worked examples
 * (rulebook 3.2.1).
 *
 * §3.2 prints its four suits as icons that do not survive `pdftotext`, so the
 * examples below were read off the PDF itself. They are, in the book's order:
 *
 *   7 Leaf / 3 Maths
 *   6 Leaf 1 Leaf / 3 Maths 9 Maths
 *   7 Leaf 8 Leaf / 5 Maths 10 Maths
 *
 * and the scoring table, whose right-hand column is what each equation pays:
 *
 *   3 Maths / 3 Leaf                  → 3 Maths or 3 Leaf
 *   1 Maths 2 Maths / 3 Leaf 4 Leaf   → 3 Maths or 7 Leaf
 *   1 Maths 3 Maths / 2 Leaf 2 Leaf   → 4 Maths or 4 Leaf
 *   1 Maths 1 Maths / 12 Leaf 4 Leaf  → 2 Maths or 16 Leaf
 *
 * Those four are the reason scoring picks a *side* rather than a suit: you are
 * paid the total of one set, in that set's suit.
 */
class EquationTest extends TestCase
{
    public function test_the_rulebooks_example_equations_are_equations(): void
    {
        $this->assertTrue($this->equation(
            [$this->card(ResearchSuit::Leaf, 7, fromHand: true)],
            [$this->card(ResearchSuit::Maths, 3)],
        )->isValid());

        $this->assertTrue($this->equation(
            [$this->card(ResearchSuit::Leaf, 6, fromHand: true), $this->card(ResearchSuit::Leaf, 1)],
            [$this->card(ResearchSuit::Maths, 3), $this->card(ResearchSuit::Maths, 9)],
        )->isValid());

        $this->assertTrue($this->equation(
            [$this->card(ResearchSuit::Leaf, 7, fromHand: true), $this->card(ResearchSuit::Leaf, 8)],
            [$this->card(ResearchSuit::Maths, 5), $this->card(ResearchSuit::Maths, 10)],
        )->isValid());
    }

    public function test_both_sides_have_to_hold_the_same_number_of_cards(): void
    {
        $this->expectException(ValidationException::class);

        $this->equation(
            [$this->card(ResearchSuit::Leaf, 7, fromHand: true), $this->card(ResearchSuit::Leaf, 1)],
            [$this->card(ResearchSuit::Maths, 8)],
        )->validate();
    }

    public function test_every_card_in_a_set_has_to_be_the_same_suit(): void
    {
        $this->expectException(ValidationException::class);

        $this->equation(
            [$this->card(ResearchSuit::Leaf, 3, fromHand: true), $this->card(ResearchSuit::Cog, 4)],
            [$this->card(ResearchSuit::Maths, 3), $this->card(ResearchSuit::Maths, 4)],
        )->validate();
    }

    public function test_at_least_one_card_has_to_come_out_of_your_hand(): void
    {
        $this->expectException(ValidationException::class);

        $this->equation(
            [$this->card(ResearchSuit::Leaf, 3)],
            [$this->card(ResearchSuit::Maths, 3)],
        )->validate();
    }

    public function test_a_side_needs_cards_on_it(): void
    {
        $this->expectException(ValidationException::class);

        $this->equation([$this->card(ResearchSuit::Leaf, 3, fromHand: true)], [])->validate();
    }

    public function test_a_wild_card_takes_the_suit_of_the_set_it_joins(): void
    {
        $equation = $this->equation(
            [$this->card(ResearchSuit::Leaf, 3, fromHand: true), $this->card(null, 4)],
            [$this->card(ResearchSuit::Maths, 7), $this->card(ResearchSuit::Maths, 1)],
        );

        $this->assertTrue($equation->isValid());
        $this->assertSame([ResearchSuit::Leaf], $equation->suitsFor(EquationSide::Left));
    }

    public function test_a_set_of_nothing_but_wilds_may_be_any_suit(): void
    {
        $equation = $this->equation(
            [$this->card(null, 3, fromHand: true)],
            [$this->card(ResearchSuit::Cog, 3)],
        );

        $this->assertSame(ResearchSuit::all(), $equation->suitsFor(EquationSide::Left));
        $this->assertSame([ResearchSuit::Cog], $equation->suitsFor(EquationSide::Right));
    }

    public function test_a_no_single_card_cannot_be_the_only_card_in_its_set(): void
    {
        // The rulebook prints the marking on two of the cards deck
        // customisation sells and never says what it does. The designer's
        // ruling: it has to be played with another card, so its side of the
        // equation needs at least two.
        $this->expectException(ValidationException::class);

        $this->equation(
            [$this->card(ResearchSuit::Leaf, 3, fromHand: true, restriction: ResearchCardRestriction::NoSingle)],
            [$this->card(ResearchSuit::Maths, 3)],
        )->validate();
    }

    public function test_a_no_single_card_is_fine_once_it_has_company(): void
    {
        $this->assertTrue($this->equation(
            [
                $this->card(ResearchSuit::Leaf, 3, fromHand: true, restriction: ResearchCardRestriction::NoSingle),
                $this->card(ResearchSuit::Leaf, 4),
            ],
            [$this->card(ResearchSuit::Maths, 5), $this->card(ResearchSuit::Maths, 2)],
        )->isValid());
    }

    public function test_a_no_single_card_is_refused_from_either_side(): void
    {
        // Both sets hold the same number of cards, so a lone No single card on
        // the right is the same illegal equation seen from the other end - and
        // it has to be refused from there too.
        $this->expectException(ValidationException::class);

        $this->equation(
            [$this->card(ResearchSuit::Leaf, 3, fromHand: true)],
            [$this->card(ResearchSuit::Maths, 3, restriction: ResearchCardRestriction::NoSingle)],
        )->validate();
    }

    public function test_an_unmarked_card_may_be_alone_in_its_set(): void
    {
        // The rulebook's own first example is one card against one card, so the
        // marking has to be what refuses it rather than the set size.
        $this->assertTrue($this->equation(
            [$this->card(ResearchSuit::Leaf, 7, fromHand: true)],
            [$this->card(ResearchSuit::Maths, 3)],
        )->isValid());
    }

    /**
     * The scoring table on page 13, read as "this equation pays either of
     * these".
     *
     * @return array<string, array{0: array<int, array{0: ResearchSuit, 1: int}>, 1: array<int, array{0: ResearchSuit, 1: int}>, 2: int, 3: int}>
     */
    public static function scoringExamples(): array
    {
        return [
            '3 Maths / 3 Leaf' => [
                [[ResearchSuit::Maths, 3]],
                [[ResearchSuit::Leaf, 3]],
                3,
                3,
            ],
            '1 2 Maths / 3 4 Leaf' => [
                [[ResearchSuit::Maths, 1], [ResearchSuit::Maths, 2]],
                [[ResearchSuit::Leaf, 3], [ResearchSuit::Leaf, 4]],
                3,
                7,
            ],
            '1 3 Maths / 2 2 Leaf' => [
                [[ResearchSuit::Maths, 1], [ResearchSuit::Maths, 3]],
                [[ResearchSuit::Leaf, 2], [ResearchSuit::Leaf, 2]],
                4,
                4,
            ],
            '1 1 Maths / 12 4 Leaf' => [
                [[ResearchSuit::Maths, 1], [ResearchSuit::Maths, 1]],
                [[ResearchSuit::Leaf, 12], [ResearchSuit::Leaf, 4]],
                2,
                16,
            ],
        ];
    }

    /**
     * @param  array<int, array{0: ResearchSuit, 1: int}>  $left
     * @param  array<int, array{0: ResearchSuit, 1: int}>  $right
     */
    #[DataProvider('scoringExamples')]
    public function test_an_equation_pays_the_total_of_whichever_side_you_take_it_from(
        array $left,
        array $right,
        int $leftPays,
        int $rightPays,
    ): void {
        $equation = $this->equation(
            array_map(fn (array $card): EquationCard => $this->card($card[0], $card[1], fromHand: true), $left),
            array_map(fn (array $card): EquationCard => $this->card($card[0], $card[1]), $right),
        );

        $this->assertSame($leftPays, $equation->scoreFor(EquationSide::Left));
        $this->assertSame($rightPays, $equation->scoreFor(EquationSide::Right));
    }

    public function test_the_balanced_bonus_is_the_triangular_numbers(): void
    {
        // "1 on both sides: 1 ... 2: 3 ... 3: 6 ... 4: 10 ... And so on."
        foreach ([1 => 1, 2 => 3, 3 => 6, 4 => 10, 5 => 15, 8 => 36] as $perSide => $bonus) {
            $cards = fn (ResearchSuit $suit): array => array_map(
                fn (): EquationCard => $this->card($suit, 2, fromHand: true),
                range(1, $perSide),
            );

            $equation = $this->equation($cards(ResearchSuit::Leaf), $cards(ResearchSuit::Maths));

            $this->assertTrue($equation->isBalanced());
            $this->assertSame($bonus, $equation->balancedBonus(), $perSide.' cards a side');
        }
    }

    public function test_an_unbalanced_equation_pays_no_bonus(): void
    {
        $equation = $this->equation(
            [$this->card(ResearchSuit::Leaf, 7, fromHand: true)],
            [$this->card(ResearchSuit::Maths, 3)],
        );

        $this->assertFalse($equation->isBalanced());
        $this->assertSame(0, $equation->balancedBonus());
        $this->assertSame(
            [
                ResearchSuit::Cog->value => 0,
                ResearchSuit::Brain->value => 0,
                ResearchSuit::Leaf->value => 7,
                ResearchSuit::Maths->value => 0,
            ],
            $equation->award(EquationSide::Left, ResearchSuit::Leaf),
        );
    }

    public function test_the_bonus_may_be_split_across_the_suits_the_equation_used(): void
    {
        $equation = $this->equation(
            [$this->card(ResearchSuit::Leaf, 2, fromHand: true), $this->card(ResearchSuit::Leaf, 2)],
            [$this->card(ResearchSuit::Maths, 3), $this->card(ResearchSuit::Maths, 1)],
        );

        $this->assertSame(3, $equation->balancedBonus());

        $award = $equation->award(EquationSide::Left, ResearchSuit::Leaf, [
            ResearchSuit::Leaf->value => 1,
            ResearchSuit::Maths->value => 2,
        ]);

        // Four for the set, plus its share of the bonus.
        $this->assertSame(5, $award[ResearchSuit::Leaf->value]);
        $this->assertSame(2, $award[ResearchSuit::Maths->value]);
    }

    public function test_the_whole_bonus_has_to_be_taken_somewhere(): void
    {
        $equation = $this->equation(
            [$this->card(ResearchSuit::Leaf, 2, fromHand: true), $this->card(ResearchSuit::Leaf, 2)],
            [$this->card(ResearchSuit::Maths, 3), $this->card(ResearchSuit::Maths, 1)],
        );

        $this->expectException(ValidationException::class);

        $equation->award(EquationSide::Left, ResearchSuit::Leaf, [
            ResearchSuit::Leaf->value => 1,
        ]);
    }

    public function test_the_bonus_cannot_be_taken_in_a_suit_the_equation_did_not_use(): void
    {
        $equation = $this->equation(
            [$this->card(ResearchSuit::Leaf, 2, fromHand: true), $this->card(ResearchSuit::Leaf, 2)],
            [$this->card(ResearchSuit::Maths, 3), $this->card(ResearchSuit::Maths, 1)],
        );

        $this->expectException(ValidationException::class);

        $equation->award(EquationSide::Left, ResearchSuit::Leaf, [
            ResearchSuit::Cog->value => 3,
        ]);
    }

    public function test_points_cannot_be_taken_in_a_suit_that_side_is_not(): void
    {
        $equation = $this->equation(
            [$this->card(ResearchSuit::Leaf, 7, fromHand: true)],
            [$this->card(ResearchSuit::Maths, 3)],
        );

        $this->expectException(ValidationException::class);

        $equation->award(EquationSide::Left, ResearchSuit::Maths);
    }

    /**
     * @param  array<int, EquationCard>  $left
     * @param  array<int, EquationCard>  $right
     */
    private function equation(array $left, array $right): Equation
    {
        return new Equation($left, $right);
    }

    private function card(
        ?ResearchSuit $suit,
        int $value,
        bool $fromHand = false,
        ?ResearchCardRestriction $restriction = null,
    ): EquationCard {
        return new EquationCard($suit, $value, $fromHand, $restriction);
    }
}
