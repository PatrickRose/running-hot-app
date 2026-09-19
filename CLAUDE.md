<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

# Running Hot

This application runs the mechanics and timing for **Running Hot**, a pre-cyberpunk megagame by Patrick Rose. The game is played live on Discord: this app owns the rules and the clock, Discord carries the conversation.

The rulebook is the source of truth. When a rule and this codebase disagree, the rulebook wins.

It lives at `docs/running-hot-rulebook.pdf`, with a grepped-friendly extraction beside it at `docs/running-hot-rulebook.txt` — reach for the text first, since reading the PDF costs thirty pages. The text is derived and regenerable; the PDF is authoritative, and the research suit icons do not survive extraction.

## The shape of a game

A turn is three phases on a wall clock — **Setup (15m) → Action (15m) → Team Time (5m)** — and the game runs many turns back to back during a single live session. Control (the organisers) sit above the whole thing and can override anything.

Players are either **Corporate** (CEO, Security, Research) grouped into Corporations, or **Runners** and **Freelancers** grouped into gangs.

## Rules that the code must not break

**Control always wins.** The rulebook defers to Control constantly ("if reasonable, Control will typically give you the opportunity"). Every computed value must stay editable by Control. Never build a mechanic the organisers cannot override mid-game.

**Never invent a rule the rulebook does not state.** Where the rules are silent, expose a value for Control to set rather than deriving one. Three live examples:
- Income is *not* calculated from anything. It is the abstraction of a corporation's stock price, so stock price is not modelled at all.
- Removing a Tag costs 3 Credits and is the player's choice, so upkeep never does it automatically.
- A Facility build cost comes from its type's own price on the type sheet, and Control can name another — MCM's Construction Leader technology is a discount on exactly this. What is *not* derived is Income: more Corporate Facilities is a *reason* for Control to raise it, never a formula that raises it.

**Never write a tracker directly.** All movement of Income, Political Will, Credits, Research Points, Notoriety, Wounds, Tags, Stability and Civil Unrest goes through `TrackerService`, which writes a `tracker_adjustments` row recording before, after, delta, actor and reason. That ledger is how Control answers "why did that number change?" three turns later. `$model->update(['wounds' => ...])` bypasses it and is a bug.

**The clock is server-authoritative.** A phase stores an absolute `ends_at` that extensions and pauses mutate directly; remaining time is always derived from it. The browser only counts down between polls and must never be able to make a phase run long.

**And it is on every page.** The whole game runs to the phase clock, so a player needs it wherever they are rather than on the handful of pages that happened to ask for a game: `HandleInertiaRequests` shares the phase and `AppSidebarHeader` draws it in a sticky header. It is shared with `Inertia::always()` rather than as a plain shared prop, because an ordinary one is filtered straight out of a partial reload — the research page polls `only: ['research']`, and the clock would have frozen between full visits. An always prop ignores the filter, so every poll any page already makes re-anchors the clock for free, and the header's own `only: ['phase']` poll covers the pages that do not poll at all.

## Naming decisions

- **Brawn**, not Brute. The rulebook uses both for the same runner skill (p.19 vs p.24 and p.26). See `App\Enums\Tracker`.
- **Character** is a player's role in a game. **User** is the login. They are deliberately separate so Control can set up a game before anyone signs in.

## Where things live

| Concern | Location |
|---|---|
| Phase transitions, pause/resume/extend | `App\Services\TurnEngine` |
| Tracker writes and the audit ledger | `App\Services\TrackerService` |
| Facility slots, card stacks, reorder and removal costs | `App\Services\FacilityDefenceService` |
| Building a Facility, and the turn's delay | `App\Actions\RequisitionFacility` |
| A game's starting Facility types | `App\Support\FacilityTypeBlueprint`, `App\Actions\SeedFacilityTypes` |
| The game's agenda deck | `App\Support\AgendaCardBlueprint`, `App\Actions\SeedAgendaCards` |
| A game's starting Facilities and card holdings | `config/running_hot.php`, `App\Actions\CreateDefaultFacilities` |
| The three card lists | `App\Support\ProtectionCardBlueprint`, `EquipmentCardBlueprint`, `TechnologyBlueprint` |
| Seeding them into a game | `App\Actions\SeedProtectionCards`, `SeedEquipmentCards`, `SeedTechnologies`, `SeedProtectionCardHoldings` |
| Finding a card's artwork from its code | `App\Support\CardImage` |
| Finding a faction's logo from its name | `App\Support\LogoImage` |
| A faction's logo and colour in one payload | `App\Support\FactionBadge`, `resources/js/components/faction-badge.tsx` |
| What each icon in the game's font means | `App\Support\IconFont` |
| The Run loop, and every consequence of it | `App\Services\RunEngine` |
| What each side of a run may see | `App\Support\RunPresenter` |
| Who may do what on a run | `App\Policies\RunPolicy` |
| The run screen players work from | `App\Http\Controllers\RunController`, `resources/js/pages/runs.tsx` |
| Run arithmetic: ordering, alerts, strength, dice | `App\Support\Runs\*` |
| What a successful run takes out of a Facility | `App\Enums\RunAccessKind`, `TechnologyAccessAction`, `App\Support\Runs\AccessCheck` |
| Team Time income and wound recovery | `App\Actions\ApplyTeamTimeUpkeep` |
| The Council's agenda, voting and attendance | `App\Services\CouncilService` |
| A Council seat that is not a Corporation | `characters.council_votes`, `App\Models\Character::sitsOnCouncil()` |
| What each person at the Council may see | `App\Support\CouncilPresenter` |
| Who may chair, and who may vote | `App\Policies\CouncilSessionPolicy`, `App\Policies\AgendaCardPolicy` |
| The Council Chamber players read | `App\Http\Controllers\CouncilController`, `resources/js/pages/council.tsx` |
| The rules of an equation, and what one pays | `App\Support\Equation` |
| The research card game: dealing, playing, scoring, the deck | `App\Services\ResearchTableService` |
| A game's starting research decks | `config/running_hot.php`, `App\Actions\SeedResearchDecks` |
| The technologies a Corporation opens holding | `App\Support\TechnologyBlueprint`, `App\Actions\GrantStartingTechnologies` |
| Spending Research Points, and everything after | `App\Services\TechnologyService` |
| Research payload shaping | `App\Support\ResearchPresenter` |
| The research table on screen | `resources/js/components/research-table.tsx` |
| Discord announcements | `App\Services\DiscordAnnouncer` |
| Who Control is, per game | `App\Models\ControlMember`, `App\Actions\ClaimControlSeatsForUser` |
| What a game's Discord server should look like | `App\Support\Discord\GuildBlueprint` |
| The `#facility-list` embed, and posting it | `App\Support\Discord\FacilityListEmbed`, `App\Actions\PublishFacilityList` |
| Building and reconciling that server | `App\Actions\ProvisionDiscordGuild` |
| A Facility's own channels | `App\Actions\ProvisionFacilityChannels`, `App\Jobs\SyncFacilityChannels` |
| Letting the Runners into the Facility they are hitting | `App\Actions\GrantRunChannelAccess`, `App\Jobs\SyncRunChannelAccess` |
| Handing a player their Discord roles | `App\Actions\SyncDiscordRolesForUser` |
| Discord REST calls as the bot | `App\Services\Discord\DiscordApi` |
| Inertia payload shaping | `App\Support\GamePresenter` |
| What a player may see of the Facilities | `App\Http\Controllers\FacilityBoardController` |
| Security arranging their own stacks | `App\Http\Controllers\FacilityDefenceController`, `App\Policies\FacilityPolicy` |
| The drag-and-drop defence board | `resources/js/components/facility-defence-board.tsx` |
| Auto-advance and its backstop | `App\Jobs\AdvancePhase`, `game:tick` |

## Gotchas that have already cost time

- **Delayed jobs do nothing useful on the `sync` queue driver** — it ignores `delay()` and runs immediately. `TurnEngine` therefore skips scheduling auto-advance under `sync`, and `AdvancePhase` returns rather than re-dispatching when it fires early. Re-dispatching there caused an infinite loop.
- **Auto-advance needs a queue worker, and the backstop needs the scheduler.** In production run both `queue:work` and `schedule:work`. `composer run dev` starts a worker but not the scheduler.
- **Wayfinder's generated modules are gitignored.** `resources/js/routes`, `resources/js/actions` and `resources/js/wayfinder` do not exist in a clean checkout, so `tsc` cannot resolve imports until `php artisan wayfinder:generate --with-form` (or `npm run build`) has run. Always pass `--with-form`: without it the `.form()` helpers vanish and starter-kit pages break.
- **Feature tests need built frontend assets, and the suite now builds them itself.** Rendering an Inertia page asks Vite for that page's entry in `public/build/manifest.json` and throws without it, which bit twice: on a clean checkout, where `public/build` is gitignored and so absent, and every time somebody added a page, because the manifest that was there did not name it yet. Neither failure said so — what you got was `Unable to locate file in Vite manifest`, from a stack trace inside the framework, on a test with nothing to do with the build. `tests/bootstrap.php` is `phpunit.xml`'s bootstrap now and runs `Tests\Support\FrontendBuild`, which builds when the manifest is missing or older than anything under `resources/js`, `resources/css`, `resources/fonts`, `vite.config.ts`, `package.json` or `package-lock.json`.

  **It builds only when that is true, and the "only" is the point.** `php artisan test --compact --filter=SomeTest` is this project's inner loop and a build on every one of those would cost more than the bug does: a fresh manifest is a few hundred `stat` calls, about 0.1s, and silent. A stale one says `Frontend build: built` so a suite that pauses for ten seconds has said why. In the bootstrap rather than `TestCase` so it happens once per process instead of once per test, and so `php artisan test`, a `--filter` run, `composer test`, an IDE's runner and `--parallel` all get it — they all read `phpunit.xml`. `--parallel` is why there is a lock: without one, a stale manifest would have every worker shell out to Vite at the same moment against the same output directory.

  A failed build is **reported, not thrown**: the suite still runs, the tests that need the manifest still fail, and the line above them says the build is why. `SKIP_FRONTEND_BUILD=1` turns it off for a machine with no Node. CI still runs `composer setup`, which still builds — the guard then finds the manifest fresh and does nothing, so nothing in CI got slower and it is a backstop if that step ever moves.
- **Freshly created models may not have every column hydrated.** Cast defensively when reading a boolean straight after `create()`.
- **`->with('status', ...)` only arrives because `HandleInertiaRequests` shares it.** Ninety-odd controllers end a redirect that way and none of it reached the browser until it was: the message was written, flashed, and thrown away one redirect later, so an install, a reorder and a played equation all happened in silence. It is shared under `flash` rather than as a bare `status` because the auth pages take a `status` prop of their own and draw it in a panel, and `use-flash-toast` reads it off the *visit* rather than out of a render — two identical messages in a row are normal, and a toast keyed on a changed value would show the second one nothing. It has to be an `Inertia::always()` prop, and that is not tidiness: a partial reload does not carry an ordinary shared prop, so the client keeps the one it already had, and a listener firing on every successful visit then re-announced the same message on every five-second poll for as long as the page stayed open. Resolved on every response, it is null again the moment the flash has been read.
- **A refusal has to be drawn somewhere.** A page posting with `router.post` gets no `errors` of its own the way an Inertia `<Form>` does, so it has to read them off `usePage()`. The research table is the one that had to learn this: `App\Support\Equation` reports every refusal against `equation`, nothing rendered that key, and an equation the rules would not take looked exactly like a dead button.
- **...and on the run screen it has to be drawn *per panel*.** Same bug one layer along, and it made every refusal on a run silent: an unaffordable Boost, a Charge out of step, a top-up the Corporation could not cover. A page-level `errors` is no good there because several runs are on screen at once and all of them report against the same handful of keys, so one group's refusal would appear under every panel. `useRunAction()` in `run-panel.tsx` is the answer the research table's `ScoreForm` already uses — each desk posts through it and keeps its own message. Two things swallowed a refusal on the way to being found: an `<input type="number" max={...}>` on the top-up, whose browser-side constraint blocked the submit outright so no post was ever made, and `requirePurse` pinning an `s` on the end of "Credit of budget".
- **`Collection::sortBy()` given an array reads closures as *comparators*, not key extractors.** So `sortBy([fn ($x) => $x->a, fn ($x) => $x->b])` calls each closure with two items, ignores the second, and sorts by nothing — silently. It cost an hour of a run meeting its cyber stack before its physical one. Either sort by one closure returning an array, or write the ordering out; `RunEngine::encounterOrder` does the latter on purpose.

## Commands

```shell
composer run dev          # serve + queue worker + vite + logs
composer ci:check         # everything CI runs: eslint, prettier, tsc, pint, phpstan, tests
php artisan test --compact --filter=SomeTest
php artisan migrate:fresh --seed --seeder=DemoGameSeeder   # demo game; prints a login per character, all "password"
DEMO_CONTROL_DISCORD=your_handle php artisan migrate:fresh --seed --seeder=DemoGameSeeder   # ...and sign in with Discord as Control
php artisan game:tick     # advance any phase whose clock has expired
```

