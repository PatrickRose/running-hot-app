<?php

namespace Tests\Unit;

use App\Support\CardImage;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Finding a card's artwork from the code printed on it.
 *
 * A card with no artwork is normal rather than exceptional: Control invents
 * cards during play, and those have never been printed. The interface shows
 * their text in a card-shaped box instead, so every one of these has to answer
 * null rather than guessing a path.
 */
class CardImageTest extends TestCase
{
    /** @var array<int, string> */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            File::delete($path);
        }

        $this->written = [];

        parent::tearDown();
    }

    private function writeArtwork(string $file): string
    {
        $directory = public_path(CardImage::DIRECTORY);
        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$file;
        File::put($path, 'not really an image');
        $this->written[] = $path;

        return $path;
    }

    public function test_a_code_with_artwork_resolves_to_its_path(): void
    {
        $this->writeArtwork('ZZ001.webp');

        $this->assertSame('/images/cards/ZZ001.webp', CardImage::pathFor('ZZ001'));
    }

    public function test_a_code_with_no_artwork_resolves_to_null(): void
    {
        $this->assertNull(CardImage::pathFor('ZZ404'));
    }

    /**
     * A card Control invented mid-game has no code, so it can have no artwork.
     */
    public function test_no_code_resolves_to_null(): void
    {
        $this->assertNull(CardImage::pathFor(null));
        $this->assertNull(CardImage::pathFor(''));
        $this->assertNull(CardImage::pathFor('   '));
    }

    /**
     * Codes are printed and filed in upper case, so one Control types in by hand
     * still finds its artwork.
     */
    public function test_a_code_is_matched_case_insensitively(): void
    {
        $this->writeArtwork('ZZ002.png');

        $this->assertSame('/images/cards/ZZ002.png', CardImage::pathFor('zz002'));
        $this->assertSame('/images/cards/ZZ002.png', CardImage::pathFor(' Zz002 '));
    }

    /**
     * The artwork is high resolution and a stack view shows a dozen at once, so
     * the smaller file wins where a card has both.
     */
    public function test_webp_wins_over_png(): void
    {
        $this->writeArtwork('ZZ003.png');
        $this->writeArtwork('ZZ003.webp');

        $this->assertSame('/images/cards/ZZ003.webp', CardImage::pathFor('ZZ003'));
    }

    /**
     * A code is used to build a file path, so it must not be able to reach out
     * of the artwork directory.
     */
    public function test_a_code_cannot_escape_the_artwork_directory(): void
    {
        $this->assertNull(CardImage::pathFor('../../../etc/passwd'));
        $this->assertNull(CardImage::pathFor('ZZ004/../../.env'));
    }

    /**
     * Worth asking once before rendering a long list: a checkout without the
     * artwork committed should show text rather than a page of broken images.
     */
    public function test_whether_any_artwork_is_on_record(): void
    {
        $before = CardImage::anyOnRecord();

        $this->writeArtwork('ZZ005.webp');

        $this->assertTrue(CardImage::anyOnRecord());

        // Says nothing about the state before, which depends on whether this
        // checkout has the artwork committed.
        $this->assertIsBool($before);
    }
}
