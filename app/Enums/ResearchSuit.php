<?php

namespace App\Enums;

/**
 * The four types of Research Point a technology is priced in (rulebook 3.2.2).
 *
 * The rulebook prints these as four icons and never names them in the body text,
 * so the names here are the game's own from its card sheet. They are also the
 * four suits of the research card game (3.2.1), which is where the points come
 * from - the same four columns run through both halves of the research sub-game.
 *
 * Because the rulebook shows them as icons, so does the application: see
 * glyph(), and the font it belongs to in resources/css/app.css.
 *
 * A technology may cost nothing in a suit, and most cost nothing in at least one
 * of them - 113 of the 144 in the game's own list - so a missing cost is zero
 * rather than an omission.
 */
enum ResearchSuit: string
{
    case Cog = 'cog';

    case Brain = 'brain';

    case Leaf = 'leaf';

    case Maths = 'maths';

    public function label(): string
    {
        return match ($this) {
            self::Cog => 'Cog',
            self::Brain => 'Brain',
            self::Leaf => 'Leaf',
            self::Maths => 'Maths',
        };
    }

    /**
     * The character that draws this suit's icon in the game's own icon font.
     *
     * The font maps each icon onto an ASCII letter rather than onto a symbol
     * codepoint, so a suit's "glyph" is a plain capital letter and only means
     * anything while the font is loaded. Which is why every caller pairs it with
     * the label: a browser that has not got the font shows a bare "B", and a
     * screen reader would read one out.
     *
     * The four suits are the first four letters, in the order the designer drew
     * them - A is the calculator, B the brain, C the leaf, D the cog. The font
     * carries about thirty more icons on the remaining letters and digits, none
     * of which this application has a use for yet.
     */
    public function glyph(): string
    {
        return match ($this) {
            self::Maths => 'A',
            self::Brain => 'B',
            self::Leaf => 'C',
            self::Cog => 'D',
        };
    }

    /**
     * The column holding a cost in this suit.
     */
    public function costColumn(): string
    {
        return $this->value.'_cost';
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return [self::Cog, self::Brain, self::Leaf, self::Maths];
    }
}
