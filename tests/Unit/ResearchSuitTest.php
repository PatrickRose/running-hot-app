<?php

namespace Tests\Unit;

use App\Enums\ResearchSuit;
use App\Support\GamePresenter;
use Tests\TestCase;

/**
 * The four Research Point suits (rulebook 3.2.2).
 *
 * The rulebook shows these as icons and never names them in its body text, so
 * the application shows icons too. That the font can actually draw them is
 * tested in IconFontTest; this is about the suits themselves.
 */
class ResearchSuitTest extends TestCase
{
    public function test_every_suit_has_a_label_and_a_cost_column(): void
    {
        foreach (ResearchSuit::all() as $suit) {
            $this->assertNotSame('', $suit->label());
            $this->assertSame($suit->value.'_cost', $suit->costColumn());
        }
    }

    public function test_all_four_suits_are_covered(): void
    {
        $this->assertSame(
            ['cog', 'brain', 'leaf', 'maths'],
            array_map(fn (ResearchSuit $suit): string => $suit->value, ResearchSuit::all()),
        );
        $this->assertCount(4, ResearchSuit::cases());
    }

    /**
     * An icon is no use without its name: the glyph is a capital letter, so a
     * screen reader would read "A" where the page means Maths. Every caller
     * pairs the two, and the payload has to carry both for them to.
     */
    public function test_the_payload_pairs_every_glyph_with_its_name(): void
    {
        $suits = app(GamePresenter::class)->researchSuits();

        $this->assertCount(4, $suits);

        foreach ($suits as $suit) {
            $this->assertNotSame('', $suit['glyph']);
            $this->assertNotSame('', $suit['label']);
            // The name must be a name rather than the letter again, or the
            // reader is no better off.
            $this->assertNotSame($suit['glyph'], $suit['label']);
        }
    }
}
