<?php

namespace Tests\Unit;

use Tests\Support\FrontendBuild;
use Tests\TestCase;

/**
 * Deciding whether the frontend needs building before the suite runs.
 *
 * The decision is the whole of the interesting part: build when the manifest is
 * missing or out of date, and do nothing otherwise. Getting the second half
 * wrong is what would hurt - `php artisan test --filter=SomeTest` is this
 * project's inner loop, and a build on every one of those would cost more than
 * the bug it prevents.
 *
 * Only the decision is tested. Actually shelling out to Vite is not something
 * to do several times in a unit test, and a test that ran the real build would
 * be testing npm.
 */
class FrontendBuildTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/frontend-build-'.bin2hex(random_bytes(6));

        mkdir($this->base.'/public/build', 0777, true);
        mkdir($this->base.'/resources/js', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->base);

        parent::tearDown();
    }

    /**
     * A clean checkout: `public/build` is gitignored, so there is no manifest
     * at all and every test that renders a page would fail.
     */
    public function test_a_missing_manifest_is_stale(): void
    {
        $this->writeSource('app.tsx', at: 1000);

        $this->assertTrue($this->build()->isStale());
    }

    public function test_a_manifest_newer_than_every_source_is_not_stale(): void
    {
        $this->writeSource('app.tsx', at: 1000);
        $this->writeManifest(at: 2000);

        $this->assertFalse($this->build()->isStale());
    }

    /**
     * The case that caused this: somebody adds a page, the manifest that is
     * there does not name it, and a feature test fails inside the framework
     * with a message about Vite rather than about the build.
     */
    public function test_a_source_newer_than_the_manifest_is_stale(): void
    {
        $this->writeManifest(at: 2000);
        $this->writeSource('pages/equipment.tsx', at: 3000);

        $this->assertTrue($this->build()->isStale());
    }

    /**
     * Nested, because a page lives several directories down and the whole tree
     * has to be looked at rather than its top level.
     */
    public function test_a_source_nested_deeply_is_noticed(): void
    {
        $this->writeManifest(at: 2000);
        $this->writeSource('components/ui/deeply/nested/thing.tsx', at: 3000);

        $this->assertTrue($this->build()->isStale());
    }

    public function test_a_changed_config_or_lockfile_is_noticed(): void
    {
        foreach (['vite.config.ts', 'package.json', 'package-lock.json'] as $file) {
            $this->writeManifest(at: 2000);
            file_put_contents($this->base.'/'.$file, '{}');
            touch($this->base.'/'.$file, 3000);

            $this->assertTrue(
                $this->build()->isStale(),
                sprintf('A newer %s should date the manifest.', $file),
            );

            unlink($this->base.'/'.$file);
        }
    }

    /**
     * A source directory this application does not happen to have dates
     * nothing, rather than counting as a reason to build every single run.
     */
    public function test_a_missing_source_directory_is_not_a_reason_to_build(): void
    {
        $this->deleteTree($this->base.'/resources/js');
        $this->writeManifest(at: 2000);

        $this->assertFalse($this->build()->isStale());
    }

    /**
     * The escape hatch, for a machine with no Node or a pipeline that has
     * already built. It must win even when the manifest really is stale,
     * because that is the only case in which anybody would set it.
     */
    public function test_the_skip_flag_wins_over_a_stale_manifest(): void
    {
        $this->writeSource('app.tsx', at: 3000);

        $build = $this->build();
        $this->assertTrue($build->isStale());

        putenv('SKIP_FRONTEND_BUILD=1');

        try {
            $this->assertSame('skipped: SKIP_FRONTEND_BUILD is set', $build->ensure());
        } finally {
            putenv('SKIP_FRONTEND_BUILD');
        }
    }

    /**
     * And a fresh manifest reports itself without going near npm, which is
     * what keeps the inner loop fast.
     */
    public function test_a_fresh_manifest_reports_up_to_date(): void
    {
        $this->writeSource('app.tsx', at: 1000);
        $this->writeManifest(at: 2000);

        $this->assertSame('up to date', $this->build()->ensure());
    }

    private function build(): FrontendBuild
    {
        return new FrontendBuild($this->base);
    }

    private function writeManifest(int $at): void
    {
        $path = $this->base.'/public/build/manifest.json';

        file_put_contents($path, '{}');
        touch($path, $at);
        touch(dirname($path), $at);
    }

    private function writeSource(string $relative, int $at): void
    {
        $path = $this->base.'/resources/js/'.$relative;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, '//');

        // Every directory on the way down as well: a directory's own time
        // moves when a file is added to it, and a stale tree with fresh
        // directories would answer the wrong way round.
        touch($path, $at);

        for ($directory = dirname($path); str_starts_with($directory, $this->base); $directory = dirname($directory)) {
            touch($directory, $at);
        }
    }

    private function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($path);
    }
}
