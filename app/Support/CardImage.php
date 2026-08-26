<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Finds a card's artwork from the code printed on it.
 *
 * Every card in the game's card list carries a code - PS003, EEP002, RSR001 -
 * and the artwork is filed under that code, so nothing has to store a path and
 * no path can drift from the card it belongs to.
 *
 * A card with no artwork is normal rather than exceptional. Control invents
 * cards mid-game: the rulebook has research proposals priced and added to the
 * tree during play (3.2.4), and a DTC technology hands out a bypass card named
 * after whichever Protection Card it counters. Those have no code and no
 * printed artwork, so the interface shows their text in a card-shaped box
 * instead. Which means every caller has to cope with null, and the front end
 * treats the text box as the normal case rather than a fallback.
 *
 * The files live under public/ because that is the only place Discord can reach
 * them: an embed references an image by absolute URL and cannot attach one by
 * path.
 */
class CardImage
{
    /**
     * Where card artwork is filed, relative to the public directory.
     */
    public const DIRECTORY = 'images/cards';

    /**
     * The extensions looked for, in the order they win.
     *
     * The artwork is high resolution, so webp is preferred where a card has both
     * - it is the same picture at a fraction of the bytes, and these are sent to
     * every player on every page that lists a stack.
     */
    public const EXTENSIONS = ['webp', 'png', 'jpg', 'jpeg'];

    /**
     * The web path to the artwork for a code, or null where there is none.
     *
     * Returns a rooted path rather than a full URL so the same value works
     * behind whatever host the game is served on. Callers that need an absolute
     * URL - the Discord embeds - pass it through url().
     */
    public static function pathFor(?string $code): ?string
    {
        $file = self::fileFor($code);

        return $file === null ? null : '/'.self::DIRECTORY.'/'.$file;
    }

    /**
     * The artwork file name for a code, or null where there is none.
     */
    public static function fileFor(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $code = self::normalise($code);

        foreach (self::EXTENSIONS as $extension) {
            $file = $code.'.'.$extension;

            if (File::exists(public_path(self::DIRECTORY.'/'.$file))) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Whether any artwork is on record at all.
     *
     * Worth asking once before rendering a long list: the artwork is added to
     * the repository as a batch, so a checkout without it should show every card
     * as text rather than a page of broken images.
     */
    public static function anyOnRecord(): bool
    {
        $directory = public_path(self::DIRECTORY);

        if (! File::isDirectory($directory)) {
            return false;
        }

        foreach (self::EXTENSIONS as $extension) {
            if (File::glob($directory.'/*.'.$extension) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * A code as it is filed.
     *
     * Codes are printed in upper case and filed that way. Trimming and
     * upper-casing here means a code Control types in by hand still finds its
     * artwork, and a code cannot be used to reach outside the artwork directory.
     */
    private static function normalise(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($code)) ?? '');
    }
}
