<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Finds a faction's logo from its name.
 *
 * The five Corporations and four gangs all have artwork, and the roster in
 * config/running_hot.php is keyed by name and nothing else - so a slug of the
 * name is what identifies a logo. Augmented Nucleotech is filed as
 * augmented-nucleotech, g33ks as g33ks. Nothing stores a path, which means no
 * path can drift from the faction it belongs to, and artwork committed after a
 * game was created appears in that game immediately rather than needing a
 * column somewhere to be backfilled.
 *
 * There are two variants of a faction's artwork, because one picture cannot do
 * both jobs. The square {@see self::ICON} is the logo alone and goes anywhere
 * the name is written beside it - a table row, a card heading, a character's
 * role line - which is every one of the application's own surfaces. The
 * {@see self::WIDE} lockup sets the name as type inside the picture, so it
 * needs horizontal room and has to stand in place of written text rather than
 * next to it; Discord's #facility-list embed is its one consumer, where a
 * Corporation gets the full width of a message. They are filed under the same
 * slug, the wide one with a -wide suffix.
 *
 * A faction with no logo is normal rather than exceptional, and it is the case
 * a fresh checkout is in. Control invents a Corporation mid-game and there has
 * never been a logo drawn for it, so every caller has to cope with null: the
 * Discord embed simply carries no picture, and the application's own pages
 * draw the faction's initials on the colour its Discord role already wears.
 *
 * The directory is read once per request and answered from memory after that,
 * for the reason {@see CardImage} does the same: the Control panel names every
 * faction in the game, and one filesystem check per faction per extension is a
 * cost worth paying once.
 *
 * The files live under public/ rather than resources/ because Discord fetches
 * an embed image over the public internet from an absolute URL - it cannot be
 * handed a local file by path - so a logo has to be servable as it sits. That
 * also means Discord cannot see one on a dev server it cannot reach, and the
 * thumbnail is quietly dropped from the embed rather than breaking it.
 */
class FactionLogo
{
    /**
     * Where faction artwork is filed, relative to the public directory.
     */
    public const DIRECTORY = 'images/factions';

    /**
     * The square badge: the logo alone, no wordmark.
     *
     * The workhorse, and what every one of the application's own surfaces
     * takes. It sits beside a faction's name in a table row, a card heading or
     * a character's role line, so the picture does not have to say the name -
     * and at 24 to 48 pixels square nothing with words in it would be legible
     * anyway.
     */
    public const ICON = 'icon';

    /**
     * The wide lockup: the logo beside the faction's name set as type.
     *
     * Carries the name itself, so it belongs only where it can stand in place
     * of written text rather than next to it, and only where there is
     * horizontal room. Discord's #facility-list embed is the one consumer.
     */
    public const WIDE = 'wide';

    /**
     * What the wide lockup's file name appends to the slug.
     *
     * So a faction is two files - gordon.png and gordon-wide.png - under the
     * one slug that identifies it, rather than two names to keep in step. The
     * bare slug is the square one, which keeps every file already on record
     * meaning what it did and makes the variant nobody can forget to supply
     * the one that needs no suffix.
     *
     * Str::slug folds an underscore to a hyphen, so gordon_wide.png out of a
     * designer's export lands in the same place.
     */
    public const WIDE_SUFFIX = '-wide';

    /**
     * The extensions looked for, in the order they win.
     *
     * webp first for the reason card artwork prefers it: the same picture at a
     * fraction of the bytes. Neither variant wants to be large - Discord scales
     * a thumbnail to 80x80 and an embed image to a few hundred wide - so
     * anything much beyond that is bytes spent on every message for nothing.
     *
     * No svg, deliberately, even though the application's own pages would draw
     * one perfectly well: Discord does not render svg in an embed, so a faction
     * whose only file was vector would look right in the browser and silently
     * have no thumbnail in the channel. One resolver answering the same for
     * both consumers is worth more than vector artwork here, at 80x80.
     */
    public const EXTENSIONS = ['webp', 'png', 'jpg', 'jpeg'];

    /**
     * Logo file names by slug and then variant, or null before the first read.
     *
     * @var array<string, array<string, string>>|null
     */
    private static ?array $manifest = null;

