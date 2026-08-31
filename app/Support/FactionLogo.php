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
 * A faction with no logo is normal rather than exceptional, and it is the case
 * a fresh checkout is in. Control invents a Corporation mid-game and there has
 * never been a logo drawn for it, so every caller has to cope with null: the
 * Discord embed simply carries no thumbnail, and the application's own pages
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
     * The extensions looked for, in the order they win.
     *
     * webp first for the reason card artwork prefers it: the same picture at a
     * fraction of the bytes. These are smaller than a card either way - Discord
     * scales a thumbnail to 80x80, so anything much over a few hundred pixels
     * square is bytes spent on every message for nothing.
     *
     * No svg, deliberately, even though the application's own pages would draw
     * one perfectly well: Discord does not render svg in an embed, so a faction
     * whose only file was vector would look right in the browser and silently
     * have no thumbnail in the channel. One resolver answering the same for
     * both consumers is worth more than vector artwork here, at 80x80.
     */
    public const EXTENSIONS = ['webp', 'png', 'jpg', 'jpeg'];

    /**
     * Logo file names by slug, or null before the first read.
     *
     * @var array<string, string>|null
     */
    private static ?array $manifest = null;

    /**
     * The web path to a faction's logo, or null where there is none.
     *
     * Rooted rather than absolute, so the same value works behind whatever host
     * the game is served on. That is what the application's own pages want;
     * anything handing the path to Discord wants {@see self::urlFor()}.
     */
    public static function pathFor(?string $name): ?string
    {
        $file = self::fileFor($name);

        return $file === null ? null : '/'.self::DIRECTORY.'/'.$file;
    }

    /**
     * The absolute URL of a faction's logo, or null where there is none.
     *
     * For Discord, which fetches an embed's images itself and so needs a URL it
     * can resolve from the public internet rather than a path relative to the
     * page it came from.
     */
    public static function urlFor(?string $name): ?string
    {
        $path = self::pathFor($name);

        return $path === null ? null : url($path);
    }

    /**
     * The logo file name for a faction, or null where there is none.
     */
    public static function fileFor(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return self::manifest()[self::slug($name)] ?? null;
    }

    /**
     * Whether a faction has a logo on record.
     */
    public static function has(?string $name): bool
    {
        return self::fileFor($name) !== null;
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
     * Every logo file, indexed by the slug it belongs to.
     *
     * A slug with more than one file keeps whichever extension wins, so dropping
     * a webp beside an existing png supersedes it rather than making which one
     * is served depend on the order the directory happens to list.
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

            $slug = self::slug($file->getFilenameWithoutExtension());

            if ($slug === '' || (isset($ranked[$slug]) && $ranked[$slug] <= $rank)) {
                continue;
            }

            $ranked[$slug] = $rank;
            $manifest[$slug] = $file->getFilename();
        }

        return self::$manifest = $manifest;
    }
}
