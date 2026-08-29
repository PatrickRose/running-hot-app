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
        CardImage::flush();

        parent::tearDown();
    }

    private function writeArtwork(string $file): string
    {
        $directory = public_path(CardImage::DIRECTORY);
        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.$file;
        File::put($path, 'not really an image');
        $this->written[] = $path;

        // The directory listing is cached for the life of the process, so a file
        // written after something has already read it needs the listing dropped.
        CardImage::flush();

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
     * An export from a designer is not always upper case, and the code printed
     * on the card always is, so the file name is folded the same way as the code.
     */
    public function test_a_lower_case_file_name_still_resolves(): void
    {
        $this->writeArtwork('zz006.webp');

        $this->assertSame('/images/cards/zz006.webp', CardImage::pathFor('ZZ006'));
    }

    /**
     * The directory is read once per request rather than once per card, so a
     * page listing two hundred cards costs one listing.
     */
    public function test_the_listing_reports_what_is_on_record(): void
    {
        $before = CardImage::countOnRecord();

        $this->writeArtwork('ZZ007.webp');
        $this->writeArtwork('ZZ008.png');

        $this->assertSame($before + 2, CardImage::countOnRecord());
    }

    /**
     * A research card is printed proposal side up and flipped over when it is
     * researched (rulebook 3.2.2), so it has two faces. Both are public.
     */
    public function test_a_card_can_have_a_second_face(): void
    {
        $this->writeArtwork('ZZ010.webp');
        $this->writeArtwork('ZZ010-BACK.webp');

        $this->assertSame('/images/cards/ZZ010.webp', CardImage::pathFor('ZZ010'));
        $this->assertSame(
            '/images/cards/ZZ010-BACK.webp',
            CardImage::pathFor('ZZ010', CardImage::BACK),
        );
        $this->assertTrue(CardImage::hasBack('ZZ010'));
    }

    /**
     * Most cards have one face, so asking for a back has to come back empty
     * rather than falling through to the front - a card showing its front twice
     * would look like a flip that did nothing.
     */
    public function test_a_one_sided_card_has_no_back(): void
    {
        $this->writeArtwork('ZZ011.webp');

        $this->assertNotNull(CardImage::pathFor('ZZ011'));
        $this->assertNull(CardImage::pathFor('ZZ011', CardImage::BACK));
        $this->assertFalse(CardImage::hasBack('ZZ011'));
    }

    /**
     * The back is filed under the front's code, so it must not be mistaken for a
     * card in its own right.
     */
    public function test_a_back_is_not_a_card_of_its_own(): void
    {
        $this->writeArtwork('ZZ012-BACK.webp');

        $this->assertNull(CardImage::pathFor('ZZ012'));
        $this->assertSame(
            '/images/cards/ZZ012-BACK.webp',
            CardImage::pathFor('ZZ012', CardImage::BACK),
        );
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
