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

    /**
     * Every test here names the posture it is about rather than inheriting one.
     * The setting is read from the environment, and `composer setup` copies
     * .env.example into place - which turns it on - so a test that relied on
     * the ambient value passed on a machine with no .env and failed in CI.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['running_hot.direct_login' => false]);
    }

    public function test_only_discord_is_offered_unless_the_environment_says_otherwise(): void
    {
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
     * The shipped default is off, so a deployment that sets nothing gets the
     * Discord-only page rather than the developer's one.
     *
     * Read out of the config file with the variable taken out of the
     * environment, rather than off the resolved value: .env.example turns this
     * on for development and CI copies it into place, so what `config()`
     * answers during a test says nothing about what a deployment that sets
     * nothing would get. This is the line that would actually change that.
     */
    public function test_the_shipped_default_is_off(): void
    {
        // Taken out of the superglobals rather than through the Env
        // repository, which is immutable here: clear() on it is a silent
        // no-op, so the variable would still have been found.
        $previous = $_ENV['RUNNING_HOT_DIRECT_LOGIN'] ?? null;

        unset($_ENV['RUNNING_HOT_DIRECT_LOGIN'], $_SERVER['RUNNING_HOT_DIRECT_LOGIN']);
        putenv('RUNNING_HOT_DIRECT_LOGIN');

        try {
            $shipped = require config_path('running_hot.php');

            $this->assertFalse($shipped['direct_login']);
        } finally {
            if ($previous !== null) {
                $_ENV['RUNNING_HOT_DIRECT_LOGIN'] = $previous;
                putenv('RUNNING_HOT_DIRECT_LOGIN='.$previous);
            }
        }
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
