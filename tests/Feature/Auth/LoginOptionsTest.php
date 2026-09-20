<?php

namespace Tests\Feature\Auth;

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
     * page rather than the developer's one.
     */
    public function test_it_is_off_when_nothing_is_configured(): void
    {
        $this->assertFalse((bool) config('running_hot.direct_login'));
    }
}
