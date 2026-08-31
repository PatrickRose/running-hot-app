<?php

namespace App\Support;

use App\Support\Discord\GuildBlueprint;

/**
 * How the application draws a faction: its logo, and its colour.
 *
 * The two travel together everywhere, because they are answers to the same
 * question and the second is the fallback for the first. A faction with artwork
 * is drawn as its logo; a faction without is drawn as its initials on the
 * colour {@see GuildBlueprint::colourFor()} gives it - which is the colour of
 * the Discord role its players are already wearing, so a team is the same
 * colour in the browser, in the channel list and on the #facility-list embed.
 *
 * The colour is shaped here rather than in the browser because it is derived
 * from an md5 of the name, and a second implementation of that in TypeScript
 * would be a hash function written twice to agree on nothing more important
 * than a swatch. The initials are not sent, because those genuinely are
 * presentation and the front end can read the first letters of a name it has
 * already been given.
 *
 * Two factions can collide on a colour: colourFor is a hash across ten
 * colours, so a roster of nine is close to the point where two share one. That
 * is already true of the Discord roles, and a logo is what tells them apart.
 */
class FactionBadge
{
    /**
     * How to draw a faction of this name.
     *
     * Spread into whatever payload is describing the faction rather than
     * nested: a Corporation is one thing on the page, and its badge is not a
     * sub-object of it. The name comes back too, so this is the whole of a
     * faction's identity in a payload and every one of them agrees on the key
     * it goes under - the browser then takes one shape everywhere instead of
     * one per page.
     *
     * @return array{name: string, logo_path: string|null, colour: string}
     */
    public static function for(string $name): array
    {
        return [
            'name' => $name,
            'logo_path' => LogoImage::pathFor($name),
            'colour' => self::cssColour($name),
        ];
    }

    /**
     * A faction's colour as CSS, from the same integer Discord is given.
     */
    public static function cssColour(string $name): string
    {
        return sprintf('#%06X', GuildBlueprint::colourFor($name));
    }
}
