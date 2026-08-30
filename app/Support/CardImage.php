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
 * The directory is read once per request and answered from memory after that.
 * There are three hundred cards, the catalogue pages show two hundred at a time
 * and each card would otherwise cost one filesystem check per extension - so
 * asking the directory once is the difference between one listing and the better
 * part of a thousand stat calls.
 *
 * The files live under public/ because they are referenced from an img tag at
 * runtime and the page itself is served by Laravel. That is the opposite of the
 * icon font, which is referenced from CSS and so has to go through Vite.
 */
class CardImage
{
    /**
     * Where card artwork is filed, relative to the public directory.
     */
    public const DIRECTORY = 'images/cards';

    /**
     * The face of a card that is printed upwards.
     */
    public const FRONT = 'front';

    /**
     * The other face.
     *
     * Only research cards have one. A technology is printed proposal side up -
     * its name, what it is for, and the Research Points it costs - and
     * researching it is described as flipping the card over (rulebook 3.2.2), so
     * the back is what the Corporation actually got. Both faces are public: a
     * technology is not secret, only where it is stored is.
     *
     * The two sides need no columns of their own, because the application
     * already holds what each says: the front is name, description, the four
     * suit costs, the prerequisites and the required Facility type, and the back
     * is the effect with its copy and destroy strengths.
     */
    public const BACK = 'back';

    /**
     * What the artwork files append to a code to say which face they are.
     *
     * A two-faced card is filed as RSR001_F and RSR001_B. A card with one face
     * may be filed either as PS009_F or as plain PS009, so the front falls back
     * to the bare code - which also means a card Control adds artwork for by
     * hand does not have to know about the suffix at all.
     */
    public const FRONT_SUFFIX = '_F';

    public const BACK_SUFFIX = '_B';

    /**
     * The extensions looked for, in the order they win.
     *
     * The artwork is high resolution, so webp is preferred where a card has both
     * - it is the same picture at a fraction of the bytes, and these are sent to
     * every player on every page that lists a stack.
     */
    public const EXTENSIONS = ['webp', 'png', 'jpg', 'jpeg'];

    /**
     * Artwork file names by upper-cased code, or null before the first read.
     *
     * @var array<string, string>|null
     */
    private static ?array $manifest = null;

    /**
     * The web path to the artwork for a code, or null where there is none.
     *
     * Returns a rooted path rather than a full URL so the same value works
     * behind whatever host the game is served on. Callers that need an absolute
     * URL - a Discord embed would - pass it through url().
     */
    public static function pathFor(?string $code, string $side = self::FRONT): ?string
    {
        $file = self::fileFor($code, $side);

        return $file === null ? null : '/'.self::DIRECTORY.'/'.$file;
    }

    /**
     * The artwork file name for a code, or null where there is none.
     */
    public static function fileFor(?string $code, string $side = self::FRONT): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $manifest = self::manifest();

        foreach (self::keys($code, $side) as $key) {
            if (isset($manifest[$key])) {
                return $manifest[$key];
            }
        }

        return null;
    }

    /**
     * Whether this card has a second face on record.
     *
     * Asked rather than assumed from the kind of card: a research card with only
     * one file on record shows the one it has, rather than offering a flip to
     * nothing.
     */
    public static function hasBack(?string $code): bool
    {
        return self::fileFor($code, self::BACK) !== null;
    }

    /**
     * Whether any artwork is on record at all.
     *
     * Worth asking once before rendering a long list: the artwork is added to
     * the repository as a batch, so a checkout without it should say so rather
     * than showing every card as text as though Control had invented them all.
     */
    public static function anyOnRecord(): bool
    {
        return self::manifest() !== [];
    }

    /**
     * How many cards have artwork on record.
     */
    public static function countOnRecord(): int
    {
        return count(self::manifest());
    }

    /**
     * Forget the directory listing.
     *
     * For tests, which write artwork while running: the listing is cached for
     * the life of the process, and a test that adds a file after something else
     * has already read the directory would otherwise not see it.
     */
    public static function flush(): void
    {
        self::$manifest = null;
    }

    /**
     * Every artwork file, indexed by the code it belongs to.
     *
     * A code with more than one file keeps whichever extension wins, so adding
     * a webp beside an existing png quietly supersedes it rather than making
     * which one is served depend on the order the directory happens to list.
     *
     * @return array<string, string>
     */
    private static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $directory = public_path(self::DIRECTORY);

        if (! File::isDirectory($directory)) {
            return self::$manifest = [];
        }

        $ranked = [];
        $manifest = [];

        foreach (File::files($directory) as $file) {
            $rank = array_search(strtolower($file->getExtension()), self::EXTENSIONS, true);

            if ($rank === false) {
                continue;
            }

            $code = self::normalise($file->getFilenameWithoutExtension());

            if ($code === '' || (isset($ranked[$code]) && $ranked[$code] <= $rank)) {
                continue;
            }

            $ranked[$code] = $rank;
            $manifest[$code] = $file->getFilename();
        }

        return self::$manifest = $manifest;
    }

    /**
     * The names one face of a card might be filed under, best first.
     *
     * The back has exactly one, because a file with no face suffix is the front
     * of a single-sided card rather than the back of anything - falling back
     * there would make every card report a back it does not have, and a flip
     * that showed the same picture twice would look broken.
     *
     * @return array<int, string>
     */
    private static function keys(string $code, string $side): array
    {
        $code = self::normalise($code);

        return $side === self::BACK
            ? [$code.self::BACK_SUFFIX]
            : [$code.self::FRONT_SUFFIX, $code];
    }

    /**
     * A code as it is filed.
     *
     * Codes are printed in upper case, but an export from a designer is not
     * always, so both the file name and the code being looked up are folded the
     * same way. Anything that is not part of a code is dropped, which also means
     * a code cannot be used to reach outside the artwork directory.
     */
    private static function normalise(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($code)) ?? '');
    }
}
