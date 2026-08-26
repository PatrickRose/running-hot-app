<?php

namespace Tests\Feature;

use App\Actions\CreateDefaultFacilities;
use App\Actions\CreateDefaultRoster;
use App\Actions\PublishFacilityList;
use App\Enums\DiscordResourceKind;
use App\Models\Corporation;
use App\Models\DiscordResource;
use App\Models\Game;
use App\Models\User;
use App\Services\TurnEngine;
use App\Support\Discord\FacilityListEmbed;
use App\Support\Discord\GuildBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Posting the Facility list to #facility-list (rulebook 3.3).
 */
class FacilityListPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.discord.bot_token', 'testing-token');
    }

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    /**
     * Replace the base TestCase's catch-all fake so these stubs are the ones
     * that match. Stubs are tried in registration order, so without the swap
     * the catch-all answers first with an empty body.
     *
     * @param  array<string, mixed>  $stubs
     */
    protected function fakeDiscord(array $stubs = []): void
    {
        Http::swap(new Factory(app('events')));
        Http::preventStrayRequests();
        Http::fake($stubs === [] ? null : $stubs);
    }

    protected function gameWithChannel(): Game
    {
        $game = Game::factory()->create(['discord_guild_id' => '900000000000000001']);

        app(CreateDefaultRoster::class)->handle($game);
        app(CreateDefaultFacilities::class)->handle($game);

        DiscordResource::factory()->create([
            'game_id' => $game->id,
            'kind' => DiscordResourceKind::TextChannel,
            'key' => GuildBlueprint::CHANNEL_FACILITY_LIST,
            'discord_id' => '900000000000000009',
            'name' => 'facility-list',
        ]);

        return $game;
    }

    public function test_publishing_posts_an_embed_and_records_the_message(): void
    {
        $game = $this->gameWithChannel();

        $this->fakeDiscord([
            'discord.com/api/*/channels/900000000000000009/messages' => Http::response(['id' => '123456789']),
        ]);

        $result = app(PublishFacilityList::class)->handle($game);

        $this->assertSame('posted', $result['action']);
        $this->assertSame('123456789', $result['message_id']);

        $this->assertDatabaseHas('discord_resources', [
            'game_id' => $game->id,
            'key' => PublishFacilityList::MESSAGE_KEY,
            'discord_id' => '123456789',
        ]);

        Http::assertSent(function ($request) use ($game): bool {
            $embeds = $request->data()['embeds'] ?? [];

            return $request->method() === 'POST'
                && count($embeds) === $game->corporations()->count();
        });
    }

    public function test_publishing_again_edits_the_same_message(): void
    {
        $game = $this->gameWithChannel();

        $this->fakeDiscord([
            'discord.com/api/*' => Http::response(['id' => '123456789']),
        ]);

        $action = app(PublishFacilityList::class);
        $action->handle($game);
        $result = $action->handle($game);

        $this->assertSame('edited', $result['action']);

        // One POST to create it, then a PATCH rather than a second POST: a
        // channel of superseded lists is worse than no list.
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
            && str_contains($request->url(), '/messages/123456789'));
    }

    /**
     * The channel is visible to Runners, and rulebook 3.4.2 says the number of
     * Protection Cards a Facility contains is Secret.
     */
    public function test_the_embed_never_says_how_many_cards_a_facility_holds(): void
    {
        $game = $this->gameWithChannel();

        $payload = FacilityListEmbed::payload($game);
        $json = json_encode($payload);
        $this->assertIsString($json);

        $this->assertGreaterThan(0, $game->protectionCardTypes()->count());

        foreach ($game->protectionCardTypes as $card) {
            $this->assertStringNotContainsString($card->name, $json);
        }

        // Nor the shape a stack count would arrive in.
        foreach ($payload['embeds'] as $embed) {
            $this->assertArrayNotHasKey('fields', $embed);
            $this->assertDoesNotMatchRegularExpression('#\d+\s*/\s*\d+#', $embed['description']);
        }
    }

    public function test_each_corporation_gets_its_own_embed(): void
    {
        $game = $this->gameWithChannel();

        $embeds = FacilityListEmbed::payload($game)['embeds'];

        $this->assertCount($game->corporations()->count(), $embeds);

        $this->assertSame(
            $game->corporations()->orderBy('name')->pluck('name')->all(),
            array_column($embeds, 'title'),
        );

        $gordon = collect($embeds)->firstWhere('title', 'Gordon');
        $this->assertNotNull($gordon);
        $this->assertStringContainsString('Gordon Tower', $gordon['description']);
        $this->assertStringContainsString('Corporate', $gordon['description']);
    }

    /**
     * The same colour as the Discord role its players already wear, so a
     * Corporation looks the same everywhere.
     */
    public function test_an_embed_is_coloured_like_its_corporations_role(): void
    {
        $game = $this->gameWithChannel();

        $embeds = FacilityListEmbed::payload($game)['embeds'];
        $gordon = collect($embeds)->firstWhere('title', 'Gordon');

        $this->assertNotNull($gordon);
        $this->assertSame(GuildBlueprint::colourFor('Gordon'), $gordon['color']);
    }

    public function test_the_heading_and_the_timestamp_bracket_the_list(): void
    {
        $game = $this->gameWithChannel();

        $embeds = FacilityListEmbed::payload($game)['embeds'];
        $last = count($embeds) - 1;

        $this->assertGreaterThan(1, count($embeds));

        // Read as one list: a heading at the top, the timestamp at the bottom.
        $this->assertArrayHasKey('author', $embeds[0]);
        $this->assertArrayNotHasKey('author', $embeds[1]);
        $this->assertArrayHasKey('timestamp', $embeds[$last]);
        $this->assertArrayNotHasKey('timestamp', $embeds[0]);
        $this->assertStringContainsString('Before the game began', $embeds[$last]['footer']['text']);
    }

    public function test_a_game_with_no_corporations_says_so(): void
    {
        $game = Game::factory()->create();

        $embeds = FacilityListEmbed::payload($game)['embeds'];

        $this->assertCount(1, $embeds);
        $this->assertSame('Nothing built yet', $embeds[0]['title']);
    }

    public function test_a_building_facility_is_marked_as_building(): void
    {
        $game = $this->gameWithChannel();
        app(TurnEngine::class)->start($game);

        $game->facilities()->first()?->forceFill(['available_from_turn' => 5])->save();

        $payload = FacilityListEmbed::payload($game->fresh());
        $json = json_encode($payload);

        $this->assertIsString($json);
        $this->assertStringContainsString('building', $json);
    }

    public function test_the_embeds_stay_within_discords_limits(): void
    {
        $game = $this->gameWithChannel();

        $embeds = FacilityListEmbed::payload($game)['embeds'];

        $this->assertLessThanOrEqual(FacilityListEmbed::MAX_EMBEDS, count($embeds));

        foreach ($embeds as $embed) {
            $this->assertLessThanOrEqual(256, mb_strlen($embed['title']));
            $this->assertLessThanOrEqual(
                FacilityListEmbed::MAX_DESCRIPTION,
                mb_strlen($embed['description']),
            );
        }

        $json = json_encode($embeds);
        $this->assertIsString($json);
        $this->assertLessThanOrEqual(FacilityListEmbed::MAX_TOTAL_CHARACTERS, mb_strlen($json));
    }

    /**
     * Discord takes at most ten embeds in a message, so a game with more
     * Corporations than that is told what it is not seeing rather than being
     * quietly short of a few.
     */
    public function test_more_corporations_than_discord_allows_embeds_are_reported(): void
    {
        $game = $this->gameWithChannel();

        foreach (range(1, 8) as $number) {
            Corporation::factory()->for($game)->create([
                'name' => sprintf('Zzz Holdings %d', $number),
            ]);
        }

        $embeds = FacilityListEmbed::payload($game->fresh())['embeds'];
        $last = count($embeds) - 1;

        $this->assertCount(FacilityListEmbed::MAX_EMBEDS, $embeds);
        $this->assertStringContainsString('not shown', $embeds[$last]['footer']['text']);
    }

    public function test_a_long_facility_list_is_truncated_on_a_line_boundary(): void
    {
        $game = $this->gameWithChannel();
        $corporation = $game->corporations()->orderBy('name')->firstOrFail();
        $type = $game->facilityTypes()->firstOrFail();

        foreach (range(1, 200) as $number) {
            $corporation->facilities()->create([
                'game_id' => $game->id,
                'facility_type_id' => $type->id,
                'name' => sprintf('A Very Long Facility Name Indeed Number %d', $number),
                'available_from_turn' => 1,
            ]);
        }

        $embeds = FacilityListEmbed::payload($game->fresh())['embeds'];
        $embed = collect($embeds)->firstWhere('title', $corporation->name);

        $this->assertNotNull($embed);
        $this->assertLessThanOrEqual(
            FacilityListEmbed::MAX_DESCRIPTION,
            mb_strlen($embed['description']),
        );
        $this->assertStringContainsString('more', $embed['description']);
    }

    public function test_a_game_without_a_channel_is_refused(): void
    {
        $game = Game::factory()->create();

        $this->expectException(ValidationException::class);

        app(PublishFacilityList::class)->handle($game);
    }

    public function test_a_game_without_a_bot_token_is_refused(): void
    {
        config()->set('services.discord.bot_token', null);
        $game = $this->gameWithChannel();

        $this->expectException(ValidationException::class);

        app(PublishFacilityList::class)->handle($game);
    }

    public function test_control_can_publish_from_the_panel(): void
    {
        $game = $this->gameWithChannel();

        $this->fakeDiscord([
            'discord.com/api/*' => Http::response(['id' => '123456789']),
        ]);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/facilities/publish-list")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(app(PublishFacilityList::class)->hasBeenPublished($game));
    }

    public function test_a_player_cannot_publish(): void
    {
        $game = $this->gameWithChannel();

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/facilities/publish-list")
            ->assertForbidden();
    }

    public function test_the_turn_clock_refreshes_a_published_list(): void
    {
        $game = $this->gameWithChannel();

        $this->fakeDiscord([
            'discord.com/api/*' => Http::response(['id' => '123456789']),
        ]);

        app(PublishFacilityList::class)->handle($game);

        $engine = app(TurnEngine::class);
        $phase = $engine->start($game);
        $phase = $engine->advance($phase);
        $phase = $engine->advance($phase);
        $engine->advance($phase);

        // Turn 2's Setup rewrote it.
        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH');
    }

    public function test_the_turn_clock_never_posts_a_list_nobody_asked_for(): void
    {
        $game = $this->gameWithChannel();

        $this->fakeDiscord();

        $engine = app(TurnEngine::class);
        $phase = $engine->start($game);
        $phase = $engine->advance($phase);
        $phase = $engine->advance($phase);
        $engine->advance($phase);

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/messages'));
        $this->assertFalse(app(PublishFacilityList::class)->hasBeenPublished($game));
    }

    public function test_a_refresh_that_fails_does_not_stall_the_clock(): void
    {
        $game = $this->gameWithChannel();

        $this->fakeDiscord([
            'discord.com/api/*' => Http::response(['id' => '123456789']),
        ]);
        app(PublishFacilityList::class)->handle($game);

        $this->fakeDiscord([
            'discord.com/api/*' => Http::response(['message' => 'Internal Server Error'], 500),
        ]);

        $engine = app(TurnEngine::class);
        $phase = $engine->start($game);
        $phase = $engine->advance($phase);
        $phase = $engine->advance($phase);

        // The clock rolled into turn 2 regardless.
        $this->assertSame(2, $engine->advance($phase)->turn->number);
    }
}
