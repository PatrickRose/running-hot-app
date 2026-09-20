<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dark unless the reader has said otherwise.
 *
 * The default is decided in three places that have to agree - this middleware,
 * the `@class` on the html element and the inline script above it - because a
 * server painting one theme and a client swapping to the other is the flash
 * that inline script exists to prevent.
 */
class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_first_visit_is_dark(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="en" class="dark"', false);
    }

    public function test_a_reader_who_has_chosen_light_gets_light(): void
    {
        $this->withUnencryptedCookie('appearance', 'light')
            ->get(route('login'))
            ->assertOk()
            ->assertDontSee('class="dark"', false);
    }

    /**
     * Following the system is still a setting; it is just no longer what you
     * get for saying nothing. The server cannot know what the system prefers,
     * so it paints light and the inline script corrects it before paint.
     */
    public function test_following_the_system_is_still_available(): void
    {
        $this->withUnencryptedCookie('appearance', 'system')
            ->get(route('login'))
            ->assertOk()
            ->assertSee("const appearance = 'system';", false);
    }
}
