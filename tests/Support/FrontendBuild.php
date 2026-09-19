<?php

namespace Tests\Support;

/**
 * Builds the frontend before the suite runs, when it needs building.
 *
 * Rendering an Inertia page asks Vite for the page's entry in
 * `public/build/manifest.json` and throws when it is not there, so a feature
 * test touching a page fails on a clean checkout - where `public/build` is
 * gitignored and so does not exist - and again whenever somebody adds a page,
 * because the manifest that is there does not name it yet. Neither failure
 * says so: what you get is `Unable to locate file in Vite manifest`, from a
 * stack trace inside the framework, on a test that has nothing to do with the
 * build.
 *
 * CI never hit this because `composer setup` builds first. This is the same
 * guarantee for every other way the suite is run.
 *
 * **It builds only when the manifest is missing or out of date**, which is what
 * makes it affordable. `php artisan test --filter=SomeTest` is the inner loop
 * this project runs constantly, and a build on every one of those would cost
 * more than the bug does. A fresh manifest costs a few hundred `stat` calls and
 * nothing else.
 *
 * Set `SKIP_FRONTEND_BUILD=1` to turn it off - for a machine with no Node, or a
 * pipeline that has already built.
 */
class FrontendBuild
{
    /** Everything Vite bundles. A change under any of these dates the manifest. */
    private const SOURCES = [
        'resources/js',
        'resources/css',
        'resources/fonts',
        'vite.config.ts',
        'package.json',
        'package-lock.json',
    ];

    private const MANIFEST = 'public/build/manifest.json';

    /**
     * The lock every process takes before building.
     *
     * `--parallel` runs this bootstrap once per worker, and without a lock a
     * stale manifest would have all of them shell out to Vite at the same
     * moment, against the same output directory. The first one through builds
     * and the rest wait, then find the manifest fresh and do nothing.
     */
    private const LOCK = 'storage/framework/frontend-build.lock';

    public function __construct(private readonly string $basePath) {}

    /**
     * Build if the manifest is missing or older than the sources.
     *
     * Never throws: a suite that cannot build is a suite that should run and
     * fail on the tests that actually need the manifest, with a line saying
     * why, rather than one that refuses to start. Returns what it did, so the
     * bootstrap can say so.
     */
    public function ensure(): string
    {
        if (getenv('SKIP_FRONTEND_BUILD')) {
            return 'skipped: SKIP_FRONTEND_BUILD is set';
        }

        if (! $this->isStale()) {
            return 'up to date';
        }

        $handle = $this->lock();

        try {
            // Somebody else may have built it while this process waited for the
            // lock, which is the whole point of taking one.
            if (! $this->isStale()) {
                return 'up to date';
            }

            return $this->build();
        } finally {
            if ($handle !== null) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }

    /**
     * Whether the manifest is missing, or older than anything that goes into
     * it.
     *
     * Modification times rather than hashes: this runs before every suite, and
     * hashing the whole frontend to save a build that only happens when
     * something changed would cost more than it saves. A touched file that did
     * not really change buys one unnecessary build.
     */
    public function isStale(): bool
    {
        $manifest = $this->path(self::MANIFEST);

        if (! is_file($manifest)) {
            return true;
        }

        $built = (int) filemtime($manifest);

        foreach (self::SOURCES as $source) {
            if ($this->newestUnder($this->path($source)) > $built) {
                return true;
            }
        }

        return false;
    }

    /**
     * The newest modification time at or under a path, or 0 where there is
     * nothing there.
     *
     * A missing source is not an error: `resources/fonts` is a directory this
     * application happens to have, and one that does not exist simply dates
     * nothing.
     */
    private function newestUnder(string $path): int
    {
        if (is_file($path)) {
            return (int) filemtime($path);
        }

        if (! is_dir($path)) {
            return 0;
        }

        $newest = (int) filemtime($path);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $newest = max($newest, (int) $file->getMTime());
        }

        return $newest;
    }

    private function build(): string
    {
        $command = 'npm run build 2>&1';

        $output = [];
        $status = 0;

        $cwd = getcwd();
        chdir($this->basePath);

        try {
            exec($command, $output, $status);
        } finally {
            if ($cwd !== false) {
                chdir($cwd);
            }
        }

        if ($status !== 0) {
            // Reported rather than thrown, so the suite still runs. The tests
            // that need the manifest will fail, and this is the line that says
            // why they did.
            return sprintf(
                "FAILED (exit %d). Feature tests that render a page will fail.\n%s",
                $status,
                implode("\n", array_slice($output, -15)),
            );
        }

        return 'built';
    }

    /**
     * @return resource|null
     */
    private function lock()
    {
        $path = $this->path(self::LOCK);
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            return null;
        }

        $handle = @fopen($path, 'c');

        if ($handle === false) {
            // No lock is worse than a lock and better than not running: the
            // worst case is two workers building at once on a machine where
            // storage/ is not writable, which is already broken.
            return null;
        }

        flock($handle, LOCK_EX);

        return $handle;
    }

    private function path(string $relative): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.$relative;
    }
}
