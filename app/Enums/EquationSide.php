<?php

namespace App\Enums;

/**
 * One of the two sets an equation is made of (rulebook 3.2.1).
 *
 * The rulebook writes an equation as "7 Leaf / 3 Maths", so the two sets are
 * the left and right of that slash. They are named rather than indexed because
 * scoring asks which of them you are taking your points from, and "left" is
 * what a player sees on the screen.
 */
enum EquationSide: string
{
    case Left = 'left';

    case Right = 'right';

    public function label(): string
    {
        return match ($this) {
            self::Left => 'Left',
            self::Right => 'Right',
        };
    }

    public function other(): self
    {
        return match ($this) {
            self::Left => self::Right,
            self::Right => self::Left,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return [self::Left, self::Right];
    }
}
