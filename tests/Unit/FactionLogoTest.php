<?php

namespace Tests\Unit;

use App\Support\Discord\GuildBlueprint;
use App\Support\FactionBadge;
use App\Support\FactionLogo;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Finding a faction's logo from its name.
 *
 * The roster is keyed by name and nothing else, so a slug of the name is the
 * whole of a logo's identity - there is no column to drift and no path to
 * store. A faction with no logo is the normal case rather than an error: a
 * clean checkout has none at all, and a Corporation Control invents mid-game
 * has never had one drawn.
 *
 * Every name written here is one no faction in the game has, because the real
 * artwork is committed to the repository and a test that cleaned up after
 * itself would delete it. {@see self::writeLogo()} refuses to overwrite a file
 * that already exists rather than trusting that.
 */
class FactionLogoTest extends TestCase
{
    /** @var array<int, string> */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            File::delete($path);
        }

        $this->written = [];
        FactionLogo::flush();

        parent::tearDown();
    }

    private function writeLogo(string $file): string
    {
        $directory = public_path(FactionLogo::DIRECTORY);
        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$file;

        // The game's own logos live here. A test that wrote over one would
        // delete it on the way out, so this is a hard stop rather than a
        // convention to remember.
        $this->assertFileDoesNotExist(
            $path,
            "[{$file}] already exists: a test must never write over the game's own artwork.",
        );

        File::put($path, 'not really an image');
        $this->written[] = $path;

        // The directory listing is cached for the life of the process, so a
        // file written after something has read it needs the listing dropped.
        FactionLogo::flush();

        return $path;
    }

    public function test_a_faction_with_a_logo_resolves_to_its_path(): void
    {
        $this->writeLogo('test-holdings-combine.png');

        $this->assertSame(
            '/images/factions/test-holdings-combine.png',
            FactionLogo::pathFor('Test Holdings Combine'),
        );
    }

    /**
     * The nine factions the roster names are all this shape, and g33ks is why
     * the slug is Str::slug rather than something that drops digits.
     */
    public function test_a_name_is_slugged_the_way_a_file_is_named(): void
    {
        $this->assertSame('augmented-nucleotech', FactionLogo::slug('Augmented Nucleotech'));
        $this->assertSame('mccullough-calibrated-mechanical', FactionLogo::slug('McCullough Calibrated Mechanical'));
        $this->assertSame('g33ks', FactionLogo::slug('g33ks'));
        $this->assertSame('gordon', FactionLogo::slug('Gordon'));
    }

    /**
     * Two files under the one slug, told apart by the suffix. The bare slug is
     * the square badge, so a file already on record keeps meaning what it did.
     */
    public function test_the_two_variants_resolve_separately(): void
    {
        $this->writeLogo('test-two-variants.png');
        $this->writeLogo('test-two-variants-wide.png');

        $this->assertSame(
            '/images/factions/test-two-variants.png',
            FactionLogo::pathFor('Test Two Variants', FactionLogo::ICON),
        );
        $this->assertSame(
            '/images/factions/test-two-variants-wide.png',
            FactionLogo::pathFor('Test Two Variants', FactionLogo::WIDE),
        );
    }

    /**
     * The square badge is what a caller gets without asking, because it is what
     * every one of the application's own surfaces wants.
     */
    public function test_the_icon_is_the_default_variant(): void
    {
        $this->writeLogo('test-default-variant.png');
        $this->writeLogo('test-default-variant-wide.png');

        $this->assertSame(
            FactionLogo::pathFor('Test Default Variant', FactionLogo::ICON),
            FactionLogo::pathFor('Test Default Variant'),
        );
    }

    /**
     * A lockup crushed into a 24-pixel square is an unreadable smudge, so it
     * never stands in for the badge - the faction draws its initials instead.
     */
    public function test_a_wide_lockup_is_not_used_as_the_square_badge(): void
    {
        $this->writeLogo('test-lockup-only-wide.png');

        $this->assertNull(FactionLogo::pathFor('Test Lockup Only'));
        $this->assertFalse(FactionLogo::has('Test Lockup Only'));
        $this->assertTrue(FactionLogo::has('Test Lockup Only', FactionLogo::WIDE));
    }

    /**
     * Nor the other way round: a caller asking for the lockup is told there
     * isn't one rather than being handed the badge to stretch.
     */
    public function test_the_square_badge_is_not_used_as_a_wide_lockup(): void
    {
        $this->writeLogo('test-badge-only.png');

        $this->assertNull(FactionLogo::pathFor('Test Badge Only', FactionLogo::WIDE));
        $this->assertTrue(FactionLogo::has('Test Badge Only'));
    }

    /**
     * The suffix is only stripped when something is left over to be a faction,
     * so a file called wide.png belongs to a faction named Wide.
     */
    public function test_a_bare_suffix_is_a_faction_rather_than_a_lockup(): void
    {
        $this->writeLogo('wide.png');

        $this->assertSame('/images/factions/wide.png', FactionLogo::pathFor('Wide'));
        $this->assertNull(FactionLogo::pathFor('Wide', FactionLogo::WIDE));
    }

    /**
     * Str::slug folds an underscore to a hyphen, so a designer's export lands
     * in the same place as the documented name.
     */
    public function test_an_underscore_suffix_is_the_same_lockup(): void
    {
        $this->writeLogo('test_underscore_suffix_wide.png');

        $this->assertSame(
            '/images/factions/test_underscore_suffix_wide.png',
            FactionLogo::pathFor('Test Underscore Suffix', FactionLogo::WIDE),
        );
    }

    public function test_a_faction_with_no_logo_resolves_to_null(): void
    {
        $this->assertNull(FactionLogo::pathFor('Test Nothing Drawn Yet'));
        $this->assertNull(FactionLogo::pathFor('Test Nothing Drawn Yet', FactionLogo::WIDE));
        $this->assertFalse(FactionLogo::has('Test Nothing Drawn Yet'));
    }

    public function test_no_name_resolves_to_null(): void
    {
        $this->assertNull(FactionLogo::pathFor(null));
        $this->assertNull(FactionLogo::pathFor(''));
        $this->assertNull(FactionLogo::pathFor('   '));
    }

    /**
     * A file dropped in with different capitalisation still resolves, because
     * an export from a designer is not always lower case.
     */
    public function test_the_file_name_is_matched_case_insensitively(): void
    {
        $this->writeLogo('Test-Case-Folding.PNG');

        $this->assertSame(
            '/images/factions/Test-Case-Folding.PNG',
            FactionLogo::pathFor('Test Case Folding'),
        );
    }

    /**
     * Adding a webp beside an existing png supersedes it, rather than making
     * which one is served depend on the order the directory happens to list.
     */
    public function test_webp_wins_over_png_for_the_same_faction(): void
    {
        $this->writeLogo('test-both-formats.png');
        $this->writeLogo('test-both-formats.webp');

        $this->assertSame(
            '/images/factions/test-both-formats.webp',
            FactionLogo::pathFor('Test Both Formats'),
        );
    }

    /**
     * And the two variants rank independently, so a webp lockup does not
     * supersede a png badge.
     */
    public function test_the_variants_rank_their_extensions_independently(): void
    {
        $this->writeLogo('test-mixed-formats.png');
        $this->writeLogo('test-mixed-formats-wide.webp');

        $this->assertSame(
            '/images/factions/test-mixed-formats.png',
            FactionLogo::pathFor('Test Mixed Formats'),
        );
        $this->assertSame(
            '/images/factions/test-mixed-formats-wide.webp',
            FactionLogo::pathFor('Test Mixed Formats', FactionLogo::WIDE),
        );
    }

    /**
     * Discord will not render an svg in an embed, so one is not on record at
     * all - a faction whose only file was vector would otherwise look right in
     * the browser and have no thumbnail in the channel.
     */
    public function test_an_svg_is_not_treated_as_a_logo(): void
    {
        $this->writeLogo('test-vector-only.svg');

        $this->assertNull(FactionLogo::pathFor('Test Vector Only'));
    }

    /**
     * Discord fetches an embed's images itself, so it needs a URL it can
     * resolve rather than a path relative to a page it never saw.
     */
    public function test_the_url_is_absolute(): void
    {
        $this->writeLogo('test-absolute-url.png');

        $this->assertSame(
            url('/images/factions/test-absolute-url.png'),
            FactionLogo::urlFor('Test Absolute URL'),
        );
        $this->assertStringStartsWith('http', (string) FactionLogo::urlFor('Test Absolute URL'));
    }

    public function test_a_faction_with_no_logo_has_no_url(): void
    {
        $this->assertNull(FactionLogo::urlFor('Test Nothing Drawn Yet'));
    }

    /**
     * The badge is the whole of a faction's identity in a payload: the name it
     * goes under, the logo where there is one, and the colour that stands in
     * where there is not.
     */
    public function test_the_badge_carries_the_name_the_logo_and_the_colour(): void
    {
        $this->writeLogo('test-badge-shape.png');

        $badge = FactionBadge::for('Test Badge Shape');

        $this->assertSame('Test Badge Shape', $badge['name']);
        $this->assertSame('/images/factions/test-badge-shape.png', $badge['logo_path']);
        $this->assertSame(
            sprintf('#%06X', GuildBlueprint::colourFor('Test Badge Shape')),
            $badge['colour'],
        );
    }

    /**
     * The colour is the same integer Discord is given, only written as CSS -
     * which is the point of sending it rather than hashing the name again in
     * the browser. Checked on the real roster, since those are the nine names
     * that have to agree.
     */
    public function test_the_badge_colour_is_the_discord_colour_written_as_css(): void
    {
        foreach (['Augmented Nucleotech', 'Gordon', 'g33ks', 'Gruffsters'] as $name) {
            $this->assertSame(
                sprintf('#%06X', GuildBlueprint::colourFor($name)),
                FactionBadge::cssColour($name),
                "[{$name}] is a different colour in the browser than in Discord.",
            );
        }
    }

    public function test_a_faction_with_no_logo_still_has_a_colour(): void
    {
        $badge = FactionBadge::for('Test Nothing Drawn Yet');

        $this->assertNull($badge['logo_path']);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $badge['colour']);
    }
}
