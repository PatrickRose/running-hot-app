<?php

namespace Tests\Feature;

use App\Actions\ClearRunChannels;
use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\PhaseType;
use App\Enums\ProtectionKind;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\FacilityType;
use App\Models\Game;
use App\Models\Phase;
use App\Models\ProtectionCardType;
use App\Models\Turn;
use App\Models\User;
use App\Services\Dice;
use App\Services\RunEngine;
use App\Services\TurnEngine;
use App\Support\Discord\GuildBlueprint;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeDice;
use Tests\Support\FakeDiscordGuild;
use Tests\TestCase;

/**
 * Last turn's run comes out of the Facility's channel when the turn ends.
 *
 * A Facility's channels are permanent and every Run against it happens in the
 * same pair, so without this a group hitting Attercliffe Yard on turn 4 opens
 * the channel and reads turn 3's group working out exactly what was in the
 * stack. Rulebook 3.4.2 makes that Secret and reconnaissance is what you spend
 * an action to find out, so leaving the transcript there is a free recon action
 * for everybody who comes after.
 *
 * What is worth keeping is kept in `run_events`, which nothing here touches.
 */
class RunChannelClearingTest extends TestCase
{
    use RefreshDatabase;

    private FakeDiscordGuild $discord;

    private Game $game;

    private Turn $turn;

