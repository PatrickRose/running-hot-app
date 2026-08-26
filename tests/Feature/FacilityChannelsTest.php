<?php

namespace Tests\Feature;

use App\Actions\ProvisionDiscordGuild;
use App\Actions\ProvisionFacilityChannels;
use App\Enums\DiscordResourceKind;
use App\Jobs\SyncFacilityChannels;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Game;
use App\Services\Discord\DiscordApi;
use App\Support\Discord\GuildBlueprint;
use App\Support\Discord\PlannedChannel;
use App\Support\Discord\PlannedOverwrite;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeDiscordGuild;
use Tests\TestCase;

/**
 * Every Facility gets a private text and voice channel, which is where its Runs
 * will happen.
 */
class FacilityChannelsTest extends TestCase
{
    use RefreshDatabase;

    private function facilityFor(Game $game, Corporation $corporation, string $name): Facility
    {
        /** @var FacilityType $type */
        $type = $game->facilityTypes()->where('key', FacilityTypeBlueprint::RESEARCH)->sole();

        return Facility::factory()->for($corporation)->for($type)->create(['name' => $name]);
    }

    private function channel(GuildBlueprint $blueprint, string $key): PlannedChannel
    {
        foreach ($blueprint->channels() as $channel) {
            if ($channel->key === $key) {
                return $channel;
            }
        }

        $this->fail("No planned channel with key [{$key}].");
    }

    public function test_the_blueprint_plans_a_text_and_voice_channel_per_facility(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        $blueprint = new GuildBlueprint($game);

        $text = $this->channel($blueprint, GuildBlueprint::facilityChannelKey($facility, 'text'));
        $voice = $this->channel($blueprint, GuildBlueprint::facilityChannelKey($facility, 'voice'));

        $this->assertSame(DiscordResourceKind::TextChannel, $text->kind);
        $this->assertSame('gordon-tower', $text->name);
        $this->assertSame(DiscordResourceKind::VoiceChannel, $voice->kind);
        $this->assertSame('Gordon Tower', $voice->name);
    }

    public function test_a_facilitys_channels_sit_in_their_corporations_own_category(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        $blueprint = new GuildBlueprint($game);
        $expected = GuildBlueprint::facilityCategoryKey($corporation);

        $category = $this->channel($blueprint, $expected);

        $this->assertSame(DiscordResourceKind::Category, $category->kind);
        $this->assertSame('Gordon Facilities', $category->name);

        // Separate from the team category, so eight Facilities do not bury the
        // two channels a Corporation's players actually talk in.
        $this->assertNotSame('category:corporation:'.$corporation->id, $expected);

        foreach (['text', 'voice'] as $kind) {
            $this->assertSame(
                $expected,
                $this->channel($blueprint, GuildBlueprint::facilityChannelKey($facility, $kind))->parentKey,
            );
        }
    }

