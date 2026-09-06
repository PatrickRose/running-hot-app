<?php

namespace Tests\Unit;

use App\Models\AgendaCard;
use App\Support\AgendaCardBlueprint;
use Tests\TestCase;

/**
 * The game's own agenda deck (rulebook 3.1.1).
 *
 * These are data assertions rather than rule assertions, and they are here
 * because the deck is transcribed from the game's card list: a card that breaks
 * 3.1.4's bounds, or a title that repeats, would not fail until Control drew it
 * mid-session.
 */
class AgendaCardBlueprintTest extends TestCase
{
    public function test_every_card_carries_between_two_and_five_resolutions(): void
    {
        foreach (AgendaCardBlueprint::defaults() as $card) {
            $count = count($card['resolutions']);

            $this->assertGreaterThanOrEqual(
                AgendaCard::MINIMUM_RESOLUTIONS,
                $count,
                $card['title'].' has too few resolutions to be voted on.',
            );

            $this->assertLessThanOrEqual(
                AgendaCard::MAXIMUM_RESOLUTIONS,
                $count,
                $card['title'].' has more resolutions than an agenda may carry.',
            );
        }
    }

    /**
     * App\Actions\SeedAgendaCards matches on the title, because an agenda card
     * carries no printed code. A repeat would silently seed one card short.
     */
    public function test_titles_are_distinct(): void
    {
        $titles = array_column(AgendaCardBlueprint::defaults(), 'title');

        $this->assertSame(array_unique($titles), $titles);
    }

    public function test_no_resolution_is_blank_or_repeated_on_its_own_card(): void
    {
        foreach (AgendaCardBlueprint::defaults() as $card) {
            foreach ($card['resolutions'] as $resolution) {
                $this->assertNotSame('', trim($resolution), $card['title'].' has a blank resolution.');
            }

            $this->assertSame(
                array_unique($card['resolutions']),
                $card['resolutions'],
                $card['title'].' offers the same resolution twice.',
            );
        }
    }

    /**
     * The one pair that looks like a transcription slip and is not: the
     * quotation marks are the whole difference between a pension package and a
     * "pension package", and they are the joke.
     */
    public function test_retirement_keeps_its_scare_quotes(): void
    {
        $retirement = collect(AgendaCardBlueprint::defaults())->firstWhere('title', 'Retirement');

        $this->assertContains('Provide retirees with a pension package', $retirement['resolutions']);
        $this->assertContains('Provide retirees with a "pension package"', $retirement['resolutions']);
    }
}