    /**
     * The web path to a faction's logo, or null where there is none.
     *
     * Rooted rather than absolute, so the same value works behind whatever host
     * the game is served on. That is what the application's own pages want;
     * anything handing the path to Discord wants {@see self::urlFor()}.
     */
    public static function pathFor(?string $name, string $variant = self::ICON): ?string
    {
        $file = self::fileFor($name, $variant);

        return $file === null ? null : '/'.self::DIRECTORY.'/'.$file;
    }

    /**
     * The absolute URL of a faction's logo, or null where there is none.
     *
     * For Discord, which fetches an embed's images itself and so needs a URL it
     * can resolve from the public internet rather than a path relative to the
     * page it came from.
     */
    public static function urlFor(?string $name, string $variant = self::ICON): ?string
    {
        $path = self::pathFor($name, $variant);

        return $path === null ? null : url($path);
    }

    /**
     * The logo file name for a faction, or null where there is none.
     *
     * One variant never stands in for the other. A wide lockup crushed into a
     * 24-pixel square is an unreadable smudge, so a faction with only a wide
     * file draws its initials in the small slots instead - the same asymmetry
     * {@see CardImage} uses, where a card's back has no fallback because an
     * unsuffixed file is the front of a one-sided card. A caller that has a
     * real preference between the two asks for each in turn and says which it
     * would rather have; the #facility-list embed is the one that does.
     */
    public static function fileFor(?string $name, string $variant = self::ICON): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return self::manifest()[self::slug($name)][$variant] ?? null;
    }

    /**
     * Whether a faction has this variant on record.
     */
    public static function has(?string $name, string $variant = self::ICON): bool
    {
        return self::fileFor($name, $variant) !== null;
    }

    /**
     * How the artwork for a faction of this name should be filed.
     *
     * Public because it is the answer to "what do I call the file?", which is
     * the only question anyone adding a logo has - the Control panel says it
     * for a faction that has none rather than leaving it to be guessed.
     */
    public static function slug(string $name): string
    {
        return Str::slug($name);
    }

    /**
     * Whether any faction artwork is on record at all.
     *
     * A clean checkout has none, so a page listing the factions can say the
     * artwork has not been added rather than implying nine factions were all
     * invented by Control.
     */
    public static function anyOnRecord(): bool
    {
        return self::manifest() !== [];
    }

    /**
     * Forget the directory listing.
     *
     * For tests, which write artwork while running: the listing is cached for
     * the life of the process, so a test adding a file after something else has
     * read the directory would otherwise not see it.
     */
    public static function flush(): void
    {
        self::$manifest = null;
    }

    /**
     * Every logo file, indexed by the slug it belongs to and then the variant.
     *
     * A slug and variant with more than one file keeps whichever extension
     * wins, so dropping a webp beside an existing png supersedes it rather than
     * making which one is served depend on the order the directory happens to
     * list.
     *
     * @return array<string, array<string, string>>
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

            [$slug, $variant] = self::identify($file->getFilenameWithoutExtension());

            if ($slug === '' || (isset($ranked[$slug][$variant]) && $ranked[$slug][$variant] <= $rank)) {
                continue;
            }

            $ranked[$slug][$variant] = $rank;
            $manifest[$slug][$variant] = $file->getFilename();
        }

        return self::$manifest = $manifest;
    }

    /**
     * Which faction a file belongs to, and which variant of it it is.
     *
     * The suffix is only stripped when something is left to be a faction: a
     * file called wide.png is a faction named Wide with no lockup rather than a
     * lockup belonging to nobody. Everything else is the square badge, so a
     * file already on record keeps meaning what it did.
     *
     * @return array{0: string, 1: string}
     */
    private static function identify(string $filename): array
    {
        $slug = self::slug($filename);
        $suffix = self::WIDE_SUFFIX;

        if (str_ends_with($slug, $suffix)) {
            $base = substr($slug, 0, -strlen($suffix));

            if ($base !== '') {
                return [$base, self::WIDE];
            }
        }

        return [$slug, self::ICON];
    }
}
