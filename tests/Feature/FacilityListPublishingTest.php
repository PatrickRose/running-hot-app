<?php

namespace Tests\Feature;

use App\Actions\CreateDefaultFacilities;
use App\Actions\CreateDefaultRoster;
use App\Actions\PublishFacilityList;
use App\Enums\DiscordResourceKind;
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

        Http::assertSent(function ($request): bool {
            $embeds = $request->data()['embeds'] ?? [];

            return $request->method() === 'POST'
                && count($embeds) === 1
                && $embeds[0]['title'] === 'Facilities';
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
        $embed = $payload['embeds'][0];
        $this->assertArrayNotHasKey('stacks', $embed);

        foreach ($embed['fields'] as $field) {
            $this->assertDoesNotMatchRegularExpression('#\d+\s*/\s*\d+#', $field['value']);
        }
    }

    public function test_the_embed_lists_every_corporation_and_its_facilities(): void
    {
        $game = $this->gameWithChannel();

        $payload = FacilityListEmbed::payload($game);
        $fields = $payload['embeds'][0]['fields'];

        $this->assertCount($game->corporations()->count(), $fields);

        $gordon = collect($fields)->firstWhere('name', 'Gordon');
        $this->assertNotNull($gordon);
        $this->assertStringContainsString('Gordon Corporate 1', $gordon['value']);
        $this->assertStringContainsString('Corporate', $gordon['value']);
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

    public function test_the_embed_stays_within_discords_limits(): void
    {
        $game = $this->gameWithChannel();

        $embed = FacilityListEmbed::payload($game)['embeds'][0];

        $this->assertLessThanOrEqual(256, mb_strlen($embed['title']));
        $this->assertLessThanOrEqual(4096, mb_strlen($embed['description']));
        $this->assertLessThanOrEqual(25, count($embed['fields']));

        foreach ($embed['fields'] as $field) {
            $this->assertLessThanOrEqual(256, mb_strlen($field['name']));
            $this->assertLessThanOrEqual(1024, mb_strlen($field['value']));
        }

        $json = json_encode($embed);
        $this->assertIsString($json);
        $this->assertLessThanOrEqual(6000, mb_strlen($json));
    }

    public function test_a_long_facility_list_is_truncated_on_a_line_boundary(): void
    {
        $game = $this->gameWithChannel();
        $corporation = $game->corporations()->orderBy('name')->firstOrFail();
        $type = $game->facilityTypes()->firstOrFail();

        foreach (range(1, 60) as $number) {
            $corporation->facilities()->create([
                'game_id' => $game->id,
                'facility_type_id' => $type->id,
                'name' => sprintf('A Very Long Facility Name Indeed Number %d', $number),
                'available_from_turn' => 1,
            ]);
        }

        $fields = FacilityListEmbed::payload($game->fresh())['embeds'][0]['fields'];
        $field = collect($fields)->firstWhere('name', $corporation->name);

        $this->assertNotNull($field);
        $this->assertLessThanOrEqual(1024, mb_strlen($field['value']));
        $this->assertStringContainsString('more', $field['value']);
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
