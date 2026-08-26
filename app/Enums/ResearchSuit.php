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
 * A technology may cost nothing in a suit, and many cost nothing in two or
 * three of them, so a missing cost is zero rather than an omission.
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
