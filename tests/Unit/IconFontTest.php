<?php

namespace Tests\Unit;

use App\Enums\EquipmentCategory;
use App\Enums\ProtectionKind;
use App\Enums\ResearchSuit;
use App\Support\IconFont;
use Tests\TestCase;

/**
 * The game's icon font, and that it can actually draw what the application asks
 * of it.
 *
 * The font maps every icon onto an ASCII capital rather than onto a symbol
 * codepoint, so the mapping in App\Support\IconFont fails quietly: a glyph the
 * font does not carry renders as a bare letter rather than as nothing, which
 * reads as a styling problem rather than a missing icon. So it is checked
 * against the file.
 */
class IconFontTest extends TestCase
{
    /**
     * Under resources/ rather than public/, because Vite has to process it: the
     * dev server does not serve public/, so a font referenced from there loads
     * once built and never in development.
     */
    private const FONT = 'fonts/RunningHot-Font.ttf';

    public function test_the_icon_font_is_on_record(): void
    {
        $this->assertFileExists(resource_path(self::FONT));
    }

    /**
     * Every icon the application puts on a page is one the font draws.
     */
    public function test_the_font_draws_every_icon_in_use(): void
    {
        $mapped = $this->mappedCodepoints(resource_path(self::FONT));

        $this->assertNotEmpty($mapped, 'The font maps no characters at all.');

        foreach (IconFont::inUse() as $glyph => $meaning) {
            $this->assertContains(
                mb_ord($glyph),
                $mapped,
                sprintf('%s is drawn by "%s", which this font does not carry.', $meaning, $glyph),
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
     * Two things sharing a glyph would show the same icon for different
     * meanings, which is worse than showing no icon at all.
     */
    public function test_no_two_icons_share_a_glyph(): void
    {
        $glyphs = array_keys(IconFont::inUse());

        $this->assertSame($glyphs, array_unique($glyphs));

        foreach ($glyphs as $glyph) {
            $this->assertSame(1, mb_strlen((string) $glyph), 'A glyph is one character.');
        }
    }

    /**
     * Every enum that draws itself does so with an icon the font map knows
     * about, so a glyph cannot be introduced without being written down.
     */
    public function test_every_enum_glyph_is_a_recorded_icon(): void
    {
        $recorded = array_keys(IconFont::inUse());

        foreach (ResearchSuit::cases() as $suit) {
            $this->assertContains($suit->glyph(), $recorded, $suit->label());
        }

        foreach (ProtectionKind::cases() as $kind) {
            $this->assertContains($kind->glyph(), $recorded, $kind->label());
        }

        foreach (EquipmentCategory::cases() as $category) {
            $this->assertContains($category->glyph(), $recorded, $category->label());
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
