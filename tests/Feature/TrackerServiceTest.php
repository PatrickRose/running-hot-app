<?php

namespace Tests\Feature;

use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use App\Services\TrackerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TrackerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function trackers(): TrackerService
    {
        return app(TrackerService::class);
    }

    public function test_adjusting_moves_the_value_and_records_the_movement(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['political_will' => 5]);
        $actor = User::factory()->control()->create();

        $adjustment = $this->trackers()->adjust(
            $corporation,
            Tracker::PoliticalWill,
            3,
            'Brokered the android contract',
            $actor,
        );

        $this->assertSame(8, $corporation->fresh()->political_will);
        $this->assertSame(5, $adjustment->value_before);
        $this->assertSame(8, $adjustment->value_after);
        $this->assertSame(3, $adjustment->delta);
        $this->assertSame('Brokered the android contract', $adjustment->reason);
        $this->assertSame($actor->id, $adjustment->actor_id);
        $this->assertFalse($adjustment->automated);
    }

    public function test_setting_writes_an_absolute_value(): void
    {
        $game = Game::factory()->create(['stability' => 6]);

        $adjustment = $this->trackers()->set($game, Tracker::Stability, 4, 'Expose in Parliament');

        $this->assertSame(4, $game->fresh()->stability);
        $this->assertSame(-2, $adjustment->delta);
    }

    public function test_political_will_and_credits_may_go_negative(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create([
            'political_will' => 1,
            'credits' => 0,
        ]);

        $this->trackers()->adjust($corporation, Tracker::PoliticalWill, -5);
        $this->trackers()->adjust($corporation, Tracker::CorporationCredits, -10);

        $this->assertSame(-4, $corporation->fresh()->political_will);
        $this->assertSame(-10, $corporation->fresh()->credits);
    }

    public function test_counts_are_clamped_at_zero(): void
    {
        $game = Game::factory()->create(['stability' => 2, 'civil_unrest' => 1]);
        $character = Character::factory()->for($game)->create(['wounds' => 1, 'tags' => 0]);

        $this->trackers()->adjust($character, Tracker::Wounds, -5);
        $this->trackers()->adjust($character, Tracker::Tags, -5);
        $this->trackers()->adjust($game, Tracker::Stability, -9);
        $this->trackers()->adjust($game, Tracker::CivilUnrest, -9);

        $this->assertSame(0, $character->fresh()->wounds);
        $this->assertSame(0, $character->fresh()->tags);
        $this->assertSame(0, $game->fresh()->stability);
        $this->assertSame(0, $game->fresh()->civil_unrest);
    }

    public function test_a_clamped_adjustment_records_the_delta_that_actually_happened(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create(['wounds' => 1]);

        $adjustment = $this->trackers()->adjust($character, Tracker::Wounds, -5);

        $this->assertSame(-1, $adjustment->delta, 'The ledger must reflect the real movement.');
        $this->assertSame(0, $adjustment->value_after);
    }

    public function test_a_tracker_cannot_be_applied_to_the_wrong_subject(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->for($game)->create();

        $this->expectException(InvalidArgumentException::class);

        $this->trackers()->adjust($gang, Tracker::Stability, 1);
    }

    public function test_notoriety_is_tracked_on_the_gang(): void
    {
        $game = Game::factory()->create();
        $gang = Gang::factory()->for($game)->create(['notoriety' => 0]);

        $this->trackers()->adjust($gang, Tracker::Notoriety, 2, 'Hit Corvid Biotics');

        $this->assertSame(2, $gang->fresh()->notoriety);
    }

    public function test_corporation_and_character_credits_write_to_their_own_rows(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['credits' => 10]);
        $character = Character::factory()->for($game)->create(['credits' => 10]);

        $this->trackers()->adjust($corporation, Tracker::CorporationCredits, 5);
        $this->trackers()->adjust($character, Tracker::CharacterCredits, -3);

        $this->assertSame(15, $corporation->fresh()->credits);
        $this->assertSame(7, $character->fresh()->credits);
    }
}
