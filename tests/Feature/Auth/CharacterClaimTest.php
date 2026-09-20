<?php

namespace Tests\Feature\Auth;

use App\Actions\ClaimCharactersByEmail;
use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Http\Controllers\Auth\CharacterClaimController;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * "I signed up to this game with this email address."
 *
 * The way back in for somebody Control could not reach by Discord handle -
 * mistyped, renamed, or never given. The boundary worth pinning is that it
 * only ever takes a seat nobody holds, because the address itself proves
 * nothing: see the note on trust in CLAUDE.md.
 */
class CharacterClaimTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
    }

    /**
     * Point the Discord driver at a fixed profile.
     */
    private function fakeDiscordUser(string $id, ?string $email, string $nickname): void
    {
        $socialiteUser = (new SocialiteUser)->map([
            'id' => $id,
            'nickname' => $nickname,
            'name' => $nickname,
            'email' => $email,
            'avatar' => null,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with('discord')->andReturn($driver);

        $this->app->instance(SocialiteFactory::class, $factory);
    }

    private function seat(array $attributes = []): Character
    {
        return Character::factory()->create([
            'game_id' => $this->game->id,
            'role' => CharacterRole::Runner,
            ...$attributes,
        ]);
    }

    public function test_the_page_renders_for_a_visitor(): void
    {
        $this->get(route('claim'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/claim'));
    }

    /**
     * A visitor is sent through the Discord sign in, because that is where a
     * seat gets bound to an account. The address waits in the session.
     */
    public function test_a_visitor_is_sent_to_discord_with_the_address_held(): void
    {
        $this->seat(['email' => 'jack@example.com']);

        $this->post(route('claim.store'), ['email' => 'Jack@Example.com '])
            ->assertRedirect(route('auth.discord'))
            ->assertSessionHas(CharacterClaimController::PENDING, 'jack@example.com');
    }

    /**
     * The same submit over XHR, which is how the form actually posts.
     *
     * The test above passes either way, and that is exactly how this shipped
     * broken: a test request without Inertia's own headers takes the ordinary
     * redirect path, while the browser takes the other one. An XHR follows the
     * hop to discord.com itself, carrying the X-XSRF-TOKEN header Inertia puts
     * on every request - so the browser preflights it against Discord, Discord
     * does not allow that header, and the submit dies as a CORS failure with
     * nothing on screen to say so.
     *
     * A 409 with X-Inertia-Location hands the navigation back to the browser.
     * Asserting the target rather than only the status matters: an asset
     * version mismatch answers 409 with this header too, and names the current
     * URL rather than Discord's.
     */
    public function test_an_inertia_submit_hands_the_navigation_back_to_the_browser(): void
    {
        $this->seat(['email' => 'jack@example.com']);

        $this->withHeaders(['X-Inertia' => 'true'])
            ->post(route('claim.store'), ['email' => 'jack@example.com'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('auth.discord'));

        $this->assertSame('jack@example.com', session(CharacterClaimController::PENDING));
    }

    /**
     * Somebody already signed in - because signing in worked, it just found
     * them nothing - is bound on the spot rather than sent round again.
     */
    public function test_somebody_signed_in_is_bound_on_the_spot(): void
    {
        $character = $this->seat(['email' => 'jack@example.com']);
        $user = User::factory()->create(['discord_username' => 'jack_s']);

        $this->actingAs($user)
            ->post(route('claim.store'), ['email' => 'jack@example.com'])
            ->assertRedirect(route('dashboard'));

        $character->refresh();

        $this->assertSame($user->id, $character->user_id);

        // And the handle goes on too, so the Control panel shows the seat as
        // linked and every later sign in claims it the ordinary way.
        $this->assertSame('jack_s', $character->discord_username);
    }

    /**
     * The round trip end to end: name the address, come back from Discord, and
     * the seat is bound to the account that came back.
     */
    public function test_the_address_is_redeemed_when_discord_hands_an_account_back(): void
    {
        $character = $this->seat([
            'email' => 'jack@example.com',
            // The handle Control typed, which is the one that never matched.
            'discord_username' => 'jack_scanton_typo',
        ]);

        $this->post(route('claim.store'), ['email' => 'jack@example.com'])
            ->assertRedirect(route('auth.discord'));

        $this->fakeDiscordUser('555000111', 'jack@gmail.com', 'jack_s');

        $this->get('/auth/discord/callback')->assertRedirect('/dashboard');

        $user = User::query()->where('discord_id', '555000111')->firstOrFail();

        $character->refresh();

        $this->assertSame($user->id, $character->user_id);
        $this->assertSame('jack_s', $character->discord_username);

        // And the address does not sit in the session waiting to fire again.
        $this->assertNull(session(CharacterClaimController::PENDING));
    }

    /**
     * The Discord account's own email has nothing to do with it. Control has
     * the address off a sign-up sheet; what Discord hands back is whatever
     * that account was made with.
     */
    public function test_the_discord_accounts_own_email_is_not_what_is_matched(): void
    {
        $character = $this->seat(['email' => 'jack@example.com']);

        $this->post(route('claim.store'), ['email' => 'jack@example.com']);

        $this->fakeDiscordUser('555000222', 'something-else@gmail.com', 'jack_s');
        $this->get('/auth/discord/callback');

        $this->assertNotNull($character->fresh()->user_id);
    }

    public function test_an_address_nobody_is_reserved_for_is_refused(): void
    {
        $this->seat(['email' => 'jack@example.com']);

        $this->post(route('claim.store'), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing(CharacterClaimController::PENDING);
    }

    /**
     * The whole of what stops the address being a way to walk off with
     * somebody else's character.
     */
    public function test_a_seat_somebody_already_holds_is_refused(): void
    {
        $held = User::factory()->create();
        $character = $this->seat(['email' => 'jack@example.com', 'user_id' => $held->id]);

        $chancer = User::factory()->create();

        $this->actingAs($chancer)
            ->post(route('claim.store'), ['email' => 'jack@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame($held->id, $character->fresh()->user_id);
    }

    public function test_holding_the_seat_already_says_so_rather_than_refusing_blankly(): void
    {
        $user = User::factory()->create();
        $this->seat(['email' => 'jack@example.com', 'user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('claim.store'), ['email' => 'jack@example.com'])
            ->assertSessionHasErrors(['email' => 'You already hold every seat reserved for that address.']);
    }

    /**
     * A finished game is not somewhere anybody needs letting into.
     */
    public function test_a_finished_games_roster_is_not_matched(): void
    {
        $over = Game::factory()->create(['status' => GameStatus::Finished]);

        Character::factory()->create([
            'game_id' => $over->id,
            'role' => CharacterRole::Runner,
            'email' => 'jack@example.com',
        ]);

        $this->post(route('claim.store'), ['email' => 'jack@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_the_action_takes_every_unheld_seat_on_the_address(): void
    {
        $second = Game::factory()->create(['status' => GameStatus::Running]);

        $this->seat(['email' => 'jack@example.com']);
        Character::factory()->create([
            'game_id' => $second->id,
            'role' => CharacterRole::Runner,
            'email' => 'jack@example.com',
        ]);

        $user = User::factory()->create();

        $claimed = app(ClaimCharactersByEmail::class)->handle($user, 'jack@example.com');

        $this->assertCount(2, $claimed);
    }

    /**
     * A password account has no handle, so writing null over what Control
     * reserved would throw away the thing they typed in.
     */
    public function test_a_reserved_handle_survives_a_claim_that_brings_none(): void
    {
        $character = $this->seat([
            'email' => 'jack@example.com',
            'discord_username' => 'jack_s',
        ]);

        $user = User::factory()->create(['discord_username' => null]);

        app(ClaimCharactersByEmail::class)->handle($user, 'jack@example.com');

        $this->assertSame('jack_s', $character->fresh()->discord_username);
    }

    public function test_the_address_is_matched_however_it_was_typed(): void
    {
        $character = $this->seat(['email' => 'Jack@Example.COM']);

        $this->assertSame('jack@example.com', $character->fresh()->email);

        $user = User::factory()->create();

        $this->assertCount(1, app(ClaimCharactersByEmail::class)->handle($user, '  JACK@example.com '));
    }
}
