<?php

namespace App\Enums;

use App\Support\IconFont;

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
     * The character that draws this suit's icon (see App\Support\IconFont).
     */
    public function glyph(): string
    {
        return match ($this) {
            self::Maths => IconFont::MATHS,
            self::Brain => IconFont::BRAIN,
            self::Leaf => IconFont::LEAF,
            self::Cog => IconFont::COG,
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
     * The column on a Corporation holding the points it has banked in this
     * suit (rulebook 3.2.1).
     */
    public function pointsColumn(): string
    {
        return $this->value.'_points';
    }

    /**
     * The tracker that moves this suit's points.
     *
     * Every Research Point that moves goes through it, so the ledger can say
     * where a Corporation's eleven Cog came from three turns later.
     */
    public function tracker(): Tracker
    {
        return match ($this) {
            self::Cog => Tracker::ResearchCog,
            self::Brain => Tracker::ResearchBrain,
            self::Leaf => Tracker::ResearchLeaf,
            self::Maths => Tracker::ResearchMaths,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return [self::Cog, self::Brain, self::Leaf, self::Maths];
    }
}
