<?php

namespace App\Enums;

use App\Support\IconFont;

/**
 * The three kinds of item a Runner can carry (rulebook 3.4.1).
 *
 * The category is what decides when a card may be played and what happens to it
 * afterwards, so it is the one attribute of an Equipment card the application
 * has to know rather than merely show:
 *
 * - Permanent items are equipped during the Setup phase, before the Run begins,
 *   and stay with the Runner. Only three may be equipped at once, and only one
 *   copy of each title.
 * - "This run" items last the Run and then go back to Control.
 * - Single use items apply immediately and then go back to Control.
 *
 * The last two are played as Protection Cards are encountered, so neither has to
 * be declared up front.
 */
enum EquipmentCategory: string
{
    /**
     * How many Permanent items a Runner may equip at once (rulebook 3.4.1).
     */
    public const EQUIPPED_LIMIT = 3;

    case Permanent = 'permanent';

    case ThisRun = 'this-run';

    case SingleUse = 'single-use';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::ThisRun => 'This run',
            self::SingleUse => 'Single use',
        };
    }

    /**
     * The character that draws this category's icon (see App\Support\IconFont).
     *
     * The font draws all three.
     */
    public function glyph(): string
    {
        return match ($this) {
            self::Permanent => IconFont::PERMANENT,
            self::SingleUse => IconFont::SINGLE_USE,
            self::ThisRun => IconFont::THIS_RUN,
        };
    }

    /**
     * Whether the item has to be equipped before the Run starts.
     *
     * Only these count against the three-item limit: the other two are played
     * out of hand as the Runners meet each card.
     */
    public function isEquippedBeforeTheRun(): bool
    {
        return $this === self::Permanent;
    }

    /**
     * Whether the card goes back to Control once it has been used.
     */
    public function returnsToControl(): bool
    {
        return $this !== self::Permanent;
    }
}
