<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\EnsureDirectLoginIsEnabled;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the login page offers, and what it says before it offers anything.
 *
 * A seat is claimed by Discord handle, so an account made any other way is an
 * account holding nothing. The shipped setting therefore offers Discord alone;
 * the application's own ways in are for development, where DemoGameSeeder
 * prints a password login per character.
 */
class LoginOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_discord_is_offered_unless_the_environment_says_otherwise(): void
    {
        config(['running_hot.direct_login' => false]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/login')
                ->where('canLoginDirectly', false));
    }

    public function test_the_environment_can_turn_the_other_ways_in_back_on(): void
    {
        config(['running_hot.direct_login' => true]);

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canLoginDirectly', true));
    }

    /**
     * Off by default, so a deployment that never sets it gets the Discord-only
     * page rather than the developer's one. The rest of the suite runs on this
     * default; the handful of tests whose subject is the password form turn it
     * back on in their own setUp.
     */
    public function test_it_is_off_when_nothing_is_configured(): void
    {
        $this->assertFalse((bool) config('running_hot.direct_login'));
    }

    /**
     * And hiding the form is not the whole of it. A hidden form whose endpoint
     * still takes credentials is a signpost pretending to be a lock, so the
     * first pipe in the login pipeline refuses the post outright.
     */
    public function test_a_password_is_refused_outright_while_it_is_off(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => EnsureDirectLoginIsEnabled::REFUSAL]);

        $this->assertGuest();

        // And the refusal names the way in that does work. A bare "these
        // credentials do not match our records" would have somebody retyping a
        // password that was never going to be looked at.
        $this->assertStringContainsString('Discord', EnsureDirectLoginIsEnabled::REFUSAL);
    }

    /**
     * A correct password is still refused, and so is a wrong one - the point
     * is that the credentials are never reached at all.
     */
    public function test_the_right_password_is_refused_just_the_same(): void
    {
        config(['running_hot.direct_login' => true]);
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->assertAuthenticated();

        auth()->logout();
        config(['running_hot.direct_login' => false]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->assertGuest();
    }
}