PHP 8.5 is the minimum, and CI runs the same version.

### Running those checks where Composer cannot download

Some sandboxes — Claude Code on the web among them — cannot run `composer install`. It dies part way through with `Failed to download … from dist` or `Could not authenticate against github.com`, and none of the checks can be run.

**It is not an egress block and not an authentication problem, and there is no token to go looking for.** Composer fetches every package from `api.github.com/repos/<vendor>/<pkg>/zipball/<ref>`, and in an Anthropic-hosted cloud session all GitHub traffic goes through a proxy that keeps the real credentials outside the VM and **scopes API requests to the repositories attached to the session**. Every other repository's zipball is therefore a 403 from GitHub, which Composer reports as an authentication failure. The host itself is perfectly reachable, and that is the quickest way to see what is happening:

```
https://api.github.com                                    -> 200
https://api.github.com/repos/PatrickRose/running-hot-app  -> 200   (attached)
https://api.github.com/repos/phpstan/phpstan              -> 403   (public, but not attached)
```

So do **not** go looking for a network setting: putting `api.github.com` or `codeload.github.com` on an environment's **Custom** allowed-domains list fixes nothing, because GitHub traffic bypasses that allowlist and the 403 is a credential scope rather than a firewall rule. `curl -sS "$HTTPS_PROXY/__agentproxy/status"` reports no relay failures at all, which is the tell.

**`git clone` is not scoped**, and cloning any public repository works. That is the whole reason the flags below do:

```shell
composer install --prefer-source --ignore-platform-req=php
```

- **`--prefer-source` is the whole trick.** It makes Composer `git clone` each package instead of asking the API for a zipball, and every package in the lock but one carries a git `source`.
- **`--ignore-platform-req=php`** is needed because the image ships PHP 8.4 where this application wants 8.5, so the install refuses outright without it. It also means the checks run on the wrong PHP; a setup script can install 8.5 from `ppa.launchpadcontent.net`, which is reachable. Pass it to `composer dump-autoload` as well if you ever run that: without it the regenerated `platform_check.php` stops artisan booting at all, which looks like a far stranger problem than it is.
- **`phpstan/phpstan` is the one exception, and it is what aborts the install.** Its lock entry has no `source` at all. Drop it and `larastan/larastan` from `composer.lock` and `composer.json` to get the rest installed — then **restore both files**, because neither edit is yours to commit.

**phpstan is then one clone away, because its repository commits the built phar.** Fetch the exact commit `composer.lock` names for it, and larastan at its locked tag, into `vendor/phpstan/phpstan/` and `vendor/larastan/larastan/`; then add both to `vendor/composer/installed.json` and run `composer dump-autoload --ignore-platform-req=php`, or larastan's own namespace will not autoload. `php vendor/phpstan/phpstan/phpstan.phar analyse` from there is the same analysis CI runs.

**A phpstan that exits 1 having printed nothing is a broken install, not a clean run.** Both ways of getting that wrong are silent: a phar too old for the larastan the lock pins (larastan v3 wants phpstan ^2.2), and `cp -r` into a directory that already exists, which nests the package one level below the path `phpstan.neon` includes. Bisect with a throwaway config including only `vendor/nesbot/carbon/extension.neon`, then only larastan's — the one that goes quiet is the one that is not where it says it is. What must not happen is concluding that phpstan cannot run here and pushing anyway: it can, and it finds real mistakes that the tests and `tsc` do not.

**All of this belongs in the environment's setup script rather than in each session.** It runs once and Anthropic snapshots the filesystem afterwards, so later sessions start with `vendor/` and `node_modules/` already on disk. Budget the whole script at about five minutes or the snapshot does not build and it re-runs every session: `composer install --prefer-source` is essentially all of it, at around six minutes cold on a warm registry, against fifteen seconds for `npm ci`, `wayfinder:generate --with-form` and `npm run build` put together.

## Discord integration

Three independent mechanisms, and it is worth keeping them straight:

- **OAuth**, for identity. Players sign in with Discord; `identify` and `email` scopes only. Login matches the immutable snowflake, never the handle, because handles can be changed. A character's `discord_username` is only a claim ticket, resolved once to a `user_id`.
- **An incoming webhook**, for announcements. Posts as itself, needs no bot token, and is fail-soft so an outage cannot stall the clock. Set per game — there is deliberately no global default to post to by mistake, and a game without one announces nowhere rather than somewhere wrong. Provisioning creates it, so it is not asked for when a game is created: requiring it there asked Control for the thing the application was about to make, and the only way through was to invent a URL. If you find yourself wanting a placeholder webhook, that is the bug.
- **A bot**, for provisioning. `DISCORD_BOT_TOKEN` lets the application build a game's server out: the roles, the channels, the per-team permissions, its own announcement webhook and a join invite. Needs Manage Roles, Manage Channels, Manage Webhooks and Create Instant Invite in the guild. Keep it as a third integration rather than an extension of the two above; leave the token unset and provisioning and role assignment report themselves unconfigured while sign in and announcements carry on working.

**The application never creates the guild.** Discord's Create Guild endpoint only works for bots in fewer than ten guilds and hands back a server nobody is a member of, so Control makes the server by hand and the application is given it. Snowflakes are stored as strings — a bare one is a numeric string that PHP will silently coerce to an integer array key, which is why `ProvisionDiscordGuild` indexes them behind an `id:` prefix.

