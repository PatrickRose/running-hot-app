<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The front door. There is no landing page: a visitor is sent to the login
 * form and somebody already signed in is sent to their dashboard, so the one
 * URL everybody types lands them where they were going.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_is_sent_to_the_login_form(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_somebody_signed_in_is_sent_to_their_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }
}
