<?php

namespace Tests\Unit;

use App\Enums\ResearchSuit;
use App\Support\GamePresenter;
use Tests\TestCase;

/**
 * The four Research Point suits and the icons that draw them (rulebook 3.2.2).
 *
 * The rulebook shows these as icons and never names them in its body text, so
 * the application shows icons too - out of the game's own font, which maps each
 * icon onto an ASCII letter rather than onto a symbol codepoint.
 *
 * That mapping is the fragile part, and it fails quietly: a glyph the font does
 * not carry shows as a bare capital letter rather than as nothing, so a wrong
 * letter looks like a styling problem rather than a missing icon. Hence the
 * check against the font file itself.
 */
class ResearchSuitTest extends TestCase
{
    /**
     * Under resources/ rather than public/, because Vite has to process it: the
     * dev server does not serve public/, so a font referenced from there loads
     * once built and never in development.
     */
    private const FONT = 'fonts/RunningHot-Font.ttf';

    public function test_every_suit_has_a_label_and_a_cost_column(): void
    {
        foreach (ResearchSuit::all() as $suit) {
            $this->assertNotSame('', $suit->label());
            $this->assertSame($suit->value.'_cost', $suit->costColumn());
        }
    }

    public function test_all_four_suits_are_covered(): void
    {
        $this->assertSame(
            ['cog', 'brain', 'leaf', 'maths'],
            array_map(fn (ResearchSuit $suit): string => $suit->value, ResearchSuit::all()),
        );
        $this->assertCount(4, ResearchSuit::cases());
    }

    /**
     * Two suits sharing a glyph would show the same icon for different costs,
     * which is worse than showing no icon at all.
     */
    public function test_each_suit_has_its_own_single_character_glyph(): void
    {
        $glyphs = array_map(fn (ResearchSuit $suit): string => $suit->glyph(), ResearchSuit::all());

        $this->assertSame($glyphs, array_unique($glyphs));

        foreach ($glyphs as $glyph) {
            $this->assertSame(1, mb_strlen($glyph), 'A glyph is one character.');
        }
    }

    public function test_the_icon_font_is_on_record(): void
    {
        $this->assertFileExists(resource_path(self::FONT));
    }

    /**
     * The font really does draw each of the four.
     *
     * Read out of the font's own character map, so replacing the file with one
     * that has dropped or moved an icon fails here rather than shipping a page
     * of stray capitals.
     */
    public function test_the_font_maps_every_suit_glyph(): void
    {
        $mapped = $this->mappedCodepoints(resource_path(self::FONT));

        $this->assertNotEmpty($mapped, 'The font maps no characters at all.');

        foreach (ResearchSuit::all() as $suit) {
            $codepoint = mb_ord($suit->glyph());

            $this->assertContains(
                $codepoint,
                $mapped,
                sprintf(
                    '%s is drawn by "%s", which this font does not carry.',
                    $suit->label(),
                    $suit->glyph(),
                ),
            );
        }
    }

    /**
     * The check above is only worth having if it can fail.
     *
     * This font carries every capital except N, which makes N the canary: a
     * reader that reported everything as mapped would pass the test above while
     * proving nothing.
     */
    public function test_the_font_reader_reports_a_missing_character_as_missing(): void
    {
        $mapped = $this->mappedCodepoints(resource_path(self::FONT));

        $this->assertContains(mb_ord('A'), $mapped);
        $this->assertNotContains(mb_ord('N'), $mapped, 'N is the one capital this font has no icon for.');
        $this->assertNotContains(mb_ord('z'), $mapped, 'The font carries no lower case.');
    }

    /**
     * An icon is no use without its name: the glyph is a capital letter, so a
     * screen reader would read "A" where the page means Maths. Every caller
     * pairs the two, and the payload has to carry both for them to.
     */
    public function test_the_payload_pairs_every_glyph_with_its_name(): void
    {
        $suits = app(GamePresenter::class)->researchSuits();

        $this->assertCount(4, $suits);

        foreach ($suits as $suit) {
            $this->assertNotSame('', $suit['glyph']);
            $this->assertNotSame('', $suit['label']);
            // The name must be a name rather than the letter again, or the
            // reader is no better off.
            $this->assertNotSame($suit['glyph'], $suit['label']);
        }
    }

    /**
     * The characters a TrueType font has glyphs for, from its `cmap` table.
     *
     * Only format 4 is read, which is the one a font like this uses. A font
     * offering nothing in format 4 comes back empty and the test above fails,
     * which is the right way round: better a failure asking someone to look than
     * a silent pass over a font nobody checked.
     *
     * @return array<int, int>
     */
    private function mappedCodepoints(string $path): array
    {
        $font = (string) file_get_contents($path);

        $cmap = $this->tableOffset($font, 'cmap');

        if ($cmap === null) {
            return [];
        }

        $subtables = $this->uint16($font, $cmap + 2);

        for ($i = 0; $i < $subtables; $i++) {
            $record = $cmap + 4 + ($i * 8);
            $offset = $cmap + $this->uint32($font, $record + 4);

            if ($this->uint16($font, $offset) !== 4) {
                continue;
            }

            return $this->readFormat4($font, $offset);
        }

        return [];
    }

    /**
     * @return array<int, int>
     */
    private function readFormat4(string $font, int $offset): array
    {
        $segments = intdiv($this->uint16($font, $offset + 6), 2);
        $endCodes = $offset + 14;
        $startCodes = $endCodes + ($segments * 2) + 2;

        $codepoints = [];

        for ($segment = 0; $segment < $segments; $segment++) {
            $end = $this->uint16($font, $endCodes + ($segment * 2));
            $start = $this->uint16($font, $startCodes + ($segment * 2));

            // The last segment is the required 0xFFFF terminator, not a range
            // anybody draws with.
            if ($start === 0xFFFF) {
                continue;
            }

            for ($codepoint = $start; $codepoint <= $end; $codepoint++) {
                $codepoints[] = $codepoint;
            }
        }

        return $codepoints;
    }

    private function tableOffset(string $font, string $tag): ?int
    {
        $tables = $this->uint16($font, 4);

        for ($i = 0; $i < $tables; $i++) {
            $record = 12 + ($i * 16);

            if (substr($font, $record, 4) === $tag) {
                return $this->uint32($font, $record + 8);
            }
        }

        return null;
    }

    private function uint16(string $font, int $at): int
    {
        /** @var array{1: int} $parsed */
        $parsed = unpack('n', substr($font, $at, 2));

        return $parsed[1];
    }

    private function uint32(string $font, int $at): int
    {
        /** @var array{1: int} $parsed */
        $parsed = unpack('N', substr($font, $at, 4));

        return $parsed[1];
    }
}
