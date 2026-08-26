<?php

namespace App\Enums;

/**
 * The two Protection Card stacks a Facility keeps (rulebook 3.3.2).
 *
 * The rulebook calls this a card's "type", but a Facility also has a type, so
 * this application says "kind" for the card and keeps "type" for the Facility.
 *
 * The stacks are independent: Runners meet every physical card before the first
 * cyber one, and installing only ever touches the stack the card belongs to.
 */
enum ProtectionKind: string
{
    case Physical = 'physical';
    case Cyber = 'cyber';

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Physical',
            self::Cyber => 'Cyber',
        };
    }

    /**
     * The order Runners meet the stacks in.
     *
     * @return array<int, self>
     */
    public static function encounterOrder(): array
    {
        return [self::Physical, self::Cyber];
    }
}