    private Corporation $corporation;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Dice::class, new FakeDice);

        $this->discord = (new FakeDiscordGuild)->bind();

        $this->game = Game::factory()->create([
            'status' => GameStatus::Running,
            'discord_guild_id' => $this->discord->guildId,
        ]);
        app(TurnEngine::class)->start($this->game);
        $this->game->refresh();

        /** @var Turn $turn */
        $turn = $this->game->currentTurn();
        $this->turn = $turn;

        $this->corporation = Corporation::factory()->for($this->game)->create(['name' => 'Gordon']);

        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        $this->facility = Facility::factory()
            ->for($this->corporation)
            ->for($type)
            ->create(['name' => 'Attercliffe Yard']);

        FacilityProtectionCard::factory()->create([
            'facility_id' => $this->facility->id,
            'protection_card_type_id' => ProtectionCardType::factory()
                ->ofKind(ProtectionKind::Physical)
                ->create(['game_id' => $this->game->id])->id,
            'kind' => ProtectionKind::Physical,
            'position' => 1,
        ]);
    }

    public function test_the_turn_ending_clears_the_channel_of_a_facility_that_was_run_against(): void
    {
        $channelId = $this->textChannelId();
        $this->discord->postMessage($channelId, 'two physical, one cyber, the Orc is outermost');
        $this->discord->postMessage($channelId, 'boost it');
        $this->discord->postMessage($channelId, 'through');

        $this->goIn();
        $this->endTheTurn();

        $this->assertSame([], $this->discord->messagesIn($channelId));
        $this->assertSame(1, $this->discord->countCalls('post', '/bulk-delete'));
    }

    /**
     * Control's own call: whatever should outlive the turn gets pinned, and
     * this leaves it exactly where it is.
     */
    public function test_a_pinned_message_survives(): void
    {
        $channelId = $this->textChannelId();
        $this->discord->postMessage($channelId, 'the Orc is outermost');
        $this->discord->postMessage($channelId, 'Control: this run is plot-critical', pinned: true);
        $this->discord->postMessage($channelId, 'through');

        $this->goIn();
        $this->endTheTurn();

        $this->assertSame(
            ['Control: this run is plot-critical'],
            array_column($this->discord->messagesIn($channelId), 'content'),
        );
    }

    /**
     * Sweeping every channel in the guild every turn would be a pile of
     * requests against a rate limit Discord enforces hard, for channels where
     * nothing was said.
     */
    public function test_a_facility_nobody_ran_against_is_left_alone(): void
    {
        /** @var FacilityType $type */
        $type = $this->game->facilityTypes()->where('key', FacilityTypeBlueprint::SECURITY)->sole();

        $quiet = Facility::factory()
            ->for($this->corporation)
            ->for($type)
            ->create(['name' => 'Burngreave Vault']);

        $quietChannel = $this->textChannelId($quiet);
        $this->discord->postMessage($quietChannel, 'nothing happened here');

        $this->goIn();
        $this->endTheTurn();

        $this->assertSame(
            ['nothing happened here'],
            array_column($this->discord->messagesIn($quietChannel), 'content'),
        );
    }

    /**
     * A run submitted and never begun put nobody in the channel, so there is
     * nothing of theirs in it to clear.
     */
    public function test_a_run_that_never_went_in_does_not_clear_the_channel(): void
    {
        $channelId = $this->textChannelId();
        $this->discord->postMessage($channelId, 'said before anybody went in');

        app(RunEngine::class)->submit($this->turn, $this->facility, $this->runner('111'));

        $this->endTheTurn();

        $this->assertCount(1, $this->discord->messagesIn($channelId));
        $this->assertSame(0, $this->discord->countCalls('get', '/messages'));
    }

    /**
     * The Action phase ending is not the turn ending. The group has Team Time
     * to read back over how it went.
     */
    public function test_the_action_phase_ending_does_not_clear_it(): void
    {
        $channelId = $this->textChannelId();
        $this->discord->postMessage($channelId, 'through the second card');

        $this->goIn();

        app(TurnEngine::class)->advance($this->currentPhase());

        $this->assertSame(PhaseType::TeamTime, $this->currentPhase()->type);
        $this->assertCount(1, $this->discord->messagesIn($channelId));
    }

    /**
     * Discord refuses to bulk-delete a batch of one, so a lone message has to
     * go the single-message way rather than being left behind.
     */
    public function test_a_single_message_is_still_cleared(): void
    {
        $channelId = $this->textChannelId();
        $this->discord->postMessage($channelId, 'the only thing anybody said');

        $this->goIn();
        $this->endTheTurn();

        $this->assertSame([], $this->discord->messagesIn($channelId));
        // Singly, because a bulk delete of one is refused outright.
        $this->assertSame(0, $this->discord->countCalls('post', '/bulk-delete'));
        $this->assertSame(1, $this->discord->countCalls('delete', '/messages/'));
    }

    /**
     * Discord refuses the whole batch if anything in it is over a fortnight
     * old, which a long-running test server is exactly where you meet it - and
     * it refuses the *whole batch*, so one stale message would otherwise take
     * the rest of the channel's tidy-up down with it.
     */
    public function test_messages_older_than_a_fortnight_are_deleted_one_at_a_time(): void
    {
        $channelId = $this->textChannelId();
        $this->discord->postMessage($channelId, 'from last month', timestamp: now()->subDays(40)->toIso8601String());
        $this->discord->postMessage($channelId, 'from tonight');
        $this->discord->postMessage($channelId, 'also from tonight');

        $this->goIn();
        $this->endTheTurn();

        $this->assertSame([], $this->discord->messagesIn($channelId));
        // The two recent ones in one call, and the stale one on its own.
        $this->assertSame(1, $this->discord->countCalls('post', '/bulk-delete'));
        $this->assertSame(1, $this->discord->countCalls('delete', '/messages/'));
    }

    /**
     * Fail-soft, and for the reason the access sync is: the sync driver runs
     * this inline, so a throw would come back out of advance() and stop the
     * clock. A channel that still has last turn's conversation in it is worth
     * fixing; a turn that cannot end would stop the game.
     */
    public function test_a_discord_outage_does_not_stop_the_clock(): void
    {
        $this->goIn();

        Http::fake([
            'discord.com/api/*/messages*' => Http::response(['message' => 'Server Error'], 500),
            'discord.com/api/*' => Http::response([], 200),
        ]);

        $this->endTheTurn();

        $this->assertSame(2, $this->game->refresh()->currentTurn()?->number);
    }

    public function test_nothing_is_attempted_without_a_bot_token(): void
    {
        $this->goIn();
        config()->set('services.discord.bot_token', null);

        $this->endTheTurn();

        $this->assertSame(0, $this->discord->countCalls('get', '/messages'));
    }

    /**
     * A Facility with no channels on record is what a Discord outage during the
     * requisition leaves behind, and the Control panel already offers to fix
     * it. The sweep must not fall over on one.
     */
    public function test_a_facility_with_no_channels_is_skipped(): void
    {
        $this->goIn();

        $this->game->discordResources()
            ->where('key', GuildBlueprint::facilityChannelKey($this->facility, 'text'))
            ->delete();

        $this->assertSame([], app(ClearRunChannels::class)->handle($this->turn));
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function textChannelId(?Facility $facility = null): string
    {
        $resource = $this->game->discordResources()
            ->where('key', GuildBlueprint::facilityChannelKey($facility ?? $this->facility, 'text'))
            ->sole();

        return $resource->discord_id;
    }

    private function currentPhase(): Phase
    {
        /** @var Phase $phase */
        $phase = $this->game->refresh()->currentPhase();

        return $phase;
    }

    /**
     * Put a group into the Facility, which is what makes it worth clearing.
     *
     * In the Action phase, because that is when a run happens (rulebook 3.4.5)
     * - and because the phase the clock is on is exactly what these tests are
     * about.
     */
    private function goIn(): void
    {
        while ($this->currentPhase()->type !== PhaseType::Action) {
            app(TurnEngine::class)->advance($this->currentPhase());
        }

        app(RunEngine::class)->begin(
            app(RunEngine::class)->submit($this->turn, $this->facility, $this->runner('111'))
        );
    }

    /** Run the clock on to the end of Team Time, which is the end of the turn. */
    private function endTheTurn(): void
    {
        while ($this->currentPhase()->turn_id === $this->turn->id) {
            app(TurnEngine::class)->advance($this->currentPhase());
        }
    }

    private function runner(string $discordId): Character
    {
        $user = User::factory()->create(['discord_id' => $discordId]);

        return Character::factory()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
            'brawn' => 2,
            'hack' => 2,
        ]);
    }
}
