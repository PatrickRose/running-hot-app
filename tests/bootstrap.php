<?php

use Tests\Support\FrontendBuild;

/**
 * The suite's bootstrap, which is Composer's plus one thing.
 *
 * Feature tests render Inertia pages, and rendering one needs the Vite
 * manifest - so a clean checkout, or a checkout where somebody has added a
 * page, fails on tests that have nothing to do with the frontend, with a
 * message from inside the framework that does not mention the build.
 *
 * Here rather than in TestCase so it happens once per process instead of once
 * per test, and so it covers every way the suite is started: `php artisan
 * test`, a `--filter` run, `composer test`, an IDE's runner and `--parallel`
 * alike all read phpunit.xml and so all come through this file.
 */

require __DIR__.'/../vendor/autoload.php';

require __DIR__.'/Support/FrontendBuild.php';

$result = (new FrontendBuild(dirname(__DIR__)))->ensure();

// Silent on the ordinary path. A build takes several seconds and a suite that
// appears to hang is worse than one that says what it is doing, so the two
// cases that are not instant say so.
if ($result !== 'up to date') {
    fwrite(STDERR, "Frontend build: {$result}\n");
}
