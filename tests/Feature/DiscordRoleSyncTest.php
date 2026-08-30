<?php

namespace Tests\Feature;

use App\Actions\ProvisionDiscordGuild;
use App\Actions\SyncDiscordRolesForUser;
use App\Enums\CharacterRole;
use App\Enums\DiscordResourceKind;
use App\Enums\DiscordSyncStatus;
use App\Enums\GameStatus;
use App\Jobs\SyncDiscordRoles;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\DiscordResource;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use App\Services\Discord\DiscordApi;
use App\Support\Discord\GuildBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\Support\FakeDiscordGuild;
use Tests\TestCase;

class DiscordRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A provisioned game, so its roles exist and are recorded.
     */
    private function provisionedGame(FakeDiscordGuild $guild): Game
    {
        $game = Game::factory()->running()->create(['discord_guild_id' => $guild->guildId]);

        Corporation::factory()->for($game)->create(['name' => 'Aldermarch Dynamics']);
        Gang::factory()->for($game)->create(['name' => 'The Kestrels']);

        app(ProvisionDiscordGuild::class)->handle($game);

        return $game->fresh();
    }

    private function roleId(Game $game, string $key): string
    {
        return (string) DiscordResource::query()
            ->where('game_id', $game->id)
            ->where('kind', DiscordResourceKind::Role)
            ->where('key', $key)
            ->value('discord_id');
    }

    /**
     * Point the Discord driver at a fixed profile, as DiscordLoginTest does.
     */
    private function fakeDiscordUser(string $id, string $nickname): void
    {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $id,
            'nickname' => $nickname,
            'name' => $nickname,
            'email' => $nickname.'@example.com',
            'avatar' => null,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with('discord')->andReturn($driver);

        $this->app->instance(SocialiteFactory::class, $factory);
    }

    public function test_signing_in_queues_a_role_sync(): void
    {
        Queue::fake();

        $this->fakeDiscordUser('4001', 'jax');

        $this->get('/auth/discord/callback')->assertRedirect('/dashboard');

        Queue::assertPushed(SyncDiscordRoles::class);
    }

    public function test_a_corporate_player_gets_their_corporation_and_function_roles(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->provisionedGame($guild);
        $corporation = $game->corporations()->first();

        $user = User::factory()->create(['discord_id' => '4002']);
        $guild->addMember('4002');

        Character::factory()->for($game)->for($corporation)->create([
            'user_id' => $user->id,
            'role' => CharacterRole::Security,
        ]);

        $status = app(SyncDiscordRolesForUser::class)->handle($game, $user);

        $this->assertSame(DiscordSyncStatus::Synced, $status);
        $this->assertEqualsCanonicalizing([
            $this->roleId($game, 'role:corporation:'.$corporation->id),
            $this->roleId($game, GuildBlueprint::functionRoleKey(CharacterRole::Security)),
        ], $guild->members['4002']);
    }

    public function test_a_runner_gets_their_gang_role(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->provisionedGame($guild);
        $gang = $game->gangs()->first();

        $user = User::factory()->create(['discord_id' => '4003']);
        $guild->addMember('4003');

        Character::factory()->for($game)->for($gang)->create([
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
        ]);

        app(SyncDiscordRolesForUser::class)->handle($game, $user);

        $this->assertSame([$this->roleId($game, 'role:gang:'.$gang->id)], $guild->members['4003']);
    }

    public function test_control_gets_the_control_role(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->provisionedGame($guild);

        $user = User::factory()->control()->create(['discord_id' => '4004']);
        $guild->addMember('4004');

        app(SyncDiscordRolesForUser::class)->handle($game, $user);

        $this->assertSame([$this->roleId($game, GuildBlueprint::ROLE_CONTROL)], $guild->members['4004']);
    }

    public function test_a_seat_on_the_control_team_gets_the_control_role(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->provisionedGame($guild);

        $user = User::factory()->create(['discord_id' => '4009']);
        ControlMember::factory()->for($game)->create(['user_id' => $user->id]);
        $guild->addMember('4009');

        app(SyncDiscordRolesForUser::class)->handle($game, $user);

        $this->assertSame([$this->roleId($game, GuildBlueprint::ROLE_CONTROL)], $guild->members['4009']);
    }

    public function test_the_job_syncs_the_games_someone_holds_a_seat_on(): void
    {
        config()->set('services.discord.bot_token', 'test-bot-token');

        $seated = Game::factory()->running()->create(['discord_guild_id' => '9001']);
        $other = Game::factory()->running()->create(['discord_guild_id' => '9002']);
        $user = User::factory()->create(['discord_id' => '4010']);
        ControlMember::factory()->for($seated)->create(['user_id' => $user->id]);

        $synced = [];
        $this->mock(SyncDiscordRolesForUser::class, function ($mock) use (&$synced) {
            $mock->shouldReceive('handle')->andReturnUsing(function (Game $game) use (&$synced) {
                $synced[] = $game->id;

                return DiscordSyncStatus::Synced;
            });
        });

        (new SyncDiscordRoles($user->id))->handle(
            app(SyncDiscordRolesForUser::class),
            app(DiscordApi::class),
        );

        $this->assertSame([$seated->id], $synced);
        $this->assertNotContains($other->id, $synced);
    }

    public function test_a_player_who_has_not_joined_the_server_is_recorded_not_failed(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->provisionedGame($guild);

        $user = User::factory()->create(['discord_id' => '4005']);
        // Deliberately not added to the guild.

        $status = app(SyncDiscordRolesForUser::class)->handle($game, $user);

        $this->assertSame(DiscordSyncStatus::NotAMember, $status);
        $this->assertDatabaseHas('discord_member_syncs', [
            'game_id' => $game->id,
            'user_id' => $user->id,
            'status' => DiscordSyncStatus::NotAMember->value,
        ]);
    }

    public function test_the_dashboard_points_a_player_who_has_not_joined_at_the_invite(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->provisionedGame($guild);

        $user = User::factory()->create(['discord_id' => '4006']);
        Character::factory()->for($game)->for($game->gangs()->first())->create(['user_id' => $user->id]);

        app(SyncDiscordRolesForUser::class)->handle($game, $user);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('discordJoin.invite_url', 'https://discord.gg/runninghot')
                ->where('discordJoin.game_name', $game->name));
    }

    public function test_the_dashboard_says_nothing_once_the_player_has_joined(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->provisionedGame($guild);

        $user = User::factory()->create(['discord_id' => '4007']);
        $guild->addMember('4007');
        Character::factory()->for($game)->for($game->gangs()->first())->create(['user_id' => $user->id]);

        app(SyncDiscordRolesForUser::class)->handle($game, $user);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('discordJoin', null));
    }

    public function test_a_role_the_application_did_not_create_is_left_alone(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->provisionedGame($guild);
        $gang = $game->gangs()->first();

        // A role Control made by hand, which the application knows nothing of.
        $guild->roles['777000000000000001'] = [
            'id' => '777000000000000001',
            'name' => 'Sound Engineer',
            'color' => 0,
            'hoist' => false,
            'mentionable' => false,
        ];

        $user = User::factory()->create(['discord_id' => '4008']);
        $guild->addMember('4008', ['777000000000000001']);

        Character::factory()->for($game)->for($gang)->create([
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
        ]);

        app(SyncDiscordRolesForUser::class)->handle($game, $user);

        $this->assertContains('777000000000000001', $guild->members['4008']);
        $this->assertContains($this->roleId($game, 'role:gang:'.$gang->id), $guild->members['4008']);
    }

    public function test_a_role_from_a_team_the_player_has_left_is_taken_back(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->provisionedGame($guild);
        $gang = $game->gangs()->first();
        $gangRoleId = $this->roleId($game, 'role:gang:'.$gang->id);

        $user = User::factory()->create(['discord_id' => '4009']);
        $guild->addMember('4009');

        $character = Character::factory()->for($game)->for($gang)->create([
            'user_id' => $user->id,
            'role' => CharacterRole::Runner,
        ]);

        app(SyncDiscordRolesForUser::class)->handle($game, $user);
        $this->assertContains($gangRoleId, $guild->members['4009']);

        // Control releases the character, so the player is no longer in the gang.
        $character->forceFill(['user_id' => null])->save();

        app(SyncDiscordRolesForUser::class)->handle($game, $user->fresh());

        $this->assertNotContains($gangRoleId, $guild->members['4009']);
    }

    public function test_the_sync_job_covers_every_unfinished_game_the_player_is_in(): void
    {
        $guild = (new FakeDiscordGuild)->bind();
        $game = $this->provisionedGame($guild);

        $user = User::factory()->create(['discord_id' => '4010']);
        $guild->addMember('4010');
        Character::factory()->for($game)->for($game->gangs()->first())->create(['user_id' => $user->id]);

        // A finished game must not be touched: its roles are history.
        $finished = Game::factory()->create([
            'status' => GameStatus::Finished,
            'discord_guild_id' => $guild->guildId,
        ]);
        Character::factory()->for($finished)->create(['user_id' => $user->id]);

        (new SyncDiscordRoles($user->id))->handle(
            app(SyncDiscordRolesForUser::class),
            app(DiscordApi::class),
        );

        $this->assertDatabaseHas('discord_member_syncs', ['game_id' => $game->id, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('discord_member_syncs', ['game_id' => $finished->id]);
    }

    public function test_nothing_reaches_discord_without_a_bot_token(): void
    {
        // No FakeDiscordGuild::bind(), so the token is blank and TestCase's
        // preventStrayRequests would fail the test if a call were attempted.
        $game = Game::factory()->running()->create(['discord_guild_id' => '900000000000000001']);
        $user = User::factory()->create(['discord_id' => '4011']);

        (new SyncDiscordRoles($user->id))->handle(
            app(SyncDiscordRolesForUser::class),
            app(DiscordApi::class),
        );

        $this->assertDatabaseCount('discord_member_syncs', 0);
    }

    public function test_control_can_resync_everyone_from_the_panel(): void
    {
        Queue::fake();

        $guild = new FakeDiscordGuild;
        $game = Game::factory()->running()->create(['discord_guild_id' => $guild->guildId]);
        $gang = Gang::factory()->for($game)->create();

        $players = User::factory()->count(2)->create(['discord_id' => null]);

        foreach ($players as $index => $player) {
            $player->forceFill(['discord_id' => '50'.$index])->save();
            Character::factory()->for($game)->for($gang)->create(['user_id' => $player->id]);
        }

        // Signed in but with no Discord account linked: nothing to sync.
        User::factory()->create(['discord_id' => null]);

        $control = User::factory()->control()->create(['discord_id' => '5099']);

        $this->actingAs($control)
            ->post("/control/games/{$game->id}/discord/sync-roles")
            ->assertRedirect();

        // Two players plus Control, who belongs in every game's server.
        Queue::assertPushed(SyncDiscordRoles::class, 3);
    }

    public function test_a_player_cannot_trigger_a_resync(): void
    {
        $game = Game::factory()->create(['discord_guild_id' => '900000000000000001']);

        $this->actingAs(User::factory()->create())
            ->post("/control/games/{$game->id}/discord/sync-roles")
            ->assertForbidden();
    }
}
