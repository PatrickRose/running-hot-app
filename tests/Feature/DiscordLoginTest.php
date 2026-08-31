<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class DiscordLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Point the Discord driver at a fixed profile.
     */
    protected function fakeDiscordUser(string $id, ?string $email, string $nickname = 'runner'): void
    {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $id,
            'nickname' => $nickname,
            'name' => $nickname,
            'email' => $email,
            'avatar' => 'https://cdn.discordapp.com/avatars/'.$id.'/abc.png',
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with('discord')->andReturn($driver);

        $this->app->instance(SocialiteFactory::class, $factory);
    }

    public function test_signing_in_claims_the_control_seat_reserved_for_that_handle(): void
    {
        $game = Game::factory()->create();
        $seat = ControlMember::factory()->for($game)->create(['discord_username' => 'patrick_rose']);

        $this->fakeDiscordUser('987654321', 'control@example.com', 'Patrick_Rose');

        $this->get('/auth/discord/callback')->assertRedirect('/dashboard');

        $user = User::query()->where('discord_id', '987654321')->firstOrFail();

        $this->assertSame($user->id, $seat->fresh()->user_id);
        $this->assertTrue($user->isControlFor($game));
        $this->get("/control/games/{$game->id}")->assertOk();
    }

    public function test_a_new_discord_user_gets_an_account_and_is_logged_in(): void
    {
        $this->fakeDiscordUser('123456789', 'runner@example.com', 'Nightshift Jax');

        $this->get('/auth/discord/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'discord_id' => '123456789',
            'email' => 'runner@example.com',
            'discord_username' => 'Nightshift Jax',
        ]);
    }

    public function test_returning_users_are_matched_on_the_discord_id_not_the_username(): void
    {
        $user = User::factory()->create([
            'discord_id' => '123456789',
            'discord_username' => 'Old Handle',
        ]);

        $this->fakeDiscordUser('123456789', 'someone-else@example.com', 'New Handle');

        $this->get('/auth/discord/callback');

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame(1, User::query()->count(), 'A renamed Discord user must not get a second account.');
        $this->assertSame('New Handle', $user->fresh()->discord_username);
    }

    public function test_an_existing_password_account_is_linked_by_email(): void
    {
        $user = User::factory()->control()->create(['email' => 'control@example.com']);

        $this->fakeDiscordUser('999', 'control@example.com', 'Control');

        $this->get('/auth/discord/callback');

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('999', $user->fresh()->discord_id);
        $this->assertTrue($user->fresh()->isControl(), 'Linking must not drop the Control flag.');
        $this->assertSame(1, User::query()->count());
    }

    public function test_a_discord_account_without_an_email_still_works(): void
    {
        $this->fakeDiscordUser('555', null, 'Ghost');

        $this->get('/auth/discord/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'discord_id' => '555',
            'email' => '555@discord.local',
        ]);
    }

    public function test_the_redirect_sends_the_player_to_discord(): void
    {
        config([
            'services.discord.client_id' => 'test-client-id',
            'services.discord.client_secret' => 'test-secret',
            'services.discord.redirect' => 'http://localhost/auth/discord/callback',
        ]);

        $this->get('/auth/discord')->assertRedirectContains('discord.com');
    }

    public function test_the_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_signing_in_claims_the_character_reserved_for_that_handle(): void
    {
        $game = Game::factory()->create();
        $character = Character::factory()->for($game)->create([
            'discord_username' => 'nightshift_jax',
        ]);

        $this->fakeDiscordUser('123456789', 'jax@example.com', 'Nightshift_Jax');

        $this->get('/auth/discord/callback')->assertRedirect('/dashboard');

        $this->assertSame(
            User::query()->where('discord_id', '123456789')->value('id'),
            $character->fresh()->user_id,
        );
    }

    public function test_the_claimed_character_appears_on_the_players_dashboard(): void
    {
        $game = Game::factory()->running()->create();
        Character::factory()->for($game)->create([
            'name' => 'Kestrel Ade',
            'discord_username' => 'ade',
        ]);

        $this->fakeDiscordUser('42', 'ade@example.com', 'ade');
        $this->get('/auth/discord/callback');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Kestrel Ade');
    }
}
