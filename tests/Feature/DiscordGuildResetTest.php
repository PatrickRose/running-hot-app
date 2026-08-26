<?php

namespace Tests\Feature;

use App\Actions\ProvisionDiscordGuild;
use App\Actions\ResetDiscordGuild;
use App\Enums\DiscordProvisionStatus;
use App\Enums\DiscordResourceKind;
use App\Models\Corporation;
use App\Models\DiscordResource;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDiscordGuild;
use Tests\TestCase;

/**
 * Clearing a game's Discord server so it can be provisioned from nothing.
 *
 * The deliberate exception to everything else here being additive. A test
 * server collects the leavings of every half-finished run, and at some point
 * the only useful thing to do with it is empty it.
 */
class DiscordGuildResetTest extends TestCase
{
    use RefreshDatabase;

    private function control(): User
    {
        return User::factory()->control()->create();
    }

    private function gameWithRoster(FakeDiscordGuild $guild): Game
    {
        $game = Game::factory()->create([
            'name' => 'Running Hot',
            'discord_guild_id' => $guild->guildId,
        ]);

        Corporation::factory()->for($game)->create(['name' => 'Aldermarch Dynamics']);
        Gang::factory()->for($game)->create(['name' => 'The Kestrels']);

        return $game;
    }

    public function test_resetting_empties_the_server(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $this->assertNotEmpty($guild->channels);

        $tally = app(ResetDiscordGuild::class)->handle($game->fresh());

        $this->assertSame([], $guild->channels);
        $this->assertGreaterThan(0, $tally['channels_deleted']);
        $this->assertGreaterThan(0, $tally['roles_deleted']);

        // The default role survives, because Discord does not let anybody
        // delete it. Nothing else does. (The map is keyed by snowflake, which
        // PHP has quietly turned into an integer key, hence the cast.)
        $this->assertSame(
            [$guild->guildId],
            array_map(strval(...), array_keys($guild->roles)),
        );
    }

    public function test_resetting_takes_things_the_application_never_made(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        // Control's own channels and roles. Provisioning would never touch
        // these, and that is the whole difference between it and this.
        $category = $guild->seedChannel('Control Business', DiscordResourceKind::Category->channelType());
        $guild->seedChannel('bot-spam', DiscordResourceKind::TextChannel->channelType(), $category);
        $guild->seedRole('Server Booster');

        $game = $this->gameWithRoster($guild);

        app(ResetDiscordGuild::class)->handle($game);

        $this->assertSame([], $guild->channels);
        $this->assertNull($guild->roleNamed('Server Booster'));
    }

    public function test_a_managed_role_is_left_where_it_is(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        // An integration's role, including the bot's own. Discord refuses to
        // delete these, so they are never even asked for.
        $guild->seedRole('running-hot-bot', ['managed' => true]);

        $game = $this->gameWithRoster($guild);

        $tally = app(ResetDiscordGuild::class)->handle($game);

        $this->assertNotNull($guild->roleNamed('running-hot-bot'));
        $this->assertSame(0, $tally['refused'], 'A managed role should be skipped, not attempted and refused.');
    }

    public function test_children_are_deleted_before_their_category(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $category = $guild->channelNamed('The Kestrels');
        $this->assertNotNull($category);
        $childIds = array_column($guild->childrenOf($category['id']), 'id');
        $this->assertNotEmpty($childIds);

        app(ResetDiscordGuild::class)->handle($game->fresh());

        // Discord orphans a category's children rather than deleting them, so
        // taking the category first would leave them behind at the top level.
        // Everything went, so the order was right.
        $this->assertSame([], $guild->channels);
    }

    public function test_resetting_forgets_every_snowflake_and_the_webhook(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);
        $game->refresh();

        $this->assertNotNull($game->discord_webhook_url);
        $this->assertNotNull($game->discord_invite_url);

        app(ResetDiscordGuild::class)->handle($game);
        $game->refresh();

        // Every one of these now points at something that no longer exists.
        $this->assertSame(0, DiscordResource::query()->where('game_id', $game->id)->count());
        $this->assertNull($game->discord_webhook_url);
        $this->assertNull($game->discord_invite_url);
        $this->assertSame(DiscordProvisionStatus::Idle, $game->discord_provision_status);
        $this->assertNull($game->discord_provisioned_at);

        // The server itself is still attached: the reset is of its contents.
        $this->assertSame($guild->guildId, $game->discord_guild_id);
    }

    public function test_provisioning_after_a_reset_builds_the_whole_server_again(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        $first = app(ProvisionDiscordGuild::class)->handle($game);

        app(ResetDiscordGuild::class)->handle($game->fresh());

        $second = app(ProvisionDiscordGuild::class)->handle($game->fresh());

        // Nothing left to adopt, so it is the same run as the very first one.
        $this->assertSame($first['roles_created'], $second['roles_created']);
        $this->assertSame($first['channels_created'], $second['channels_created']);
        $this->assertSame(0, $second['roles_adopted']);
        $this->assertSame(0, $second['channels_adopted']);
        $this->assertNotNull($guild->roleNamed('Control'));
        $this->assertNotNull($guild->channelNamed('announcements'));
    }

    public function test_control_can_reset_from_the_panel(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord/reset", ['confirm' => 'Running Hot'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([], $guild->channels);
        $this->assertStringContainsString('Server cleared', (string) $game->fresh()->discord_provision_message);
    }

    public function test_the_game_name_has_to_be_typed_out(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);
        $channelsBefore = count($guild->channels);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord/reset", ['confirm' => 'running hot'])
            ->assertSessionHasErrors('confirm');

        $this->assertCount($channelsBefore, $guild->channels, 'Nothing may be deleted without the confirmation.');
    }

    public function test_a_reset_with_no_confirmation_at_all_is_refused(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord/reset")
            ->assertSessionHasErrors('confirm');

        $this->assertNotEmpty($guild->channels);
    }

    public function test_a_player_cannot_reset_a_server(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/discord/reset", ['confirm' => 'Running Hot'])
            ->assertForbidden();

        $this->assertNotEmpty($guild->channels);
    }

    public function test_resetting_needs_a_server_and_a_bot(): void
    {
        // No bind(), so no bot token, and no guild on the game either.
        $game = Game::factory()->create(['name' => 'Running Hot']);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord/reset", ['confirm' => 'Running Hot'])
            ->assertSessionHasErrors('confirm');
    }

    public function test_a_refusal_on_one_object_does_not_abandon_the_wipe(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        // A role above the bot's own in the hierarchy. Every guild has at least
        // one, so treating a 403 as fatal would mean the wipe never finished.
        $guild->seedRole('Owner', ['managed' => false]);

        $game = $this->gameWithRoster($guild);
        app(ProvisionDiscordGuild::class)->handle($game);

        $guild->refuseRoleDeletes = ['Owner'];

        $tally = app(ResetDiscordGuild::class)->handle($game->fresh());

        $this->assertSame(1, $tally['refused']);
        $this->assertNotNull($guild->roleNamed('Owner'));
        $this->assertSame([], $guild->channels, 'Everything else still went.');
        $this->assertStringContainsString('1 refused', (string) $game->fresh()->discord_provision_message);
    }
}
