<?php

namespace Tests;

use App\Support\CardImage;
use App\Support\LogoImage;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A test must never reach a real service. Every game carries a Discord
        // webhook and the sync queue driver runs the announcement job inline, so
        // without this the suite would post to whatever those URLs point at.
        //
        // preventStrayRequests makes an unmatched request fail loudly; the bare
        // fake then matches everything, so the default is stubbed rather than
        // noisy. A test wanting to assert on a request just calls Http::fake()
        // again with its own expectations.
        Http::preventStrayRequests();
        Http::fake();

        // The card and faction artwork directories are listed once per process.
        // A test that writes artwork has to be seen by whatever reads it next,
        // so both listings start empty for every test.
        CardImage::flush();
        LogoImage::flush();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