    public function test_a_facility_channel_is_hidden_from_everyone_but_control_and_its_owner(): void
    {
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $rival = Corporation::factory()->for($game)->create(['name' => 'Genetic Equity']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        $blueprint = new GuildBlueprint($game);
        $text = $this->channel($blueprint, GuildBlueprint::facilityChannelKey($facility, 'text'));

        $targets = array_map(
            fn (PlannedOverwrite $overwrite): string => $overwrite->target,
            $text->overwrites,
        );

        $this->assertContains(PlannedOverwrite::EVERYONE, $targets);
        $this->assertContains(GuildBlueprint::ROLE_CONTROL, $targets);
        $this->assertContains(GuildBlueprint::corporationRoleKey($corporation), $targets);
        $this->assertNotContains(GuildBlueprint::corporationRoleKey($rival), $targets);

        foreach ($text->overwrites as $overwrite) {
            if ($overwrite->target === PlannedOverwrite::EVERYONE) {
                $this->assertSame(DiscordApi::VIEW_CHANNEL, $overwrite->deny);
                $this->assertSame(0, $overwrite->allow);
            }
        }
    }

    /**
     * The channel is a place to talk, not a place to leak. What is installed in
     * a Facility is Secret (rulebook 3.4.2).
     */
    public function test_nothing_in_a_channel_name_or_topic_reveals_the_defences(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = Game::factory()->create(['discord_guild_id' => $guild->guildId]);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        $planned = GuildBlueprint::channelsForFacility($facility);
        $words = json_encode(array_map(
            fn (PlannedChannel $channel): array => [$channel->name, $channel->topic ?? ''],
            $planned,
        ));

        $this->assertIsString($words);

        foreach (['card', 'Card', 'slot', 'Slot', 'physical', 'cyber'] as $leak) {
            $this->assertStringNotContainsString($leak, $words);
        }
    }

    public function test_a_corporation_with_no_facilities_gets_no_category(): void
    {
        $game = Game::factory()->create();
        Corporation::factory()->for($game)->create(['name' => 'Gordon']);

        $keys = array_map(
            fn (PlannedChannel $channel): string => $channel->key,
            (new GuildBlueprint($game))->channels(),
        );

        $this->assertNotContains('category:corporation:1:facilities', $keys);
    }

    /**
     * Two channels each, in one category per Corporation, so the ceiling is 25
     * Facilities. A game whose Corporations open with five has room to spare.
     */
    public function test_a_corporations_facilities_fit_in_their_category(): void
    {
        Queue::fake();

        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Wide Corp']);

        foreach (range(1, 24) as $number) {
            $this->facilityFor($game, $corporation, 'Site '.$number);
        }

        $key = GuildBlueprint::facilityCategoryKey($corporation);

        $inCategory = collect((new GuildBlueprint($game))->channels())
            ->where('parentKey', $key)
            ->count();

        $this->assertSame(48, $inCategory);
        $this->assertLessThanOrEqual(GuildBlueprint::MAX_CHANNELS_PER_CATEGORY, $inCategory);
    }

    public function test_provisioning_creates_the_channels(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = Game::factory()->create(['discord_guild_id' => $guild->guildId]);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        app(ProvisionDiscordGuild::class)->handle($game);

        foreach (['text', 'voice'] as $kind) {
            $this->assertDatabaseHas('discord_resources', [
                'game_id' => $game->id,
                'key' => GuildBlueprint::facilityChannelKey($facility, $kind),
            ]);
        }
    }

    /**
     * The recovery path. A Facility can miss its channels two ways: it was
     * built before the game had a Discord server at all, or the queued job hit
     * a Discord that was down and failed soft. Either way the next full
     * provision run must pick it up.
     */
    public function test_a_full_provision_run_gives_an_older_facility_its_channels(): void
    {
        Queue::fake();

        // No guild yet, so nothing could have created channels for this one.
        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        $this->assertSame(0, $game->discordResources()->count());

        // Control attaches the server and provisions it later.
        $guild = (new FakeDiscordGuild)->bind();
        $game->forceFill(['discord_guild_id' => $guild->guildId])->save();

        app(ProvisionDiscordGuild::class)->handle($game->fresh());

        foreach (['text', 'voice'] as $kind) {
            $this->assertDatabaseHas('discord_resources', [
                'game_id' => $game->id,
                'key' => GuildBlueprint::facilityChannelKey($facility, $kind),
            ]);
        }

        $this->assertDatabaseHas('discord_resources', [
            'game_id' => $game->id,
            'key' => GuildBlueprint::facilityCategoryKey($corporation),
        ]);
    }

    public function test_provisioning_rebuilds_a_channel_deleted_by_hand(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = Game::factory()->create(['discord_guild_id' => $guild->guildId]);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        app(ProvisionDiscordGuild::class)->handle($game);

        $key = GuildBlueprint::facilityChannelKey($facility, 'text');
        $resource = $game->discordResources()->where('key', $key)->sole();

        // Someone deletes it in Discord: the record survives, the channel does not.
        unset($guild->channels[$resource->discord_id]);

        app(ProvisionDiscordGuild::class)->handle($game->fresh());

        $rebuilt = $game->discordResources()->where('key', $key)->sole();

        $this->assertNotSame($resource->discord_id, $rebuilt->discord_id);
        $this->assertArrayHasKey($rebuilt->discord_id, $guild->channels);
    }

    public function test_provisioning_twice_does_not_make_a_second_pair(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = Game::factory()->create(['discord_guild_id' => $guild->guildId]);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $this->facilityFor($game, $corporation, 'Gordon Tower');

        $action = app(ProvisionDiscordGuild::class);
        $action->handle($game);
        $before = $game->discordResources()->count();

        $action->handle($game);

        $this->assertSame($before, $game->discordResources()->count());
    }

    /**
     * End to end on the sync queue: the model hook is what actually gets a
     * mid-game Facility its channels, without anyone running a full provision.
     */
    public function test_a_facility_built_mid_game_gets_its_channels_without_a_full_run(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = Game::factory()->create(['discord_guild_id' => $guild->guildId]);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $this->facilityFor($game, $corporation, 'Gordon Tower');

        app(ProvisionDiscordGuild::class)->handle($game);
        $before = $game->discordResources()->count();

        // Built after the server was provisioned, as a requisition would be.
        $later = $this->facilityFor($game, $corporation, 'Attercliffe Yard');

        foreach (['text', 'voice'] as $kind) {
            $this->assertDatabaseHas('discord_resources', [
                'game_id' => $game->id,
                'key' => GuildBlueprint::facilityChannelKey($later, $kind),
            ]);
        }

        // Its own two channels and nothing else: no second category, and no
        // re-recording of what was already there.
        $this->assertSame($before + 2, $game->discordResources()->count());
    }

    public function test_the_action_creates_the_pair_when_called_directly(): void
    {
        Queue::fake();

        $guild = (new FakeDiscordGuild)->bind();
        $game = Game::factory()->create(['discord_guild_id' => $guild->guildId]);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        $created = app(ProvisionFacilityChannels::class)->handle($facility);

        $this->assertCount(2, $created);
        $this->assertContains(GuildBlueprint::facilityChannelKey($facility, 'text'), $created);
        $this->assertContains(GuildBlueprint::facilityChannelKey($facility, 'voice'), $created);
    }

    public function test_the_mid_game_path_makes_the_category_when_it_is_missing(): void
    {
        Queue::fake();

        $guild = (new FakeDiscordGuild)->bind();
        $game = Game::factory()->create(['discord_guild_id' => $guild->guildId]);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        // Never provisioned, so the Corporation has no Facilities category.
        app(ProvisionFacilityChannels::class)->handle($facility);

        $this->assertDatabaseHas('discord_resources', [
            'game_id' => $game->id,
            'key' => GuildBlueprint::facilityCategoryKey($corporation),
        ]);
    }

    public function test_the_mid_game_path_is_idempotent(): void
    {
        Queue::fake();

        $guild = (new FakeDiscordGuild)->bind();
        $game = Game::factory()->create(['discord_guild_id' => $guild->guildId]);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        $action = app(ProvisionFacilityChannels::class);
        $action->handle($facility);

        $this->assertSame([], $action->handle($facility->fresh()));
    }

    public function test_a_game_with_no_discord_server_gets_no_channels(): void
    {
        config()->set('services.discord.bot_token', 'testing-token');

        $game = Game::factory()->create();
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        $this->assertSame([], app(ProvisionFacilityChannels::class)->handle($facility));
        $this->assertSame(0, $game->discordResources()->count());
    }

    public function test_building_a_facility_queues_the_sync(): void
    {
        Queue::fake();

        $game = Game::factory()->create(['discord_guild_id' => '900000000000000001']);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        Queue::assertPushed(
            SyncFacilityChannels::class,
            fn (SyncFacilityChannels $job): bool => $job->facilityId === $facility->id,
        );
    }

    public function test_the_job_does_nothing_without_a_bot(): void
    {
        config()->set('services.discord.bot_token', null);

        $game = Game::factory()->create(['discord_guild_id' => '900000000000000001']);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        // Would throw if it reached Discord: TestCase prevents stray requests.
        app(SyncFacilityChannels::class, ['facilityId' => $facility->id])
            ->handle(app(ProvisionFacilityChannels::class), app(DiscordApi::class));

        $this->assertSame(0, $game->discordResources()->count());
    }

    /**
     * The sync queue driver runs the job inline, so a throw here would come
     * back out of Facility::create(). A CEO must be able to sign off a build
     * while Discord is down.
     */
    public function test_a_discord_failure_does_not_stop_a_facility_being_built(): void
    {
        config()->set('services.discord.bot_token', 'testing-token');

        Http::swap(new Factory(app('events')));
        Http::preventStrayRequests();
        Http::fake(['discord.com/api/*' => Http::response(['message' => 'Server Error'], 500)]);

        $game = Game::factory()->create(['discord_guild_id' => '900000000000000001']);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);

        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        $this->assertTrue($facility->exists);
        $this->assertDatabaseHas('facilities', ['id' => $facility->id]);
        $this->assertSame(0, $game->discordResources()->count());
    }

    public function test_removing_a_facility_leaves_its_channel_alone(): void
    {
        Queue::fake();

        $guild = (new FakeDiscordGuild)->bind();
        $game = Game::factory()->create(['discord_guild_id' => $guild->guildId]);
        $corporation = Corporation::factory()->for($game)->create(['name' => 'Gordon']);
        $facility = $this->facilityFor($game, $corporation, 'Gordon Tower');

        app(ProvisionFacilityChannels::class)->handle($facility);
        $key = GuildBlueprint::facilityChannelKey($facility, 'text');

        $facility->delete();

        // The Run that happened in it is still worth reading.
        $this->assertDatabaseHas('discord_resources', ['game_id' => $game->id, 'key' => $key]);
    }
}
