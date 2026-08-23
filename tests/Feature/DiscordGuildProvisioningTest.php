<?php

namespace Tests\Feature;

use App\Actions\ProvisionDiscordGuild;
use App\Enums\DiscordProvisionStatus;
use App\Enums\DiscordResourceKind;
use App\Enums\DiscordSyncStatus;
use App\Models\Corporation;
use App\Models\DiscordMemberSync;
use App\Models\DiscordResource;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDiscordGuild;
use Tests\TestCase;

class DiscordGuildProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function control(): User
    {
        return User::factory()->control()->create();
    }

    /**
     * A game with a roster and a guild set, ready to provision.
     */
    protected function gameWithRoster(FakeDiscordGuild $guild): Game
    {
        $game = Game::factory()->create(['discord_guild_id' => $guild->guildId]);

        Corporation::factory()->for($game)->create(['name' => 'Aldermarch Dynamics']);
        Gang::factory()->for($game)->create(['name' => 'The Kestrels']);

        return $game;
    }

    public function test_control_can_point_a_game_at_a_discord_server(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord", ['discord_guild_id' => '900000000000000001'])
            ->assertRedirect();

        $this->assertSame('900000000000000001', $game->fresh()->discord_guild_id);
    }

    public function test_something_that_is_not_a_snowflake_is_rejected(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord", ['discord_guild_id' => 'my-cool-server'])
            ->assertSessionHasErrors('discord_guild_id');

        $this->assertNull($game->fresh()->discord_guild_id);
    }

    public function test_a_player_cannot_change_the_discord_settings(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/discord", ['discord_guild_id' => '900000000000000001'])
            ->assertForbidden();
    }

    public function test_provisioning_needs_a_server(): void
    {
        $game = Game::factory()->create();

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord/provision")
            ->assertSessionHasErrors('discord_guild_id');
    }

    public function test_provisioning_needs_a_bot_token(): void
    {
        $game = Game::factory()->create(['discord_guild_id' => '900000000000000001']);

        // The token is blank in the test environment, which is what a
        // deployment that has not set one up looks like.
        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord/provision")
            ->assertSessionHasErrors('discord_guild_id');

        $this->assertSame(DiscordProvisionStatus::Idle, $game->fresh()->discord_provision_status);
    }

    public function test_provisioning_creates_the_roles_the_game_needs(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        foreach (['Control', 'Corp CEO', 'Corp Security', 'Corp Research', 'Aldermarch Dynamics', 'The Kestrels'] as $name) {
            $this->assertNotNull($guild->roleNamed($name), "Expected a [{$name}] role in the guild.");
        }
    }

    public function test_provisioning_creates_a_private_category_per_team(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $category = $guild->channelNamed('The Kestrels');

        $this->assertNotNull($category);
        $this->assertSame(DiscordResourceKind::Category->channelType(), $category['type']);

        // A text and a voice channel, both parented to the category.
        $children = $guild->childrenOf($category['id']);
        $this->assertCount(2, $children);

        // @everyone is denied sight of the category itself.
        $everyone = collect($category['permission_overwrites'])
            ->firstWhere('id', $guild->guildId);

        $this->assertNotNull($everyone);
        $this->assertSame('1024', $everyone['deny']);
    }

    public function test_provisioning_records_everything_it_made(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $this->assertDatabaseHas('discord_resources', [
            'game_id' => $game->id,
            'key' => 'role:control',
            'kind' => DiscordResourceKind::Role->value,
        ]);

        $this->assertSame(
            count($guild->roles) - 1, // less @everyone, which the guild came with
            DiscordResource::query()->where('game_id', $game->id)->where('kind', DiscordResourceKind::Role)->count(),
        );
    }

    public function test_running_it_twice_reconciles_instead_of_duplicating(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        $first = app(ProvisionDiscordGuild::class)->handle($game);
        $rolesAfterFirst = count($guild->roles);
        $channelsAfterFirst = count($guild->channels);

        $second = app(ProvisionDiscordGuild::class)->handle($game->fresh());

        $this->assertGreaterThan(0, $first['roles_created']);
        $this->assertSame(0, $second['roles_created'], 'A second run must not create another set of roles.');
        $this->assertSame(0, $second['channels_created']);
        $this->assertSame($rolesAfterFirst, count($guild->roles));
        $this->assertSame($channelsAfterFirst, count($guild->channels));
    }

    public function test_a_team_added_after_provisioning_gets_its_roles_on_the_next_run(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        Gang::factory()->for($game)->create(['name' => 'Sixth Signal']);

        $tally = app(ProvisionDiscordGuild::class)->handle($game->fresh());

        $this->assertSame(1, $tally['roles_created']);
        $this->assertNotNull($guild->roleNamed('Sixth Signal'));
        $this->assertNotNull($guild->channelNamed('Sixth Signal'));
    }

    public function test_renaming_a_team_renames_its_role_rather_than_making_a_second_one(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $gang = $game->gangs()->first();
        $roleId = DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('key', 'role:gang:'.$gang->id)
            ->value('discord_id');

        $gang->update(['name' => 'The Kestrels (reformed)']);

        $tally = app(ProvisionDiscordGuild::class)->handle($game->fresh());

        $this->assertSame(0, $tally['roles_created']);
        $this->assertSame(1, $tally['roles_updated']);
        $this->assertSame('The Kestrels (reformed)', $guild->roles[$roleId]['name']);
        $this->assertNull($guild->roleNamed('The Kestrels'));
    }

    public function test_a_role_deleted_by_hand_in_discord_is_rebuilt(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $controlRoleId = DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('key', 'role:control')
            ->value('discord_id');

        // Somebody deletes it in the Discord UI.
        unset($guild->roles[$controlRoleId]);

        $tally = app(ProvisionDiscordGuild::class)->handle($game->fresh());

        $this->assertSame(1, $tally['roles_created']);
        $this->assertNotNull($guild->roleNamed('Control'));
        $this->assertNotSame($controlRoleId, DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('key', 'role:control')
            ->value('discord_id'));
    }

    public function test_provisioning_never_deletes_anything(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        // A gang leaving the game must not take a Discord channel — and its
        // history — with it. Control tidies up by hand if they want to.
        $game->gangs()->first()->delete();

        app(ProvisionDiscordGuild::class)->handle($game->fresh());

        $this->assertSame(0, $guild->countCalls('DELETE', '/channels/'));
        $this->assertNotNull($guild->channelNamed('The Kestrels'));
    }

    public function test_provisioning_gives_the_game_its_own_webhook_and_invite(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);
        $originalWebhook = $game->discord_webhook_url;

        app(ProvisionDiscordGuild::class)->handle($game);

        $game->refresh();

        $this->assertNotSame($originalWebhook, $game->discord_webhook_url);
        $this->assertStringStartsWith('https://discord.com/api/webhooks/', $game->discord_webhook_url);
        $this->assertSame('https://discord.gg/runninghot', $game->discord_invite_url);

        // The webhook belongs to the announcements channel.
        $announcements = $guild->channelNamed('announcements');
        $this->assertNotNull($announcements);
        $this->assertSame($announcements['id'], collect($guild->webhooks)->first()['channel_id']);
    }

    public function test_a_webhook_control_has_repointed_is_left_alone(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        // Control decides announcements should go somewhere else.
        $chosen = 'https://discord.com/api/webhooks/123456789012345678/control-picked-this';
        $game->fresh()->update(['discord_webhook_url' => $chosen]);

        app(ProvisionDiscordGuild::class)->handle($game->fresh());

        $this->assertSame($chosen, $game->fresh()->discord_webhook_url);
    }

    public function test_a_missing_bot_is_reported_rather_than_half_provisioning(): void
    {
        $guild = (new FakeDiscordGuild)->bind();

        // A guild the bot is not in: Discord answers the very first call with a
        // 404, which is the common "you forgot to invite the bot" case.
        $game = Game::factory()->create(['discord_guild_id' => '999999999999999999']);

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord/provision")
            ->assertRedirect();

        $game->refresh();

        $this->assertSame(DiscordProvisionStatus::Failed, $game->discord_provision_status);
        $this->assertStringContainsString('Unknown Guild', (string) $game->discord_provision_message);

        // It gave up on the guild lookup, so nothing was created anywhere: the
        // only role in the fake guild is the @everyone it started with.
        $this->assertCount(1, $guild->roles, 'Only the @everyone the guild came with.');
        $this->assertSame(0, DiscordResource::query()->where('game_id', $game->id)->count());
    }

    public function test_provisioning_through_the_panel_reports_success(): void
    {
        $game = $this->gameWithRoster((new FakeDiscordGuild)->bind());

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord/provision")
            ->assertRedirect();

        $game->refresh();

        $this->assertSame(DiscordProvisionStatus::Completed, $game->discord_provision_status);
        $this->assertNotNull($game->discord_provisioned_at);
        $this->assertStringContainsString('role(s) created', (string) $game->discord_provision_message);
    }

    public function test_the_factories_build_usable_records(): void
    {
        $resource = DiscordResource::factory()->channel()->create();
        $sync = DiscordMemberSync::factory()->notAMember()->create();

        $this->assertSame(DiscordResourceKind::TextChannel, $resource->kind);
        $this->assertSame(DiscordSyncStatus::NotAMember, $sync->status);
    }

    public function test_moving_a_game_to_a_different_server_forgets_the_old_snowflakes(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->gameWithRoster($guild);

        app(ProvisionDiscordGuild::class)->handle($game);

        $this->assertGreaterThan(0, DiscordResource::query()->where('game_id', $game->id)->count());

        $this->actingAs($this->control())
            ->post("/control/games/{$game->id}/discord", ['discord_guild_id' => '900000000000000002'])
            ->assertRedirect();

        // Those snowflakes live in the old guild; patching them in the new one
        // would either fail or, worse, edit someone else's server.
        $this->assertSame(0, DiscordResource::query()->where('game_id', $game->id)->count());
        $this->assertSame(DiscordProvisionStatus::Idle, $game->fresh()->discord_provision_status);
    }
}