**Nobody should have to copy a snowflake.** Three ways to attach a server, in order of least work: the bot-add OAuth flow (`connect` → Discord's own server picker → `callback`), which is the good path because `response_type=code` plus a registered `redirect_uri` makes Discord hand `guild_id` back, so the invite and the ID happen in one click; a list of the servers the bot is already in, from `GET /users/@me/guilds`, served as an `Inertia::optional()` prop so opening the Control panel never calls Discord; and a plain text field for correcting one by hand. The callback also checks the granted permission bits, because Discord lets the person untick boxes on the way through and a missing Manage Roles would otherwise only surface halfway through a provision run. `DISCORD_BOT_REDIRECT_URI` must be registered in the Developer Portal next to the login one.

**The bot holds the Control role, and takes it before making any channel.** Discord drops every permission in a channel its caller cannot view, and `@everyone` is the bot's only source of View Channel — so a category locked to one team locks the bot out of it too, and it then cannot create the channels that belong inside it. Every private channel in the blueprint already grants Control, so holding that role is all the access the bot needs. The role carries `permissions: 0` and only ever opens channels, so this grants the bot nothing at guild level. Do not reorder `giveBotTheControlRole` after `reconcileChannels`: provisioning dies on the first private category.

**Every Facility gets a private text and voice channel**, which is where its Runs will happen. They sit in the Corporation's own category, beside the two channels its players talk in, so everything a Corporation owns is in one place. Locked to Control and the owning Corporation; the Runners attacking a Facility are added when a Run starts, because they choose their target in Secret (3.4.1) and access any earlier would leak who is hitting what. `App\Actions\GrantRunChannelAccess` is that half, through `App\Jobs\SyncRunChannelAccess`.

**A Runner's key to a Facility is a per-member overwrite, not a role.** It is something that happens to those four people for the next ten minutes rather than a standing fact about the guild, and a "currently running" role would have to be created, granted, revoked and cleaned up after a crash. They may read, write, connect and speak, because a Run is a conversation under time pressure and the voice channel is the point of having one. A Runner who walks away at the Breather keeps their access until the run ends: they already know the target, so nothing leaks, and the rulebook is neutral about leaving. A character nobody has claimed simply has no snowflake to grant anything to, which is normal.

**Which is why a reconcile now carries member overwrites through.** `ProvisionDiscordGuild` re-sends every channel's whole permission table on every run, and the blueprint knows nothing about the Runners currently inside a Facility — so re-sending only the roles would lock a group out halfway down a stack. That is a reset rather than a reconcile, and `ChannelPayload::for()` takes a `$keep` list for exactly it.

**A missing pair is visible and fixable.** Because the job is fail-soft, a Discord outage during a requisition leaves a Facility with no channels and nothing retrying, so the Control panel badges each Facility with whether its channels are on record and offers to build just that pair. A full provision run recovers them too — they are in the blueprint — but at the cost of re-PATCHing every channel and role in the guild, which is a heavy hammer for one missing channel mid-game.

**A Facility built mid-game gets its channels from a job, not a provision run.** Re-provisioning the whole guild for one requisition would re-PATCH every channel and role in it, which Discord rate-limits hard, so `ProvisionFacilityChannels` creates just the pair and `ProvisionDiscordGuild` still reconciles them on its next run. The job is fail-soft for a sharper reason than most: the `sync` driver runs it inline, so a throw would come back out of `Facility::create()` and take the requisition with it. A Facility with no channel is a nuisance; a CEO who cannot sign off a build because Discord is down would stop the game.

**Provisioning reconciles; it never resets.** Every object the application owns is recorded in `discord_resources` against a stable key (`role:control`, `channel:gang:7:text`), so a re-run renames what drifted, rebuilds what someone deleted by hand, and adds whatever the roster has grown. It is safe mid-game, and it never deletes: a gang leaving the game does not take its channel history with it. The old Discord bot's `reset` command deleted and rebuilt on every run — do not go back to that. Anything in the guild the blueprint does not ask for belongs to Control and is left alone.

**A key with no record is looked for by name before it is created.** The recorded snowflake is the right thing to match on, right up until there is no record: a rebuilt database, a game set up again for a new session, or a server Control built by hand against the blueprint. Matching on the key alone then meant a second Control role and a second category per team on every single run, which is what a test server looks like after a few months. Adoption is the reconcile the recorded key would have done, on the evidence available — the object is claimed, recorded, and brought in line with the blueprint in the same pass. A role matches on name; a channel has to match name, type *and* parent category, because a Corporation's category and its voice channel share a name and nothing else would tell them apart. Two keys can never take the same object: `claimedSnowflakes` is what stops it. `@everyone` and managed roles are never candidates — Discord will not let anybody rename or grant them.

**`ResetDiscordGuild` is the one destructive path, and it is nothing to do with provisioning.** It empties a guild so it can be built again from nothing: every channel, and every role bar `@everyone` and the managed ones Discord refuses to delete. That is far more than the application made — Control's own channels go too — which is why it is behind Control typing the game's name out, and why nothing else calls it. Children are deleted before their categories, because Discord orphans a category's children at the top level rather than taking them with it. A 403 on one object is counted and reported rather than abandoning the wipe: every guild has at least one role above the bot's. The guild itself survives; a bot cannot delete a server it did not create, and the invite links everyone has already used are worth more than the mess. Afterwards the game has no recorded snowflakes, no webhook URL and no invite, because all three now point at things that no longer exist.

The roster has to exist first, since team channels are permissioned from it. A blueprint for a game with no corporations and no gangs is just Control plus the common channels, which is correct rather than an error.

**Control is a roster, not just a flag.** `users.is_control` is granted from the console and means Control of every game there will ever be — right for whoever owns the deployment, useless for the four friends helping run tonight's game. So a game has a **Control team**: a `control_members` row per organiser, named by Discord handle in the game's Control panel and claimed at sign in exactly as a character is. `User::isControl()` is "Control of something" and gates the Control area; `isControlFor($game)` is "Control of this game" and gates every route carrying a `{game}`, so a seat on Saturday's game is not a seat on someone else's. A seat carries the Control Discord role in that game's guild and nowhere else, and whoever creates a game is seated on it, since otherwise they would be bounced off the panel of the game they had just made. Removing a seat takes the role with it. There is deliberately no way to grant the account-wide flag from the web: the thing Control actually needs to say is who is running *this* game.

**Every character in the demo game has a password login**, named after the character — `augmented-nucleotech-corp-security@example.com`, `jack-scanton@example.com` — all sharing the password `password`, and printed as a table when the seeder runs. A real game claims a seat by Discord handle, which would want a Discord account per player before a developer could see the game from a CEO's chair and then from a Runner's. Seeding a second time numbers a clash (`jack-scanton-2@example.com`) rather than falling over, so the second game gets its own accounts.

**The demo seeder can seat you.** `db:seed` takes no options of its own, so the handles come from `DEMO_CONTROL_DISCORD` (via `running_hot.demo_control_discord`), and `DemoGameSeeder::run()` takes the same string as an argument, which is how one seeder passes it to another: `$this->call(DemoGameSeeder::class, false, ['controlDiscord' => ...])`. Without it the demo game's only Control is the password login the seeder prints, so signing in with Discord locally lands on a player's dashboard with no way through — which is the whole reason it is there.

**Roles are handed out on every sign in**, not just the first — same reasoning as character claiming. Assigning a role needs the player to already be a guild member: Discord answers 404 otherwise, and the only way round it is the `guilds.join` scope, which would widen login beyond the `identify`/`email` it deliberately asks for. So a non-member is recorded as `not_a_member` in `discord_member_syncs`, the dashboard shows them the invite, and the next sign in tries again. A sync only ever adds or removes this game's own recorded roles, so a role Control granted by hand survives it.

**Never let a test reach Discord.** `TestCase` calls `Http::preventStrayRequests()` and `phpunit.xml` blanks `DISCORD_WEBHOOK_URL`, because the sync queue driver runs the announcement job inline: without both, a real webhook in `.env` gets posted to for real. `SendDiscordAnnouncement` deliberately rethrows `StrayRequestException` so this fails loudly rather than being swallowed by its fail-soft catch.

## The Council

The CEOs' sub-game (rulebook 3.1), and the one place in this application with a
real secret in it. Two halves on the same turn: the agenda is established during
Setup, and voted on during the Action phase.

**Players drive it; Control provides the agendas.** The rulebook gives Control
four jobs and no more — pick the cards the Chair is handed, add remarks to a
custom agenda, sign off an amendment, and judge an empty seat — so those are
what `/control/games/{game}/council` holds. Everything else is the Chair's or a CEO's
and happens at `/council`. Control still reaches all of it, through
`CouncilSessionPolicy::before()`, because a ruling mid-game must not wait on the
Chair being at their laptop.

**The agenda deck is real data, and it seeds like the three card lists.**
`App\Support\AgendaCardBlueprint` holds the game's own twenty-one cards and
`App\Actions\SeedAgendaCards` gives them to every new game from `Game::booted`.
An agenda card carries no printed code, so it is matched on its title — which
works only because the titles are distinct, and `AgendaCardBlueprintTest` is
what keeps that true. The match also ignores cards a player submitted: somebody
is perfectly likely to write a custom agenda called "Privacy" (3.1.3), and
theirs must not stand in for Control's.

Seeding goes through `CouncilService::createDeckCard` rather than writing rows,
so a seeded card cannot break the two-to-five bound 3.1.4 puts on resolutions.
Re-running adds only what is missing entirely and never overwrites, so a card
the Chair has amended survives it.

Two entries read like transcription slips and are not. Retirement offers a
pension package and a `"pension package"` — the quotation marks are the whole
difference and they are the joke — and Grants names the four Research Point
suits, which makes it the only card in the deck naming a mechanic rather than a
policy. Nothing reads either: what a resolution *does* is Control's to apply.

**Control picks the cards; nothing is drawn.** The rulebook says Control draws
three from the deck, but at the table Control is holding the deck and reading
it — which three the Council is asked about is a judgement about the game in
front of it, not a shuffle. So `CouncilService::handToChair()` takes the ids
Control ticked. Three is what the panel offers and what the Chair keeps two of;
the count is not enforced, because a Control that wants to put four up should
not be argued with.

Handing over **sets** the hand rather than adding to it, so a card picked by
mistake is taken back by handing the corrected set over again and anything
dropped goes back to the deck. That stops the moment the Chair keeps two:
`chairHasChosen()` reads it off the cards rather than a flag, because a handed
card that is no longer in the hand was either kept or discarded and either way
the discard of 3.1.1 has happened.

**Not every seat at the Council is a Corporation.** Rulebook 3.1 seats only the
CEOs; HM Government sitting there with a bloc of six is Control's ruling, and
the application holds it as `characters.council_votes` — a count on the
character rather than a flag naming the Government, so a Press player or the
Runner Representative of 3.1.3's own agenda card can be seated without new code.
Null means no seat, which is almost everybody; a CEO's votes are their
Corporation's Political Will and are not recorded there.

It is deliberately **not** a tracker. Political Will is a Corporation's
political capital and moves through `TrackerService` with a ledger behind it;
this is the size of a bloc, which Control sets and nothing in the game spends —
so the absence penalty of 3.1.2, which costs Political Will, does not reach the
Government seat either. The register on the Control panel stays Corporations
only for that reason, and "Seats at the Council" is a separate card beside it:
the register is attendance, and the seats are who is entitled to be there at
all.

**A CEO is refused a second seat rather than quietly given a dead one.** They
already vote with their Corporation's Political Will, and `CouncilPresenter`
looks for a character seat only when there is no CEO seat — so a number stored
against a CEO would sit there doing nothing. `CouncilService::seat()` therefore
throws, and `seatable()` keeps them off the Control panel's list to begin
with.

So `council_ballots.voter` is a morph rather than a `corporation_id`: a CEO
votes for their Corporation, the Government votes for itself, and neither gets
a nullable column it never uses. `CouncilService::votesFor()` is the one place
that knows which weight a seat carries, and the pages say "votes" rather than
"Political Will" because both kinds are at the same ballot form. The seat votes
and does nothing else — it never chairs, because the Chair rotates between the
Corporations.

**Political Will weights a vote; it is never spent on one.** The rulebook has a
CEO write down the Political Will they *have* (3.1.2) and nothing in 3.1 takes
it, so `castBallot` moves no tracker and a Corporation votes with everything it
holds on every card. The one movement 3.1 does describe is what an absence
costs, and even that has no figure printed: Control marks the seat and names the
number, and it goes through `TrackerService` like everything else. `Tracker`
adjustments are the only reason `CouncilService` knows about Political Will at
all.

**A ballot splits across resolutions.** A card carries two to five resolutions
(3.1.4), so "which way you are voting" is a division of Political Will between
them rather than a for-or-against. "Simple majority" is read as the most
Political Will, because with five options there may be no absolute majority to
be had — and the rulebook's own remedy for an undecided vote is the Chair rather
than a second round. A tie is handed straight back to the Chair, who may only
pick between the resolutions that actually tied: breaking a tie is choosing
between the votes, not overruling them.

**Who you are and what you may do are different questions, and only the second
goes through the Gate.** `CouncilSessionPolicy::before()` hands Control every
ability at the Council, so asking it "is this user the Chair" answers yes for
Control — and the Chamber then told Control it was Augmented Nucleotech. So
`CouncilPresenter` asks the policy's `chair()` and `vote()` methods directly,
under the override rather than through it, for `is_chair` and `can_vote`, and
asks the Gate for `can_chair`. Control may do everything the Chair can and is
still not the Chair; the page says so, and the Chair's controls say whose hands
they are in. Any new flag that names a seat rather than a permission wants the
same treatment.

**Secret means hidden from the players, not hidden.** 3.1.2 has the Chair
receiving the individual breakdowns either way and leaking them as they see fit,
so `CouncilPresenter` builds the Chair's view separately rather than hiding less
of the same payload. Three tiers, and the line between them is the point: who
has handed a slip over is public even in a secret vote (you can watch somebody
vote at the table), the breakdown of a public vote is read out once the Chair
resolves it, and the breakdown of a secret one reaches the Chair and Control
alone. Your own vote is never hidden from you.

**Declaring secrecy hands back the votes already in**, which is both halves of
what 3.1.2 asks for: it must be done before any votes are submitted, and any
that are in go back to the player. Going the other way is refused once anybody
has voted — that is not in the rulebook and is here anyway, because a ballot
cast under a promise of secrecy must not be exposed by the Chair changing their
mind. The Chair leaking it is theirs to do; the application publishing it is
not.

**An amendment changes nothing until Council Control signs it off.** So a
proposed addition is not yet an option and a proposed removal is still one — the
card reads and votes as it stands, and a CEO can never be voting on words that
moved underneath them. The 2-to-5 bounds are checked again at sign-off rather
than trusted from the proposal: two amendments can be waiting at once, and it is
signing both off that would take a card past them.

**Where a card is lives on the card; what is true of it this turn lives on the
item.** `AgendaCardStatus` is the whole lifecycle, deck and custom alike, and
the two kinds meet at `WithChair` — from there the rulebook treats a card
Control handed over and a player's card exactly alike. `InHand` and `WithChair`
read alike and are not: `InHand` is one of Control's cards awaiting the Chair's
keep-or-discard, `WithChair` a player's custom agenda awaiting urgent, important
or rejected. `council_agenda_items` adds only what belongs
to one sitting: how the card got there, whether the vote is secret, and how it
resolved. The five-item cap counts cards on the table, so a discarded one costs
nothing.

**The Chair rotation is held, never derived.** There is no rule saying whose
turn it is — Council Control announces the order on the day (3.1.1) — so
`corporations.council_chair_order` holds it and `CreateDefaultRoster` writes the
roster's own order down as a starting point rather than leaving something to
guess later. Each sitting then stores its own chair, so handing the Chair to
somebody for one turn does not shuffle every turn after it.

**The recess is a second clock inside the Setup phase**, and it is
server-authoritative for the reason the phase clock is: `council_sessions.recess_at`
is absolute, the browser only counts down between polls, and a pause moves it
with the phase (`TurnEngine::resume` shifts it by however long the pause lasted).
Five minutes is `games.council_recess_seconds`, a column rather than a constant,
because Control runs the game to the clock it wants on the night.

**A card waiting on Control's remarks is Control's alone to see.** Not the
Chair's: it has not been handed to the Chair yet and might never be, because the
author reads the remarks first and may keep it back. It appears in the Council
Chamber for Control as well as on the Control panel, because the player is told
their card is with Control and the Chamber is where both of them go looking —
the pile that was only on the panel was a card that had visibly vanished.

**Custom agendas are a three-step handshake, and all three steps are real.** A
player writes the card, Control adds its remarks and gives it *back*, and only
then does the player submit it to the Chair — the rulebook has the player submit
it once they and Control agree, so agreeing is the player's to do too. Any
player may write one: 3.1.3 hands blank cards to players rather than to CEOs.

**The Chamber polls, for the reason the research table does.** It is a room full
of other people: the Chair puts a card up, somebody declares a vote secret, a
ballot lands, the recess clock runs out — none of it anything the reader's
browser did, and a CEO watching a stale page is a CEO who misses the vote.
`usePoll(5000, { only: ['game', 'council'] })`, the same five seconds as
everywhere else.

**Not modelled:** what a resolution actually *does*. The Council decides things
about Procatorion, and the consequences are Control's to apply with the tracker
controls — an outcome is recorded as the resolution that carried, and nothing
reads it.

## Facility Defence

**A Facility type is a row, not an enum case.** The rulebook's footnote to 3.3.1 says more Facility types may be researched during the game, so Control adds one mid-game. `App\Support\FacilityTypeBlueprint` holds the game's own type sheet — all eleven, with their build costs and both effect columns.

What a type *does* travels on the row as well, so a type Control invents is mechanical rather than decorative without new code:

| Column | Meaning | Who grants it |
|---|---|---|
| `physical_slots_granted` | physical card slots added to every Facility the Corporation owns | Security: 1 |
| `cyber_slots_granted` | cyber card slots, same reading | Security: 2 |
| `technology_capacity_granted` | technology storage, same reading | Corporate: 2 |
| `card_move_discount` | Credits off reordering a stack | Factory: 2, Mini-factory: 1 |

Two traps in there. **Physical and cyber slots are asymmetric** — the type sheet gives a Security Facility 1 physical and 2 cyber, and rulebook 3.3.4's "1 more of each type" is the older number. And **technology capacity scales with the count of Corporate Facilities**, not with the type of the Facility doing the storing, which is where "2 x the number of Corporate Facilities" comes from.

**Effects scale two different ways**, held on the row as `grant_scaling`. Security and Corporate are flat: each Facility adds its effect again. Research, AI School, Factory, Mini-factory and Arms *step* — the type sheet gives them their effect once and then "an additional ... at 2, 3, 5, 8 etc Facilities", so a fourth Factory is worth nothing and a fifth is worth one more. `App\Enums\FacilityGrantScaling` owns the arithmetic. Its thresholds beyond 8 continue the Fibonacci run the sheet's "etc" implies and are a reading rather than something written down.

**Most of a type's effect is text, not code.** Research hand size, the strength bonuses Directing Security gets from AI School and Arms, how many cyber cards a Power Facility activates, what an Equipment Facility produces, and every access effect are stored as words for Control to read. They belong to sub-games this application has not built, and none of those numbers move on their own.

**Slots and storage are derived, never stored.** Both move the moment a Security or Corporate Facility opens. Facilities still building do not count: they are not yours until they open.

**A Facility stores the turn it opens**, not a "building" flag. A requisition raised during turn N's Setup opens during turn N+1's, so the clock moving is all it takes, and Control brings one forward by editing the number.

**Position 1 is the card Runners meet first.** Installing puts the new card there and pushes the rest back, which is what "outermost" means in 3.3.4. The physical and cyber stacks are numbered independently and `FacilityDefenceService` keeps each dense at 1..n; nothing else may write a position.

**Reordering costs 1 Credit per card that has to move**, which is the cards left over once the longest run keeping its relative order stays put. The rulebook's worked example (A,B,C → B,C,A costs 1; → C,B,A costs 2) is encoded in `tests/Unit/ProtectionCardMoveCostTest.php`.

**A security budget is escrowed.** Placing one takes the Credits off the Corporation immediately, because that is what putting Credits on the Facility does at the table and it stops the same Credits being promised twice. `TurnEngine` hands back whatever is unspent when the Action phase ends.

**The `#facility-list` embed is the one thing the application shows everyone at once**, so what it leaves out matters more than what it says. Facility names, their types and whether they are still building — and nothing else. Rulebook 3.4.2 makes the number of Protection Cards in a Facility Secret, and technology contents are secret so that reconnaissance costs something, so a stack size here would hand every Runner a free recon action. `FacilityListEmbed` is pure for exactly this reason: what players see is asserted in a test, including a guard that no card title ever reaches it.

**One embed per Corporation**, coloured with `GuildBlueprint::colourFor()` and carrying its wide lockup as the embed's `image` — or its square badge as a `thumbnail` where no lockup has been drawn — so a Corporation matches the Discord role its players already wear. The cost is Discord's cap of ten embeds per message, against twenty-five fields had it been one embed of fields — a game with more Corporations than ten gets the first ten and a footer saying so. Two Corporations can still collide on a colour, because `colourFor` is a hash of the name across ten colours; that is true of the roles too.

**It is posted once and then rewritten.** A list that changes every time a Facility opens would otherwise leave the channel full of superseded copies, and a player reading the wrong one is worse than a player reading none. The message id is a `discord_resources` row (`message:facility-list`), so the reconcile pattern already covers it. This needs the *bot*, not the webhook: a webhook only posts to the channel it was made in.

**Control publishes it; the application never creates it unprompted.** `PublishFacilityList::refresh()` keeps an existing list current as each Setup phase opens and does nothing at all until Control has published one, so provisioning a server can never surprise it with a post. Refresh is fail-soft for the same reason announcements are — a list a turn out of date must never stall the clock.

**Players read the Facilities at `/facilities`, in two tiers, and the line between them is the point.** Everyone sees the same public list the embed carries — Corporation, Facility name, type, building. A player holding a Corporate seat additionally sees *their own* Corporation's stacks in full, because 3.4.2 makes a stack Secret from everyone else, not from the Corporation that installed it. So a Runner learns nothing there that reconnaissance would otherwise have to buy, and a Security player cannot read a rival's stack. `GamePresenter::facilityBoard()` decides which tier a viewer gets, from the Corporate characters they have claimed rather than from a column.

**A Facility reads as what it holds and then how it is protected.** The
technologies stored in one are on `/facilities` beside its stacks, on exactly
the same tier line: 3.4.2 keeps a Facility's contents Secret from *outside* the
Corporation, so that reconnaissance costs something — not from the people who
put them there. So `GamePresenter::facility()` carries them and the public list
in `facilityBoard()`, which is assembled separately, names none of it;
`FacilityBoardTest` asserts that from both ends, as it already did for card
titles. They were only ever on `/research` before, which is the wrong page for
them: a Runner is coming for the technologies, and the person deciding what to
defend is looking at the Facility board.

**Security arranges their own stacks; everyone else reads.** That page used to be read-only, on the reasoning that Security hands Control a requisition slip at the table. It is not any more: a Security player drags cards between their hand and their own Corporation's Facilities at `/facilities`, and Control is left for the rulings only Control can make. `FacilityPolicy::defend` is the whole of the boundary — the Corporation's *Security* seat, in a running game, and nobody else. The CEO and the Research player still see those stacks (3.4.2 keeps them Secret from outside the Corporation, not from inside it) and still cannot move them, because a board three people can drag at once is a board nobody can trust. Control keeps every power it had, through `before()` and through its own routes, so a ruling mid-game never waits on the Security player being at their laptop.

**The rules did not move with the routes.** `App\Http\Controllers\FacilityDefenceController` is a thin thing: every write goes through `FacilityDefenceService`, so a full stack is still refused, a card the Corporation does not hold is still refused, and every Credit still lands in the `tracker_adjustments` ledger with the Security player's name against it rather than Control's. Do not let a player-facing route grow its own copy of a rule.

**Installing and removing commit at once; arranging does not.** They are not the same kind of act. Installing costs no Credits and spends a copy out of the hand, and removing hands one back — things you either did or did not do. Reordering costs 1 Credit per card that moves, and at the table you lay the cards out and *then* pay once, so dragging within a stack only arranges: nothing is charged until Confirm. A stack with an unconfirmed arrangement refuses installs and removals until it is confirmed or undone, because the order on screen and the order on the server would otherwise disagree about what is in it.

**The cost of an arrangement is quoted by the server, never computed in the browser.** `FacilityDefenceService::quoteReorder` is the one implementation of the rule and `reorder()` quotes itself from it, so the number shown and the number charged cannot drift. The board asks over `GET .../cards/order/quote` as the cards move — a GET because asking what something would cost is a question, which needs no CSRF token and is safe to repeat. A second implementation of a longest-ascending-run in TypeScript is exactly the bug this avoids.

**A card does not move straight from one Facility to another.** Drag it back to the hand and then out again, so the removal cost and the copy returning to hand are both visible. The board says so rather than silently refusing the drop.

**The hand is pinned to the top of the page, and the stacks read downwards.** Both ends of every gesture have to be on screen at once: a Facility you have scrolled to is no use if the cards are three screens up, and neither is a hand you have to scroll back to in order to take a card off. A stack is drawn top to bottom because a stack is a stack — reading down it is reading the order the cards are met in — and both ends are named (`StackEnd`: "Runners arrive" above, "into <Facility>" below), which turns a column of cards into the corridor it represents. That is the one thing "Met 1st" on a card cannot say by itself, and it is worth saying on a screen where the whole point is deciding what Runners hit first.

**Pinning the hand cost three fights with dnd-kit, and all three are load-bearing.** Do not undo any of them without reproducing the bug first.
- **`AppContent` is `overflow-x-clip`, not `overflow-x-hidden`.** `overflow-x: hidden` computes `overflow-y` to `auto`, which makes that element a scroll container — and a scrollport that never scrolls is one nothing inside it can be sticky against. `clip` crops the same way without creating one. This is layout-wide, so any page may now use `position: sticky`.
- **The hand wins the pointer outright**, rather than competing on distance. `closestCenter`, dnd-kit's first suggestion, measures to the centre of each target: the hand is a wide bar whose centre is a long way from its own left-hand end, so a card dropped on the left of it scored as closer to a Facility stack underneath.
- **The hand is measured from its own DOM node while a card is in the air.** dnd-kit measures every target once at drag start and then shifts those rectangles by however far the page has scrolled since. That is right for everything on the page except the pinned hand, which does not move when the page does — so the moment a drag auto-scrolls, its believed position walks off the screen it is still sitting on and cards dropped squarely into it land in whatever is scrolled underneath. `MeasuringStrategy.Always` does not fix this; reading `getBoundingClientRect()` at the moment of the question does.

**Dragging is `@dnd-kit`, and that is a deliberate dependency.** The game is played live and people are on phones: native HTML5 drag never fires on touch, and has no keyboard path at all. dnd-kit covers pointer, touch and keyboard, and `FacilityDefenceBoard` gives it its own announcements because the default ones talk about sortable positions when the same gesture here installs, arranges or removes depending on where the card lands. Every installed card also carries a plain Remove button: the hand can be scrolled off screen, and "drag it somewhere else to delete it" is a poor way to ask for the one gesture that costs Credits.

**Directing Security is not modelled at all, and that is a design decision rather than an omission.** The rulebook (3.3.5) has a Security player place a meeple at one Facility during Setup and makes that the price of Boosting a card, paying a Charge and choosing to leave one switched off. It was built that way and then taken out: a Security player may move where they are directing freely during the Action phase, so the meeple constrained nobody, and the only thing it reliably did was stop somebody Boosting until a second person had ticked a box for them. The `security_directed` column, its service method, Control's toggle and `SecurityDirectionTest` all went with it — a flag nothing reads is worse than no flag, because it looks mechanical on the panel and does nothing.

**The budget is the half that survives, and it is Security's own.** What Credits are on a Facility decides what Security can switch on, Boost and Charge, so it is the decision that was always doing the work. It is placed at `/facilities` by the Security player through `FacilityDefenceController::budget`, not only by Control: escrowed the moment it lands, movable through the Action phase because Security reacts after the attacks do, and refused only where it would drop below what has already been spent. The ledger carries the Security player's name rather than Control's, which is the whole reason the route exists. The run screen posts to that same route to top a budget up mid-run, which is the only way the Corporation's own Credits reach a Run at all.

Anything still printed on a card that mentions directing security — the Internet link technology, the Boost and Charge glossary entries — is left exactly as printed. Those are the game's own words for Control to read, not code.

**A new game opens with Facilities already standing.** `CreateDefaultFacilities` runs after `CreateDefaultRoster`, because Facilities belong to Corporations, and both are governed by the same "start empty" choice on the create form. It writes a starting position rather than a change, so the Facilities are built free and the basic cards installed free — nothing goes through `TrackerService`, because there is no before state. Re-running is a no-op: a second Armoury would silently widen every stack in the game.

**Every Facility is named in `config/running_hot.php`, not labelled from its type.** A Facility's name is what players call it all game, and "Gordon Corporate 2" is a label. The names there are flavour rather than briefing data — places near Sheffield, since that is where Procatorion was bought — so rename them freely; nothing keys off them. An entry with no name falls back to the Corporation's short name and the type, so a Corporation added later still works.

**Starting Facilities are per Corporation and the differences are mechanical**, not decorative. They live beside each Corporation in `config/running_hot.php`, from the briefing documents: DTC's second Security Facility widens every one of its stacks, Gordon's three Corporate Facilities make it the only Corporation storing six technologies per Facility, and Genetic Equity's three Research Facilities are its whole strategy. A Corporation the config says nothing about opens with none rather than a guessed set.

## Runs

The Runners' primary conflict (rulebook 3.4), and the loop the rest of the
Facility game exists to feed. Four steps — **Activate → Challenge → Consequence
→ Breather** — repeated until the stacks run out or the Runners back out.
`App\Services\RunEngine` owns all of it; each of its public methods is one *act*,
and who may perform which act is a policy question that lives elsewhere.

**Failing a challenge does not stop the Runners.** This is the reading that
shapes everything else, and it surprises everyone: 3.4.2 sends the Runners to
the Breather "unless a Protection Card has an 'End the Run' consequence", so
losing a check costs you the consequence and you *still get past the card*. A
Facility is attrition, not a wall. Which is why Retry is one of the worst things
a card can do to you — the same card again, with more Alerts standing — and why
Alerts matter so much, since they make everything still ahead of you harder.

**Two counts of cards passed, and they are not the same question.** The strength
bonus is "for each 2 **Active** Protection Cards already passed"; the
consolation payment of 3.4.4 is for "each 3 Protection Cards you managed to get
past". A card Security could not afford to switch on is one the Runners walked
straight past: it counts towards what they got through and makes nothing that
follows it harder. `runs.cards_passed` and `runs.active_cards_passed` therefore
both exist, and they only ever differ when Security ran out of budget.

**The cursor is derived, never stored.** Which card, which pass, which step all
come off the append-only event log — a pass ends when the Runners move on from a
card or consume a Retry, so counting `card_passed` and `retried` events counts
the passes that have finished. Same instinct as the Council reading the Chair's
choice off the cards rather than a flag: a cursor kept beside the log is a second
source of truth that can disagree with the thing players are shown.

**Every roll is server-side and kept, with its faces.** A browser that rolls its
own dice is a browser that can decide it won. `App\Services\Dice` is injected so
tests can say what the dice did, and `tests/Support/FakeDice.php` throws when it
runs dry rather than falling back to random — a test that quietly started rolling
real dice would fail intermittently for a reason nobody would look for. A roll
that decides a *choice* rather than a check (which Runner inherits the Run
Leader's job) goes in the event payload instead of `run_dice_rolls`, because that
table's threshold and successes would be meaningless for it. And a single
remaining candidate is not rolled for at all: a one-sided die is not a die.

**Security pays from two purses, and which one matters.**
`App\Support\Runs\SecurityPayment` is the split, named by the player rather
than applied in an order the engine picked:
- **Alerts** are a pool for one run and then gone, so there is no ledger to
  write and nothing outside the run can see them. Spending them is free in
  Credits and costs the Runners nothing — but it lowers the Alerts *standing*,
  which makes every card they have left easier. That trade is the decision
  Security is there to make, which is why the screen makes it a slider.
- **Budget** Credits were already taken off the Corporation when the budget was
  placed (`FacilityDefenceService::setSecurityBudget`), so spending only records
  how much of that escrow has gone — the apparent exception to "never write a
  tracker directly" is not one.

**There is deliberately no third purse.** A payment reaching past the budget
into the Corporation's own Credits was built and taken out, for the reason
Directing Security was: it put the same Credits in two places at once, spent on
this card and still promised to whatever else the Facility was funded for. A
Facility is defended out of what has been put on it, so company money reaches a
run by *raising the budget* — one button on the run screen, posting to the
budget route the Facility board already has, which escrows the Credits in the
open and writes the ledger row there. Do not give `SecurityPayment` a third
field back.

Naming no purse at all means the budget, so any caller that never cared about
the split behaves as it did before there was one. A split that does not add up
to the cost is refused rather than topped up from somewhere, and a purse asked
for more than it holds is refused naming which one came up short.

**A slider belongs to a cost, not to the desk.** One shared slider had to say
how many Alerts to spend before knowing what on, so it ran to the Alerts in hand
and read as a setting rather than a decision. Each payable act draws its own
instead — Activate, Boost and Charge — running from 0 to *that* cost and saying
underneath what each purse is covering, because "1 required" is half of what the
control is for. A Boost's slider re-reads itself as the count changes. Each
reads down the column the way the act happens: what sets the cost, how it is
being paid, then the button that commits it.

Everything else — Wounds, Tags, the 3.4.4 payment — goes through
`TrackerService` like anything else.

**A Boost is bought during the Activate step and nowhere else.** 3.4.2 puts it
there — it is the last line of that step, before the Challenge heading — and the
worked example says so outright: "During the Activate step, Ryan uses a 'Boost'
card." It is also the only reading that means anything, because Security names
the printed strength and rolls the defence at the Challenge, so a Boost bought
after that could not reach the roll it was meant to win. `boost()` checks the
step and the screen draws the control only there.

**A Charge is paid at the Consequence step and nowhere else.** It buys an
*extra* consequence on top of one the Runners are already taking, so there is
nothing to add it to until they have lost the roll — 3.4.2 introduces it after
"if they do not, then the Runner(s) take the consequence", and the glossary
makes it "if the runner(s) fail to break a Protection Card with a Charge
effect". The Consequence step *is* that condition and nothing else, because
`stepFor()` only reaches it when a challenge has happened and `runners_won` was
false, so `charge()` checks the step and the screen draws the control only
there. It used to sit beside Boost from the moment a card came on, which offered
Security a purchase that could not mean anything yet.

**Alerts do two jobs, and that is the decision Security is there to make.** They
are temporary Credits *and* a point of strength on every card the Runners have
left, so spending them buys something now and makes the rest of the Facility
easier. Which means the strength curve reads the Alerts *standing*, not the
Alerts generated.

**The Consequence step is a handshake, and the two halves are different
jobs.** Security marks what the card does and the Run Leader takes it. That is
3.4.2's own division: the consequence comes off the card, which Security is
holding and has certainly read, and the one thing the rulebook gives the Leader
is that "the consequence must be taken by a single player, decided by the Run
Leader". The Leader used to type the card's numbers in themselves, which asked
the side that cannot see the card to read it out.

So there is a **slip**, `App\Support\Runs\ConsequenceSlip`, derived off the
pass's own events rather than stored — same reason the cursor is, and it matters
more here because both sides read it at once. Marking an empty slip is a real
answer — "the card does nothing" — so `consequenceIsMarked()` reads the event
rather than the slip, and nothing can be taken before Security has written
something down.

**One act, both halves.** `markConsequence()` takes what the card prints *and*
what Security is paying Alerts to add, because it is one decision made once:
read the card, decide whether to make it worse, hand the lot to the Leader.
Buying each Alert effect as its own request meant committing to it before seeing
what the finished consequence looked like, and handed the Leader a slip that grew
under them. One transaction, so Alerts Security cannot afford take the mark down
with them rather than leaving the card marked and the extras missing.

The two halves still behave differently, because they are not the same kind of
thing. Marking **replaces** what the card does, for the reason handing cards to
the Chair sets the Council's hand: a count typed wrong is corrected by marking
the right one. Alerts are **spent**, so what they bought is added to the pile and
survives every later mark — they are gone, and no amount of re-reading the card
brings them back.

**An Alert-bought consequence lands on the slip rather than happening on its
own.** "Security players may also use any alerts to trigger one of the other
effects as well" reads as one more thing on the pile the Runners are about to
take, so the Leader still names who takes it and still answers for an End the
Run bought that way. It used to apply immediately, which took the choice off the
Leader and moved the cursor to the Breather underneath them.

**The rest of the group reads the slip too.** A Runner who is not the Leader is
as likely as anybody to be the one taking two Wounds, and used to be told only
"Watching" while that was decided. At the table the card is face up and they can
read it, so `Watching` in `run-panel.tsx` draws what Security marked and who is
deciding. Read-only: the Leader decides.

**An End the Run is a question, not a consequence, so it is answered last.**
Everything else on the slip lands first — the Runners take what the card does to
them either way — and then the run either stops or does not.
`applyMarkedConsequence()` is the one act that does all of it, so a card
printing "1 tag, End the run" can no longer pay half of itself.

**Ignoring one is priced on the count, not as a flag.** "For each
'End the Run' that you have ignored (including this one), you take 1 Wound, 1
Tag and 1 Alert" — so the first costs 1 of each and the second 2 of each, and it
converts into a Retry. The screen says that number before the button is pressed,
quoted by the server as `ignore_cost`. There is deliberately no separate
`ignore-end` route any more: it was a second way to reach `ignoreEndTheRun()`
that skipped Security's marking entirely.

**Where the rulebook says "may", nothing moves.** Walking away at the Breather
"may have an effect on your gang's Notoriety", so no Notoriety moves and the
event says it is Control's call. Being incapacitated hands your permanent
Equipment to the Security player, and since who owns which Equipment card is not
modelled, the event says that too rather than the application guessing.

**Ordering is a starting position, not the last word.** The seven tiebreakers of
3.4.1 are run once and the deciding rule is written down, because the seventh is
a d8 and the order cannot be recomputed afterwards. Groups may cede their place
or be "otherwise monetarily convinced" and footnote 10 sends anything unusual to
Control, so `order_index` stays editable.

**Players drive it and Control steps in, on the same routes.** `RunPolicy` is
the whole boundary: `lead` for the Run Leader (roll, decide who takes a
consequence, move the group on), `act` for any Runner still in (walking away is
each Runner's own decision, not the Leader's), `defend` for the Corporation's
own Security seat, and `submit` for anybody holding a Runner *or Freelancer* —
3.4 hands the Facility game to a side rather than to one role. `order` is the
one ability no player has, because a group that could order the queue could put
itself at the front of it. `before()` gives Control every one of them, so a run
never stalls on somebody being away from their laptop.

**A run keeps two secrets, and `RunPresenter` is where they are kept.** Same
shape of problem as `CouncilPresenter`, and the same answer: the two sides get
views built separately rather than one payload with things taken out of it.
- **The stack depth is Secret** (footnote 11), so `cards_remaining` is null for
  the Runners. They find out by running out, which is what makes the Breather a
  real decision — leaving costs you what you have already paid for, and you
  cannot know whether you were one card from the end.
- **A card is face down until it is Active.** Security reads their own stack
  (3.4.2 keeps it Secret from everyone else, not from them) and so sees the card
  they are deciding whether to pay for; the Runners get the kind and nothing
  else until it is flipped. A card Security leaves off is one they get past
  without ever learning the name of — which the *log* has to respect too, so
  those lines read as the card being left off without naming it.
- **Security cannot see a run that has not gone in yet**, because budgets are
  set in Secret at the same moment targets are chosen (3.3.5, 3.4.1).

**The challenge form asks for the skill and the printed strength.** It does not
parse them, for the reason there is no parsed strength column: `Brute/Hack (2)`
is the Runners' choice and `Hack (4+N) - where N is the number of cards
underneath this` is not knowable from a column. The sentence is shown beside the
form and the table converts it, which is what it does with the card in hand
anyway.

### Getting inside (3.4.3)

**Every Runner who walked in gets one access, and spends their own.** The
rulebook has the Run Leader choosing the cards; this application gives the
choice to each Runner, which is what makes a group of four worth more than a
group of one. `RunPolicy::act` is the boundary and the controller adds the
second half of it: `act` only asks whether you are on the run, and every Runner
on it passes that, so without a check against the character named a Runner could
spend a gangmate's access out from under them. Control spends anybody's, through
`before()` — Control is not in the way of an access, only available for the one
that needs them.

**Spending is the whole of what you get.** A failed copy or a steal that missed
still costs the access: 3.4.3 puts the card back on the list and says "you may
attempt to access it again", which only means anything if the first attempt was
spent. Equipment may buy a Runner more accesses (footnote 13) and nobody's
Equipment is modelled, so that arrives with the Equipment holdings.

**The Credits card is read off the building, not set anywhere.** Two printed
sums added together: the Protection Cards *installed* in the Facility — not
activated, because a card Security could not afford to switch on is still a card
in the building — and the technologies stored there, counted "including the
Credits card". The first is a printed list with steps of 1, 2, 2, 3, 3 and then
+3 a card past ten, so it is written out; the second is the triangular numbers.
`App\Support\Runs\RunRewards` holds both. There is one Credits card and one
Facility effect in a building, so the second Runner to reach for either finds it
gone — `RunAccessKind::onlyOncePerRun()` is where that lives.

**An unseen card is drawn; a card that has been face up is chosen.** At the
table the draw is a person holding cards face down and fanning them out, so the
blind half is the same thing without somebody to hold them — and a Facility down
to its last unseen card hands it over rather than rolling a one-sided die. But
the secrecy has nothing left to protect once a card has been turned over, and
3.4.3 says outright that making two copies means accessing the card twice. So a
Runner may go back for anything this run has already revealed, by name.

That is the line the payload draws: `known_technologies` names the revealed
cards, `technologies_left` counts everything still in the racks, and the unseen
ones are only ever the difference between the two. Naming an unseen card is
refused — that is the choice the draw exists to take away, and it is what stops
a Runner shopping the Facility without spending anything on finding out what is
in it. When the blind draw runs out the refusal says to name one rather than
claiming the racks are empty, because they are not.

**Having been at a card does not take it out of the racks**, and getting this
wrong is easy — it was wrong here first. 3.4.3 says so twice: a failed check
"goes back to the list of cards you may access", and after a copy "no matter the
outcome, the card is returned to the list", because copying it twice is how you
make two copies. So the draw excludes nothing on the strength of the access log.
What takes a technology out of the draw is it *leaving the building*, which
`storedTechnologies()` already reads off the holding's own status — stolen, or
destroyed outright. The one exception is a card that is face up and still being
decided about: it is in somebody's hands, so a second Runner cannot draw it out
from under them, and it goes back in the moment it is resolved.

**Drawing it and deciding on it are two acts, and the order is the point.**
`accessTechnology()` turns a card over and stops; `resolveAccess()` is Copy,
Steal, Destroy — or nothing. Choosing before the draw would be picking how to
open a safe before knowing what is in it, and it is not what 3.4.3 describes:
the card is revealed and *then* the choice is made. Two routes for the same
reason, and the log reads as two lines.

**"Leave it" is a real answer rather than a way out.** The access is spent on
the draw, not on the decision, so a Runner who does not fancy their dice against
this particular card has still bought something: they know what the Facility is
holding. `run_accesses.outcome` records `left` for it, which is why the column is
null only while a card is face up and waiting.

**The three things you can do to a card share a shape and nothing else.** All
roll the group's *combined* Brawn and Hack — both, added, which is the whole
difference from a Protection Card — and all read their successes off a printed
band with no opposing roll and no consequence for failing.
`App\Support\Runs\AccessCheck` owns the bands. A copy leaves the card where it
is and produces a discount for whoever buys the copy, so nothing is written on
the holding: what the Runner carries out becomes somebody's
`technology_holdings` row when they sell it, which is the same conversation
3.2.5 already has. A theft takes the card at 8 successes — flat for every
technology, because the card sheet has copy and destroy strengths and no Steal
column at all. A destroy only removes the technology at the last of its four
bands; everything below leaves traces the Corporation can research again at a
discount the rulebook never prints, so the band is recorded and the percentage
is Control's.

**Destroyed and Stolen are different losses and are kept apart.** A destroyed
technology leaves traces; a stolen one is intact in somebody else's hands.
Neither row is deleted, and neither occupies the Facility's storage any more.

**What a Facility's own effect *does* is still words — but the players read
them.** Spying on a rival's stack, a blackmail file, a stock certificate: all
conversations, so taking the effect records that it was taken and the
conversation happens. The text itself is on the run screen, on its own line
above the buttons rather than in the small print, because it is one of the four
things an access can be spent on and choosing between them means being able to
read it.

**A plot access asks for no reason.** A Runner tells Control what they are
chasing in the channel they are already standing in, and a text box that has to
be filled in before the button works is a worse version of a conversation. The
`notes` column stays for Control's own use.

That and the Facility effect are the two places Control is still wanted, and
only to hand over what the Runner has already won.

The Runners are also let into their target Facility's Discord channels for the
length of the run, which is the Discord half above.

## The card lists

Three card families, all real data from the game's own card sheet, all seeded per game and all editable by Control. They live in `App\Support` beside the Facility type sheet rather than in `config/running_hot.php`, because three hundred cards do not read comfortably in a config file: `ProtectionCardBlueprint` (83), `EquipmentCardBlueprint` (74) and `TechnologyBlueprint` (144). `Game::booted` seeds all three when a game is created — none of them depends on the roster, so they arrive before it does.

**The code printed on a card is what identifies it, not its title.** Doppleganger is two cards, `PX011` in the physical stack and `PX012` in the cyber one, so `protection_card_types` is unique on `(game_id, code)` and titles may repeat. The one-copy-per-Facility rule of 3.3.4 keys on the card type rather than the title, so a Facility can hold both Dopplegangers and still only one of each.

**ANT's five cards are distinct cards, not its own names for the other five.** Öryggissveit, Takkaborðið, Öryggisluggari, Vélfærafræði sporðdreka and Engill (`PS015`–`PS020`) are ANT's own, and the four other Corporations hold Security team, Keypad, Security shutter, Roboscorpion and Angel instead. Everyone holds Orc.

**Their printed stats are identical pair for pair, which is exactly why it is worth writing down: they are still separate rows, and merging them would be wrong.** Öryggissveit and Vélfærafræði sporðdreka really do carry the Charges Security team and Roboscorpion carry, the card sheet included. The committed artwork for `PS015` and `PS018` shows no CHARGE panel and is *stale*: it was read once as evidence that neither card has a Charge, and the Charges were taken off the blueprint on the strength of it. They were put back. Where a card's artwork and the card sheet disagree, the sheet is the newer of the two - and the artwork is deleted rather than left to be read again.

**No card's printed strength counts the Alerts standing, and that is arithmetic rather than transcription.** `App\Support\Runs\ChallengeStrength` adds `AlertSchedule::strengthBonus()` to *every* card, so a card whose own strength reads "Number of alerts+2" has the Alerts counted twice — once in the number Security types into the challenge form, and again in the curve underneath it. Security shutter (`PS006`) and Öryggisluggari (`PS017`) were transcribed that way, and both are now a flat `Brute/Hack (2)` — the card sheet had flattened them to 4 and the designer has since rebalanced them to 2, which is the floor the old formula had anyway at nought Alerts standing. Öryggisluggari moves with Security shutter because ANT's five are identical pair for pair, above. `ProtectionCardBlueprintTest::test_no_printed_strength_counts_the_alerts_standing` sweeps the whole list rather than the two codes, because the next card to do it would be as wrong and as quiet. A formula counting something the Run does *not* already track — wounds, runners, cards underneath, times encountered — is fine and several cards carry one.

**A challenge is the sentence the card prints, not a skill and a number.** Most cards are a plain `Brute (6)`, but `Brute/Hack (2)` lets the Runners choose, `Hack (4+N) - where N is the number of cards underneath this` is not known until the card is met, and `Hack (4), followed by Brute (4)` is two challenges on one card. There is no parsed skill or strength column: the words are stored and shown, and the table converts them, which is what it does with the card in hand anyway. Runs will need to read the text.

**Availability is read off the code prefix, and that is a reading rather than a rule.** The card sheet has no availability column. `PS` and `PE` cards are the ones the card sheet prices and the briefings hand out; every `PR` and `PX` card is the target of some technology's `Unlock:` effect, so they are seeded research-only. Three (`PR001`, `PR011`, `PR013`) are unlocked by no technology in the list at all, so Control has to add the research or open them up by hand. `ProtectionCardBlueprint` says all of this in one place instead of deriving it at runtime.

**No card carries a shop price, deliberately.** The card sheet has a cost column, but the Corporation shop and the Runners' market do not work the way it suggests, so seeding those numbers would encode a pricing model the game does not use — and a wrong price is worse than none, because the shop would be built on it. `cost` stays a nullable column that Control can fill in for a one-off ruling, and **pricing arrives with the shop**, which is its own piece of work. A Charge is unaffected: its cost is Credits Security spends during a Run (3.3.5), printed on the card and nothing to do with buying one, so it is seeded. Research costs are unaffected too — those are Research Points, not Credits.

**Cards are found by code, and a card with no artwork is normal.** `App\Support\CardImage` resolves `public/images/cards/<CODE>.{webp,png,jpg}` and answers null when there is nothing there. Control invents cards mid-game — the rulebook has research proposals priced and added to the tree during play, and DTC's "Unfortunate Malfunction" hands out a bypass card named after whichever Protection Card it counters — and those have never been printed. The `CardFace` React component shows artwork where there is any and a card-shaped box of the card's own text where there is not, so the text box is the other normal case rather than a fallback.

**The card sheet is the source of a card's text, and artwork that disagrees with it is deleted.** The sheet is maintained; the faces were rendered once, and six of them print something the sheet no longer says — `PR010` Anzû a three-part consequence where the sheet has one line, `PE004` Giant and `PR002` Nachtkrapp a longer Charge, `PS015` and `PS018` no CHARGE panel at all where the sheet gives both a Charge, and `PS006` Security shutter an Alert-scaled strength that is now a flat 2. Deleting is safe precisely because a card with no artwork is a normal card: `CardFace` draws its text instead, and the text is the thing that was right. Do not re-derive a card's rules from its picture — that reading is what put the application and the card list out of step, and every remaining face was checked against the sheet rather than trusted.

**A research card has two faces, filed `<CODE>_F` and `<CODE>_B`.** A technology is printed proposal side up and flipped over once researched (3.2.2), and both faces are public — a technology is not secret, only which Facility is storing it is. The two need no columns: they are the fields the application already has, split the way the card splits them, with the front the name, description, suit costs, prerequisites and required Facility type and the back the effect with its copy and destroy strengths. A single-sided card resolves from `<CODE>_F` or from the bare `<CODE>`; the back has no such fallback, because an unsuffixed file is the front of a one-sided card and treating it as a back would have every card offering a flip that showed the same picture twice. Either face may be the one that is missing — sixteen technologies have a back and no front — so anything showing a card takes whichever it has.

**The three families are not the same shape, and a card is the size of a card.** Equipment is printed portrait at 600×817 and both the Protection and research cards landscape at 600×440, so no single aspect ratio may be imposed across all three — forcing one crops two thirds of the game. What `CardFace` takes instead is the family's shape, from the call site, and it holds *both* faces to it: a card shown as its own text is the same size as the artwork beside it, rather than as tall as its words, so a list lines up whether or not the artwork has been drawn. That means a wordy text card can outrun its box, which is why the words fade at the bottom rather than being cut, why the availability line sits below the fade, and why every card — not just the ones with artwork — carries its full text in a tooltip and in the page for a screen reader. The table thumbnail is a different problem and is held to a height instead, so a landscape and a portrait card still line their rows up. `CardFace`'s footer is part of that text rather than a caption, so it is drawn only on a card being shown as its words — anything that has to be legible on the artwork as well lives outside `CardFace` and on top of it, which is what the `×N` copy count on a card in Security's hand is. Card artwork lives under `public/` because it is referenced from an `img` tag at runtime and the page is served by Laravel — the opposite of the icon font below.

**The artwork directory is read once per request, not once per card.** Three hundred cards against four extensions is the better part of a thousand `stat` calls a page, and worst on a checkout with no artwork at all. `CardImage` lists the directory once and answers from memory, which also makes lower-case file names resolve and lets a `webp` supersede a `png` by extension rather than by directory order. Tests that write artwork must call `CardImage::flush()` — `TestCase` does it for every test — and must never write over a real code: the game's artwork is committed, so a test cleaning up after itself would delete it. One did.

**Owning copies is modelled; buying them is not.** `protection_card_holdings` is a count per Corporation per card, and it is what caps how far a card stretches: one copy per Facility, so four copies of Security Team defend four Facilities and no more. `FacilityDefenceService::install()` is the only place a copy leaves a hand and `remove()` the only place one comes back — a copy in a Facility is a row in `facility_protection_cards`, so the hand plus the installed copies is still the briefing's count. Installing with none left is refused; Control raises the count first.

Control sets any count outright via `ProtectionCardHoldingController`. The Corporation shop, auctions, research grants and Security players trading between themselves all happen at the table, so the application records where a count ended up rather than replaying how it got there. **Buying from the shop is a follow-up.**

**Starting cards are drawn from what a Corporation actually holds.** `installed_in_each` in `config/running_hot.php` says how many of each kind a starting Facility opens with (one each), and `CreateDefaultFacilities` picks the card the Corporation has most copies of that the Facility does not already have. That is what makes ANT's Facilities open with ANT's own cards, and it spreads the load — a Corporation holds four copies of its commonest card against five Facilities, so no single card can cover them all. Running out is not an error: a Facility simply opens thinner.

**Technologies are catalogue only.** A technology carries its effect as printed text, because the research game that would act on it is unbuilt, along with its four suit costs, its prerequisites as printed titles (Control may add a technology others already name), a required Facility type, and its copy and destroy strengths.

**Equipment is not, any more.** `equipment_holdings` is a count per **Character** per card, and per Character is what the rulebook says twice: 3.4.1 caps *you* at three equipped permanent items and one copy of each by title, and 3.4.2 hands *your* permanent Equipment to the Security player when you are carried out. Neither sentence means anything about a shared pile, so a gang's kit is four or five separate hands. The effects themselves are still printed text — `RollModifiers` is what the player holding the card says it grants this roll, rather than seventy-four effects parsed into a little language that a card Control invents mid-game would fall straight out of.

**`EquipmentService` is the one writer of that table**, the way `FacilityDefenceService` is for a Corporation's Protection Cards. It was `RunEngine` alone, because a run spending a consumable and a carried-out Runner losing their permanents were the only two ways a count could move; Control handing a card out is the third and happens nowhere near a run. The run loop asks the service rather than a Control route growing its own copy of the write.

None of it is a Tracker. A Tracker is a number the game moves and argues about afterwards, which is why every one of those leaves a `tracker_adjustments` row; how many Shivs somebody is carrying is a holding, and a holding records where a count ended up rather than replaying how it got there.

**Control hands cards out at `/control/games/{game}/cards`**, under the Equipment list rather than on a page of its own — that is where Control is already looking when a Runner asks for a card. The rest of that page is still a card list to read: the Protection Card catalogue is edited on the Facility Defence page instead, beside installing. A Corporate seat is **refused** Equipment rather than quietly given it, because a CEO with a Katana in hand is a row nothing reads; a Freelancer is not, since 3.4 hands the Facility game to a side rather than to a roster.

**Every Runner opens the game carrying what their briefing prints**, seeded by `SeedEquipmentHoldings` from per-Runner lists in `config/running_hot.php` — per Runner because the briefings are one document per player, so there is deliberately no gang-level list to be mis-keyed against somebody else. Two readings are worth knowing. A briefing's **"Ability" section is a card too**: what is printed under it is the effect text of `EEP014`–`EEP016`, the three Reconnaissance cards, reproduced almost word for word, so Ballet, Bitter and Z3R0 are seeded as the cards they are. And a **Freelancer carrying nothing is the right answer**, not an unfinished one — all three are given "Special rules" in place of a kit, and none of those is an Equipment card.

The seeder **skips a code it cannot find**, which is right when Control has deleted a card and wrong when somebody has fat-fingered a digit — and the two are indistinguishable at run time, so a Runner would simply open the game one card lighter than their briefing. `EquipmentSeedingTest` checks the configuration against the catalogue for exactly that, rather than transcribing the eighteen kits a second time where they would agree with themselves instead of with the briefings.

**Not modelled:** the market. Buying, selling between Runners and splitting a haul are conversations at the table, so Control sets the count.

**Technology trees attach to Corporations late.** A game is created before its roster exists, so `SeedTechnologies` writes every technology unattached and fills in `corporation_id` on a second run, after `CreateDefaultRoster`. `CreateDefaultFacilities` makes that second call. Note the codes do not identify the tree reliably — Gordon and Genetic Equity both take a `G` — so the tree comes from the sheet's own column.

**Not modelled, deliberately:** the Corporation shop, auctions, research grants, trading copies between Security players, and who owns which Equipment card. Each is a conversation with Control, who then sets the count.

## The research game

The Research players' sub-game, and the one place the application does something
a table of cards cannot: it does the arithmetic, and it lets scoring happen off
to one side while play carries on. Rulebook 3.2 — and read the **PDF** for it,
because the four suits are icons that do not survive the text extraction.

**Research Points are four Trackers on the Corporation.** Not one currency and
not the Research player's: 3.2.5 trades them "between different Corporations",
and a Corporation fielding two Research players still has one pile of tokens. As
Trackers they sit on the Control panel beside Credits, every movement lands in
`tracker_adjustments` with a reason, and Control overrides a score by moving the
number — which is the whole of "Control can override any score".

**An equation is two sets, and the arithmetic lives in one pure class.**
`App\Support\Equation` takes the cards and answers whether they are an equation,
what each side pays, and what the balanced bonus is. Nothing about players,
turns, decks or the database. That is deliberate for the reason the Protection
Card move cost is one method: the rules are subtle enough to drift if they are
written twice, and `tests/Unit/EquationTest.php` holds them to the rulebook's own
worked examples — including the scoring table on page 13, which is what proves
scoring picks a *side* rather than a suit.

**Scoring picks a side.** "You earn Research Points in one of the suits that you
used ... equal to the sum of the card values of that set" reads as a free choice
of suit until you check it against the book's four examples: 1+2 Maths against
3+4 Leaf pays *either* 3 Maths *or* 7 Leaf, never 7 Maths. So the player chooses
which set to be paid for, and the suit follows from it. The suit is asked for as
well only because a set of nothing but wilds could be any of the four.

**The balanced bonus is the triangular numbers.** 1, 3, 6, 10 for one to four
cards a side, and the book then says "and so on" — so it is `n(n+1)/2` rather
than a table that runs out, because deck customisation adds cards and nothing
caps how wide an equation can get. It may be split across any suits the equation
used, which is why it is allocated rather than paid in one.

**Playing and scoring are two acts, and that is the rulebook's instruction.**
"Scoring can and should be done while other players are taking their turns." So
`play()` spends the cards, draws the hand and the pool back up and passes the
turn on, and leaves the equation Pending; `score()` pays it whenever its player
gets to it, possibly several turns later. Nothing at the table waits on somebody
doing sums. Control's override is `unscore()`, which hands the points back and
returns the equation to Pending so it can be scored again — both movements in the
ledger, so the tokens on the table stay reconcilable.

**A sitting is dealt as the Action phase opens and shut when it ends.** The
rulebook has research players make their way to the table during the Action
phase, and "the phase end is called" is one of the two ways the game ends
(3.2.1) — so the table exists for exactly that phase and `TurnEngine` owns both
ends of it. Nothing closed it at first, and a sitting then stayed open through
Team Time and the next Setup, still taking equations. Control re-deals, redraws
the order and closes the table from its own panel as well.

**Opening is fail-soft; closing is not, and the asymmetry is the point.**
Dealing seeds decks, gathers every card, seats the Corporations and deals them a
hand — a lot to go wrong, and none of it may stop a phase from starting, so a
throw is reported and the phase opens anyway with a sub-game Control deals by
hand. Closing writes one column, and swallowing a failure there would leave open
exactly the table the call exists to shut. `finish()` closes it too: ending the
game is the one path to a completed phase that does not go through `advance()`.

**Closing the table does not stop scoring, and must not.** An equation is played
in one phase and scored "while other players are taking their turns", possibly
several turns later — so `score()` asks only whether the equation is still
Pending, and the pending list hangs off the Corporation rather than off the
sitting. What closing stops is playing another one.

**Dealing gathers every card back first, and that is a reading rather than a
rule.** The rulebook has a deck that runs dry end that player's game, and says
nothing about getting the cards back — which read literally would put a
Corporation out for the rest of the evening rather than the rest of the phase. So
each sitting begins by gathering and shuffling. It is the only way the game is
playable more than once.

**Both decks are real, and the rulebook describes neither.** "Each Corporation's
research deck begins as a fairly basic deck" is the whole of what 3.2.3 says,
and the public deck is never mentioned at all — so both lists are the designer's,
they live in `config/running_hot.php`, and `SeedResearchDecks` writes them when
the roster is created. `private_deck` is now only the fallback for a Corporation
the config does not name.

**The public deck is 138 cards and almost all of it is marked**, which is what
makes it the *shared* deck: a card off the table usually says something about how
the equation has to be built. Each of the four suits holds the same 32 — one of
every value that cannot be played alone, one of every value demanding each of the
four suits of the other side (its own included, which makes both sets the same
suit), and only seven plain cards: three 1s, two 2s, two 3s. Then ten wilds, two
of every value, every one of them No single.

**A Corporation's deck is two major suits and two minor ones**, and the shape of
each is the same for all five — so `research.suit_decks` holds both once and a
Corporation names only which two it majors in. Thirty-six cards: fourteen in
each major suit (3×1, 2×2, 2×3, 1×4, 1×5, and a No single at every value) and
four in each minor (1 and 2, one plain and one No single each). The pairs come
off the briefings and are what make the five research games different from one
another: ANT Maths and Cog, DTC Maths and Brain, McCullough Cog and Leaf, Gordon
Cog and Brain, Genetic Equity Brain and Leaf.

Every suit a Corporation does not name is minor, which makes *two* majors the
game's own shape rather than something enforced — a Corporation Control invents
may major in one or in three and gets the deck that implies. One named nowhere
gets `private_deck`, which is the fallback rather than anybody's real deck.

**A test that pins a deck has to clear `research.corporations` as well.** The
named entries win over `private_deck`, so a test setting a flat deck and not
clearing them gets thirty-six cards full of No single instead — which fails
nowhere near the setup that caused it, as several did the moment the real decks
landed.

**A deck is described three ways, and they add together.** The shape —
`values`, `copies`, `wild` — is the run of numbers that makes up the bulk of one,
because most of a deck is the same values four times over and writing that out
would bury the parts that are not. `cards` is those parts, an entry at a time: a
card printed with a marking, a wild worth something other than the rest of them,
two 3s against one 5. An entry naming no suit means one in each of the four,
which is what a value in the shape means as well; `wild => true` is the card of
no suit, and `markings` is the list of what it is printed with. `major` is the
third way, above, and the entries in `suit_decks` read exactly as a `cards` entry
does except that they name no suit: the suit is the assignment, stamped on as
they are read.

An entry may also carry `values`, a list, which is the same entry said of several
values at once. That is what the public deck needs and why it exists: five cards
demanding Cog of the other side are one line rather than five, and the twenty
restricted entries a suit holds read as four lines rather than twenty. Without `cards` a
seeded deck could hold no marking at all, so the only "No single" card that could
ever reach a game was one bought off the tech tree. A suit or a marking the
application does not have stops the seed rather than being written as a null, and
so does a Restricted naming no suit: a card that quietly lost half its marking
would go on being played wrongly for the rest of the game, and nothing would say
why.

**A card is a row, not a type.** A research card is a suit and a value and
nothing else, so there is no catalogue to point at — two 3-of-Leaf cards are two
rows. That is what lets deck customisation add one and Control upgrade one
without either touching its twin. A null suit is a wild card rather than a
missing one, and it draws the icon font's `Y`, which was drawn for this and had
nothing to show it until now.

**A card carries two kinds of marking, and they are about opposite halves of the
equation.** "No single" is about the set holding the card: it cannot be the only
card there. "Restricted" is about the set facing it: the card names a suit and
the other side has to be that suit, printed as "Other side must be Cog". The
rulebook prints both and defines neither, so both are the designer's ruling
rather than a reading — which is why they are written down here and in
`App\Enums\ResearchCardMarking` rather than inferred from anything.

**A card carries a *list* of them, because nothing stops it printing both.** So
`research_cards.markings` is json rather than a column, and `App\Support\CardMarking`
is the pair of a kind and — where the kind needs one — the suit it names. The
enum on its own is not a whole marking: a Restricted with no suit is a rule that
would silently check nothing, so the value object's constructor refuses one and
so does the deck seeder. Where the *strictest* marking should win, it does:
`EquationCard::minimumSetSize()` takes the maximum rather than the last one read.

**Restricted narrows `suitsFor()` rather than only being checked in `validate()`.**
That is what makes it come out right at scoring time as well: a set of nothing
but wilds facing "Other side must be Cog" *is* Cog, so Cog is what it pays in and
nothing else can be claimed for it. Validation still reports the failure
separately, against the *printed* suits, so the message names the card that did
the restricting instead of calling the far set mixed. Two cards demanding
different suits of the same set each look satisfiable alone — an all-wild set
could be either — so the clash is caught by the narrowed set coming out empty,
which is a check of its own.

**A marking is an enum, not the text it started as.** The equation rules act on
it, and a rule keyed off a string somebody typed breaks on a capital letter. That
also makes it a choice rather than a free-text box wherever Control sets one: a
marking the rules could not enforce would be worse on a card than no marking. A
third marking is a case, a `minimumSetSize()` or a `demandsOfTheOtherSide()`, and
a line in `CardMarking`.

**A blank slot on a form is not a half-written marking.** The marking form has a
slot per kind and an untouched Restricted posts an empty suit, so
`ShapesCardMarkings::filterBlankMarkings()` drops it at the HTTP edge — while
`CardMarking` and the deck seeder still refuse one, because in a config file
somebody wrote by hand a blank suit really is a mistake.

**Deck customisation is priced on the tree, and prices unlike anything else on
it.** "4 research credits in any suit", "6 in any suit and 3 in another", "5 from
each suit" — the suits are the player's choice, which is the point of customising
a deck, so it cannot go in the four cost columns. It goes in a `deck_grant` json
column on `technology_types`: a list of amounts the player assigns to suits (all
different), the value range, whether the card is wild, what it is printed with,
and how many Research Facilities the row asks for. The card takes the suit of the
first amount, which is those rows' own "in that suit" and "in the first suit".
Control can write one of these rows during play like any other proposal (3.2.4);
a blank set of amount boxes on that form is what says a proposal is an ordinary
technology. Upgrading an existing card is the other half of 3.2.3 and the tree
prices it nowhere, so it is a custom proposal too: Control names a price, takes
the points with the tracker controls, and edits the card.

**A technology has to be housed, and that is a bar on researching rather than a
step afterwards.** Footnote 7 to 3.2.2: "If you do not have any Facilities that
this technology can be placed in, you may not research them." Storage is 2 for
every *Corporate* Facility the Corporation owns, so a Corporation with none can
store nothing at all — which reads oddly until you notice it is exactly what "2
multiplied by the number of Corporate Facilities you have" says.

**A Corporation opens the game already holding technologies.** Twenty across the
five, and they are the shape of each Corporation's position rather than a bonus:
ANT's four pieces of Power, DTC's four of Arms, Genetic Equity's four of Miracle
Genetics, McCullough's Factory and Construction Leader, and Gordon's three plot
hooks. `App\Actions\GrantStartingTechnologies` runs from
`CreateDefaultFacilities`, after the Facilities exist, because 3.2.2 houses every
technology in a Facility and a starting card sitting nowhere would have the
storage count wrong from the first turn. It writes a starting position rather
than a change, exactly as the Facilities do: the cards are free, nothing goes
through `TrackerService`, and no Research Points move because none were spent.
Origin is **Researched**, which is what lets 3.2.7 give ANT a working Power on any
single piece — a Corporation that stole one needs all four. Each card goes in the
emptiest Facility that will take it, so four pieces of Power are not all in one
building for one Run to take, and a card naming a Facility type goes in that type.

**Which technologies those are is a column, because neither of the obvious
markers works.** They cost nothing in every suit — but so do fourteen others, the
six deck customisation rows among them. Seventeen of the twenty carry the words
"Starting tech" in their description — but Gordon's three (`RGR094`–`RGR096`) carry
real descriptions, and reading the marker off that column handed Gordon nothing.
So `technology_types.starting` says it outright, seeded from
`TechnologyBlueprint`, which also lets Control mark one on a Corporation they
invented mid-game. A Corporation with nowhere to house one is *reported* by the
action rather than thrown: a roster Control built with no Corporate Facility can
store nothing at all, and that must not stop a game being created.

**A copy is a discount, not a technology.** A card Research Control made for a
partner (3.2.5) or a Run brought back (3.2.6) is a `technology_holdings` row with
status Claimed: it sits in a Facility, it takes up storage — footnote 8 says so
outright — and it does nothing until the Corporation pays. Paying flips that card
rather than creating a second one. The discount rounds the *resulting cost* up,
which 3.2.6 states in as many words: a 5 at 50% off is 3. The percentages
(25% weak copy, 50% good copy or theft) are defaults on the origin rather than
rules, because 3.2.6 leaves a copy's worth to Research Control's judgement of
"the strength of the copy".

**A split technology's owner is not its thief.** The Corporation that researched
one works it holding any single piece; a Corporation that stole or copied it
needs every piece before it works at all (3.2.7). That is the only place in the
application where how something was come by matters more than what it is, and it
is why `TechnologyOrigin` carries `needsEveryPiece()`. Which technologies are
split is read off their printed names — "Power (Part 1/4)" — at seed time, so a
technology Control writes during play is split if they name it that way.

**Prerequisites are printed titles, and a split group counts as one.** A title is
what a Research player shows Research Control (footnote 6), and Control may add a
technology that others already name, so nothing is a foreign key. A Corporation
holding "Power (Part 1/4)" satisfies a prerequisite of "Power", for the same
reason 3.2.7 lets its owner work the technology on one piece.

**Trading points asks nobody's permission.** 3.2.5 has players trading "however
they wish ... by passing over the requisite tokens", so a transfer is one-way and
one-sided: one pile goes down, the other goes up, and what came back — Credits, a
favour, another suit — is settled at the table. Both movements are in the ledger
with the other Corporation named, so a trade can be reconstructed from either end.

**Affordability is checked against the database, not the model.** Every service
here may be holding a Corporation loaded before the last tracker write, and
`TrackerService` clamps Research Points at zero — so a stale check would not
refuse an overspend, it would quietly take the pile down to nothing.
`Corporation::researchPointsIn()` exists for exactly that, and is what every
"can they pay?" goes through.

**Two tiers on the page, and the line is the Facility board's.** The table is a
table in a room: who is sitting at it, in what order, whose turn it is and the
six public cards are everybody's. A hand, a deck, a pile of points and a tech
tree go only to the Corporation they belong to, because 3.2.5 makes the size of
that pile semi-secret and a hand everybody can read is not a card game. Inside a
Corporation the Research seat plays and the CEO and Security read — a hand two
people can play from is a hand neither can plan with, which is the same reasoning
that gives the defence board to Security alone. `App\Policies\CorporationPolicy`
is the whole of that boundary.

**The equation builder takes clicks and drags, and needs both.** Clicking cycles
a card — into the first set, into the second, then back out — which is one
control for every answer. Dragging a card from the pool or your hand into either
tray says the same thing by pointing at it, which is what people reach for when
two sets of cards are laid out in front of them. Neither covers the other:
clicking is the only path for a keyboard, and dragging is the only way to move a
card straight from the first set to the second without cycling it past a state
nobody wanted.

**So the research table registers no `KeyboardSensor`, and that is deliberate.**
The cards are buttons, and a keyboard drag would capture Enter and Space from
them to offer a slower way of doing what the click already does. For the same
reason only dnd-kit's `listeners` go on the wrapper and never its `attributes`:
those announce a draggable that answers to the keyboard, and they would also
take the button's own accessible name and `aria-pressed` off it.

**Touch holds where the Facility board measures distance.** The defence board
pins its hand and gives its cards `touch-action: none`, because both ends of
every gesture are on screen at once. The research cards sit in a page that
scrolls, so a `TouchSensor` with a 200ms delay is what lets a swipe starting on a
card scroll the page instead of picking the card up. The mouse still activates on
6px of travel, so a click on a card is still a click.

**A drop that misses, misses.** `closestCenter` — dnd-kit's usual suggestion, and
what the Facility board falls back to — always finds a nearest target however far
away the pointer is, so a card let go over empty space would silently join a set.
The table requires the pointer to actually be inside a tray, which is also why
the pool and the hand are drop targets in their own right: dropping a card back
among the others is how it leaves the equation.

**The research page polls, because the table is a table other people sit at.**
The turn passes to you when somebody else plays, the pool refills under you and
the sitting shuts when the phase is called — none of it anything your browser
did. `usePoll(5000, { only: ['game', 'research'] })`, the same five seconds the
dashboard and the Control panels use. A half-built equation survives it: the
trays are local state keyed by card id, so a card another player has since spent
simply drops out of the tray it was in, which is what has happened to it.

**Leaving the table is not the player's, and a dry deck is final.** The rulebook
makes leaving a choice (3.2.1) and this application does not offer it: a seat is
left by running your deck dry, and taken back by Control from its own panel.
"You have no cards left in your deck when you try to draw up your hand limit"
ends that player's game, so it ends it — there is no player-facing way back to
the table, and the next sitting is what gathers the cards and deals again.
Control can still seat somebody mid-sitting, because Control can always
override; nobody else can.
`ResearchTableService::leave()` and `rejoin()` are both still there and both
still used — by the dry-deck path and by `Control\ResearchSessionController`
— so what went is the player-facing routes, not the mechanism. The page still
says *why* you are out; it just does not offer a way back in.

**A refused score is shown on the form that was refused.** Several equations
wait at once and they all report against the same three keys, so a page-level
`errors` would put one form's refusal under every form on screen. Each
`ScoreForm` therefore keeps its own, set from `router.post`'s `onError`. Same
bug as the equation builder's dead Play button, one layer along.

**What you have to spend is kept on screen while you read what things cost.**
The tech tree is long and the four totals were at the top of the page, so buying
meant scrolling up, remembering four numbers and scrolling back down — the strip
is sticky inside the tree's own card instead. It sits at `top-16` to clear the
app header, which is sticky itself and drawn above it, and spans the card's
padding with a negative margin so the rows scroll behind an edge rather than
past a floating box.

**The research payload has a presenter of its own.** `App\Support\ResearchPresenter`
rather than more of `GamePresenter`: a hand, a pool, a turn order, every equation
waiting to be scored, a tech tree with each row's affordability worked out, the
cards a Corporation has researched and the deck it plays from is about as much as
the rest of the application sends put together.

**Not modelled, deliberately:** what any technology's effect does. Effects are
printed text for Control to read, exactly as a Facility type's are — the sub-games
that would act on them are unbuilt.

## Logos

**A logo is identified by a slug of the name it belongs to, and by nothing else.**
The roster in `config/running_hot.php` is keyed by name, so `App\Support\LogoImage`
resolves `public/images/logos/<slug>.{webp,png,jpg,jpeg}` — Augmented Nucleotech
from `augmented-nucleotech`, g33ks from `g33ks`. There is deliberately no
`logo_path` column: nothing stores a path, so no path can drift from what it
belongs to, and artwork committed after a game was created appears in that game
immediately rather than waiting on a column to be backfilled. The directory is
listed once per request, for the reason `CardImage` does the same — and the class is
named for neither of the things that have logos, because it does not care which it
is asked about.

**Two kinds of thing have one, and they differ in what happens when there is
none.** The nine factions are one. The other is the three characters that are
*organisations* rather than people: Business Times and Th3 Undergr0und are
newspapers and HM Government is a government, each staffed by a single player.
A faction always gets a badge and falls back to its initials, because a faction is
a side players need to pick out of a list. The faction's colour is painted **only**
behind those initials: behind real artwork it would impose an arbitrary hash colour
on somebody's brand, so a logo gets a plain white ground instead — not no ground,
because the artwork is transparent around a black diamond frame that vanishes on a
dark page. A character gets one **only where
artwork exists** — a game has forty-odd of them and all but three are somebody's
name, so a coloured square against every one would imply an organisation where
there is none. `FactionBadge` is the always-badged one and is faction-only;
`resources/js/components/character-logo.tsx` renders nothing when asked about a
person. Adding `jack-scanton.png` would simply start showing it, which is the
right amount of ceremony for a decision Control might make.

**Two variants per faction, because one picture cannot do both jobs.** The square
badge is the logo alone, under the bare slug, and it goes wherever the name is
already written beside it — which is every one of the application's own screens, at
24 to 48 pixels square. The wide lockup sets the name as type inside the picture,
under a `-wide` suffix, so it only belongs where it can stand in place of written
text and has room to be read: the `#facility-list` embed is its one consumer, and
it takes the full width of a message as the embed's `image` rather than an 80×80
`thumbnail`. `LogoImage::ICON` and `::WIDE` name them.

**Neither variant ever stands in for the other in the resolver**, which is the same
asymmetry `CardImage` uses for a card's back. A lockup crushed into a 24-pixel
square is an unreadable smudge, so a faction with only a lockup draws its initials
in the small slots. A caller with a real preference asks for each in turn and says
which it would rather have — `FacilityListEmbed` is the one that does, taking the
lockup as `image` and falling back to the badge as `thumbnail`. The `-wide` suffix
is only stripped when something is left over to be a faction, so `wide.png` belongs
to a faction named Wide.

**A faction with no logo is the normal case, and a clean checkout is in it.** So
every caller copes with null: the Discord embed carries neither picture key at all
rather than an empty one, and the application's own pages draw a faction's
initials on the colour `GuildBlueprint::colourFor()` gives it. That is also exactly
what a Corporation Control invents mid-game gets, which is why the fallback is a
first-class rendering rather than a placeholder — `FactionBadge` (the React one)
draws both at the same size, so a row of nine lines up whether or not the artwork
has been drawn.

**No SVG, even though the browser would draw one perfectly well.** Discord does not
render svg in an embed, so a faction whose only file was vector would look right on
the page and silently have no thumbnail in the channel. One resolver answering the
same for both consumers is worth more than vector artwork at 80×80.

**The logo and the colour travel together, because the second is the fallback for
the first.** `App\Support\FactionBadge::for()` returns the name, the logo path and
the colour as CSS, and it is spread into every payload that describes a faction —
so `name`, `logo_path` and `colour` mean the same thing on the Control panel, the
dashboard, the Facilities page and the card holdings, and the browser takes one
shape instead of one per page. The colour is shaped server-side because it is
derived from an md5 of the name, and a second implementation of that in TypeScript
would be a hash function written twice to agree on a swatch. Two factions can
collide on a colour — `colourFor` hashes across ten — which is already true of the
Discord roles, and the logo is what tells them apart.

**The committed files are web-sized, and the export is not.** The artwork comes out
of design at print resolution — 8334px square for a badge, 16668px wide for a
lockup — and a browser decompresses an image in full however small the box it draws
it in, so one of those badges is about 278MB of bitmap and the Control panel draws a
dozen. What is committed is 256px badges and 800px lockups as lossless webp, which
is 381KB for all twenty-four against 22MB for the export. Resize anything new on
the way in; the README carries the command.

**Discord fetches an embed's image itself**, so `LogoImage::urlFor()` is absolute
where `pathFor()` is rooted. That also means Discord cannot see a logo on a dev
server it cannot reach: the thumbnail is quietly absent rather than broken. There
is a line about this in the README, beside the file names.

**A test must never write over real artwork**, for the reason the card tests must
not: the logos are committed, so a test cleaning up after itself would delete one.
`LogoImageTest::writeLogo()` asserts the file does not already exist rather than
trusting the convention, and every name it uses is one nothing in the game has.
`TestCase` calls `LogoImage::flush()` for every test.

**Nor may a test assert that a real faction has *no* artwork.** That is the same
mistake from the other end, and it is the one that actually happened: a test
asserting Gordon's embed carried no picture passed for exactly as long as the
repository had no logos in it, and failed on the commit that added them. A test
about the missing case makes its own subject with a name nothing in the game has —
`Corporation::factory()->create(['name' => 'Test Unbranded Combine'])` — so it goes
on meaning what it says after every faction has been drawn.

## The icon font

The rulebook prints the four Research Point suits as icons and never names them in its body text, which is why they do not survive `pdftotext` and why issue #6 says to read the PDF for §3.2. The game's own font draws them, and it is committed at `resources/fonts/RunningHot-Font.ttf`.

**It is an icon font: every icon is drawn by an ASCII capital.** So a "glyph" is a plain letter — Physical is `E`, Brain is `B` — and it only reads as a picture while the font is loaded. Two consequences, both load-bearing:

- **A screen reader left to itself says "E" where the page means Physical.** So the glyph is always `aria-hidden` with its name in an `sr-only` span beside it, and nothing renders a glyph directly: it goes through the `GameIcon` React component, which is what stops the pairing being forgotten at the next call site.
- **A missing glyph fails quietly**, rendering a bare capital rather than nothing, which reads as a styling bug. `tests/Unit/IconFontTest.php` therefore parses the font's own `cmap` table and asserts every icon in use is really in the file. `N` is the one capital the font has no icon for, which makes it the canary that keeps that test from passing vacuously.

**What each icon means is recorded in `App\Support\IconFont`, and nowhere else.** The glyph names inside the font are only the letters, so nothing in the file says what any of them is — working it out again means rendering the font and looking at it. The three enums that draw themselves (`ProtectionKind`, `EquipmentCategory`, `ResearchSuit`) take their glyphs from there, and a wild research card draws `Y`. `G` (Boost) is drawn and recorded but unused, because Runs are not built.

**The font is loaded through Vite, not from `public/`.** `laravel-vite-plugin` sets Vite's `publicDir` to `false`, and in development the stylesheet is served from the Vite origin — so a root-relative `url('/fonts/…')` asks the dev server for a directory it does not serve, 404s, and the icons silently degrade to bare letters for everybody running `composer run dev` while working perfectly once built. Anything referenced from CSS has to live under `resources/` and be referenced relatively. Card artwork is the opposite case and belongs in `public/`.

## Built so far

The turn engine, the trackers, Discord-handle character claiming, Discord server provisioning with role assignment, Facility Defence — Facilities, the ordered stacks and the security budget — the game's three real card lists with the Protection Card inventory, their printed artwork and the icon font, the drag-and-drop board Security arranges their own defences on, logos wherever the application names a team or one of the three characters that is an organisation, the Council — the game's agenda deck with Control picking what goes up, the Chair's powers over it, and Political-Will-weighted voting with secret ballots — the research sub-game: the equation card game, the tech trees, deck customisation, point trading and technology copies — and Runs: submitting and ordering the groups at a Facility, the four steps, every consequence, both ways a run can end, the accesses a successful one buys, the screen players work it from, the Runners being let into the Facility's own Discord channels for the length of it, and what each Runner is carrying — the Equipment holdings, seeded from the briefings and handed out by Control.

**What is left is tracked as GitHub issues**, each written against the relevant rulebook section — start there rather than re-deriving the scope. Runs are the highest-value piece and the last of the sub-games, and everything a Run operates on is now built: the Facilities, their Protection Card stacks, and the technologies stored in them. `#facility-list` now carries the Facility list once Control publishes it.
