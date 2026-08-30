<?php

namespace App\Support;

/**
 * The game's own icon font, and what each of its icons means.
 *
 * It is an icon font rather than a symbol font: every icon is drawn by an ASCII
 * capital, so a "glyph" here is a plain letter and only reads as a picture while
 * the font is loaded. That is why nothing shows one without its name beside it -
 * a browser without the font renders a bare "E", and a screen reader would read
 * that letter out. See resources/css/app.css for the face itself.
 *
 * The meanings are the designer's, not a reading of the artwork: the glyph names
 * inside the font are only the letters, so there is nothing in the file itself
 * that says what any of them is. Written down here because that knowledge lives
 * nowhere else, and working it out again means rendering the font and looking.
 *
 * In use:
 *
 * | A | Maths           | research suit  | App\Enums\ResearchSuit
 * | B | Brain           | research suit  | App\Enums\ResearchSuit
 * | C | Leaf            | research suit  | App\Enums\ResearchSuit
 * | D | Cog             | research suit  | App\Enums\ResearchSuit
 * | E | Physical        | Protection Card kind | App\Enums\ProtectionKind
 * | F | Cyber           | Protection Card kind | App\Enums\ProtectionKind
 * | H | Permanent       | equipment category   | App\Enums\EquipmentCategory
 * | I | Single use      | equipment category   | App\Enums\EquipmentCategory
 * | J | This run        | equipment category   | App\Enums\EquipmentCategory
 *
 * Drawn but not used yet:
 *
 * | G | Boost           | belongs to Runs, which are not built
 * | Y | Wildcard        | belongs to the research game, which is not built
 *
 * The font carries about twenty more icons on the remaining capitals. They are
 * not used in the game, so they have no meaning to record. It also carries
 * styled numerals on 0-9, and a single "10" on Z.
 *
 * N is the one capital the font has no icon for at all, which makes it a useful
 * canary: see tests/Unit/IconFontTest.php, where it proves the font reader can
 * actually report a missing glyph.
 */
class IconFont
{
    public const MATHS = 'A';

    public const BRAIN = 'B';

    public const LEAF = 'C';

    public const COG = 'D';

    public const PHYSICAL = 'E';

    public const CYBER = 'F';

    /**
     * The third equipment category: an item equipped before the Run that stays
     * with the Runner (rulebook 3.4.1).
     */
    public const PERMANENT = 'H';

    public const SINGLE_USE = 'I';

    public const THIS_RUN = 'J';

    /**
     * Spends and effects in a Run (rulebook 3.4.2). Nothing shows it yet.
     */
    public const BOOST = 'G';

    /**
     * A card that counts as any research suit, for the research game of 3.2.1.
     * Nothing shows it yet.
     */
    public const WILDCARD = 'Y';

    /**
     * Every glyph the application actually puts on a page.
     *
     * The two drawn-but-unused icons are deliberately absent: a test asserts the
     * font can draw everything in here, and holding it to icons nothing shows
     * would be asserting against a page nobody can look at.
     *
     * @return array<string, string> meaning, keyed by glyph
     */
    public static function inUse(): array
    {
        return [
            self::MATHS => 'Maths',
            self::BRAIN => 'Brain',
            self::LEAF => 'Leaf',
            self::COG => 'Cog',
            self::PHYSICAL => 'Physical',
            self::CYBER => 'Cyber',
            self::PERMANENT => 'Permanent',
            self::SINGLE_USE => 'Single use',
            self::THIS_RUN => 'This run',
        ];
    }
}
