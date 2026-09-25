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
- **Notoriety belongs to the Runner, and a gang's is the total of its members'.** The rulebook heads the section "Gang Notoriety" and the glossary calls it "a measure of how well-known and successful your gang is", which is what this application modelled first: one column on the gang. It is the designer's ruling that the number is earned by the person who did the thing — which 2.3.2's own sentence already reads as, "as members of your gang successfully complete runs or perform other actions, the Notoriety of your gang will go up". So `Tracker::Notoriety` is a Character tracker, and **`gangs.notoriety` is gone rather than kept in step**: a stored total beside the rows it sums is a second source of truth, and the one that drifts is always the summary. `Gang::notoriety` is an accessor reading a `withSum('characters', 'notoriety')` where one has been eager loaded and asking otherwise — so anything listing gangs must load it, as `GamePresenter::trackers()` and `RunEngine::mostNotoriousGangIds()` both do. That second one is rulebook 3.4.1's tiebreaker 2, and it is now decided by summing a roster: three Runners at 2 each out-rank one at 5, which a per-gang column could never have shown. A gang carries no tracker of its own any more, so `TrackerService` refuses one and the Control panel reads its figure rather than editing it. Historical `tracker_adjustments` rows pointing at a gang are kept — the ledger is the answer to "why did that number change?", and tidying a relation is not worth deleting the question's history.

## Where things live

| Concern | Location |
|---|---|
| Phase transitions, pause/resume/extend | `App\Services\TurnEngine` |
| Tracker writes and the audit ledger | `App\Services\TrackerService` |
| Facility slots, card stacks, reorder and removal costs | `App\Services\FacilityDefenceService` |
| The shop's list, its stock, and what a purchase moves | `App\Services\ShopService` |
| Building a Facility, and the turn's delay | `App\Actions\RequisitionFacility` |
| A CEO requisitioning their own Facility, and the type sheet players read | `App\Http\Controllers\FacilityRequisitionController`, `CorporationPolicy::requisition`, `resources/js/components/facility-requisition.tsx` |
| A Facility Control builds for the Runners to hit | `Facility::isPlotFacility()`, `RequisitionFacility::buildForControl()` |
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
| A roll a player makes for Control to read, outside any run | `App\Actions\RollDice`, `App\Support\DiceRollPresenter`, `resources/js/pages/dice.tsx` |
| Stock Certificates: handing out, handing on, cashing in | `App\Services\StockCertificateService`, `App\Enums\StockCertificateOption`, `App\Support\StockCertificatePresenter` |
| Discord announcements | `App\Services\DiscordAnnouncer` |
| Who Control is, per game | `App\Models\ControlMember`, `App\Actions\ClaimControlSeatsForUser` |
| What a game's Discord server should look like | `App\Support\Discord\GuildBlueprint` |
| The `#facility-list` embed, and posting it | `App\Support\Discord\FacilityListEmbed`, `App\Actions\PublishFacilityList` |
| Building and reconciling that server | `App\Actions\ProvisionDiscordGuild` |
| A Facility's own channels | `App\Actions\ProvisionFacilityChannels`, `App\Jobs\SyncFacilityChannels` |
| Letting the Runners into the Facility they are hitting | `App\Actions\GrantRunChannelAccess`, `App\Jobs\SyncRunChannelAccess` |
| Emptying that channel when the turn ends | `App\Actions\ClearRunChannels`, `App\Jobs\ClearRunChannels` |
| Handing a player their Discord roles | `App\Actions\SyncDiscordRolesForUser` |
| Discord REST calls as the bot | `App\Services\Discord\DiscordApi` |
| Inertia payload shaping | `App\Support\GamePresenter` |
| What a player may see of the Facilities | `App\Http\Controllers\FacilityBoardController` |
| What a player may see of their Equipment | `App\Http\Controllers\EquipmentController`, `resources/js/pages/equipment.tsx` |
| The card lists every player reads | `App\Http\Controllers\CardListController`, `GamePresenter::publicCardList()`, `resources/js/pages/cards.tsx` |
| One player handing a card to another | `App\Http\Controllers\EquipmentTransferController`, `App\Services\EquipmentService::transfer()`, `App\Policies\CharacterPolicy` |
| Security arranging their own stacks | `App\Http\Controllers\FacilityDefenceController`, `App\Policies\FacilityPolicy` |
| Moving a stored technology between Facilities | `App\Http\Controllers\FacilityTechnologyController`, `App\Services\TechnologyService::place()` |
| The drag-and-drop defence board | `resources/js/components/facility-defence-board.tsx` |
| Auto-advance and its backstop | `App\Jobs\AdvancePhase`, `game:tick` |
| The rulebook and the game's background link in the sidebar | `App\Http\Controllers\RulebookController`, `games.background_url`, `resources/js/components/app-sidebar.tsx` |

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

**The webhook URL never reaches a player, and it is kept off the wire rather than merely unrendered.** It is a bearer credential: anybody holding one posts into `#announcements` as the announcer, with no token and no authentication, and Control's announcements carry rulings. It used to ride in `GamePresenter::summary()`, which is the payload of the dashboard, the Facility board, the equipment page, the Chamber, the shop, the runs and the research table — nothing on any of those pages drew it, so it was invisible everywhere except devtools, which is exactly where somebody would find it. So `summary()` is the shape everybody gets and carries no webhook at all, and `controlSummary()` is a spread of it plus the one key. The default is the safe one on purpose: a player-facing page added later leaks nothing by forgetting to ask, and a Control page that wants the credential has to say so — in TypeScript as well, where `GameSummary` and `ControlGameSummary` draw the same line. Omitted rather than sent as null, so the type is honest instead of nullable-and-sometimes-lying.

**The application never creates the guild.** Discord's Create Guild endpoint only works for bots in fewer than ten guilds and hands back a server nobody is a member of, so Control makes the server by hand and the application is given it. Snowflakes are stored as strings — a bare one is a numeric string that PHP will silently coerce to an integer array key, which is why `ProvisionDiscordGuild` indexes them behind an `id:` prefix.

**Nobody should have to copy a snowflake.** Three ways to attach a server, in order of least work: the bot-add OAuth flow (`connect` → Discord's own server picker → `callback`), which is the good path because `response_type=code` plus a registered `redirect_uri` makes Discord hand `guild_id` back, so the invite and the ID happen in one click; a list of the servers the bot is already in, from `GET /users/@me/guilds`, served as an `Inertia::optional()` prop so opening the Control panel never calls Discord; and a plain text field for correcting one by hand. The callback also checks the granted permission bits, because Discord lets the person untick boxes on the way through and a missing Manage Roles would otherwise only surface halfway through a provision run. `DISCORD_BOT_REDIRECT_URI` must be registered in the Developer Portal next to the login one.

**The bot holds the Control role, and takes it before making any channel.** Discord drops every permission in a channel its caller cannot view, and `@everyone` is the bot's only source of View Channel — so a category locked to one team locks the bot out of it too, and it then cannot create the channels that belong inside it. Every private channel in the blueprint already grants Control, so holding that role is all the access the bot needs. The role carries `permissions: 0` and only ever opens channels, so this grants the bot nothing at guild level. Do not reorder `giveBotTheControlRole` after `reconcileChannels`: provisioning dies on the first private category.

**Every faction has a public front as well as a private room.** A Corporation or a gang gets a public text channel and three public voice rooms in its own category, beside the two only its own players can see. The private room is where a team plans; the public ones are where the rest of the game comes to talk to them, which is the thing a locked category cannot be — a faction that had only the locked room was approached by DM, and Control could not see any of it. Three voice rooms rather than one because a faction is negotiating with several people at once for most of a turn, and one room means whoever got there first owns it. They are numbered rather than named for a purpose, since what a room is for changes every fifteen minutes.

**A public channel inside a locked category is public**, and that is not a contradiction: Discord computes a channel's permissions from its own overwrites, so a category's `@everyone` denial only reaches a channel that has nothing to say for itself. Keeping them together is the Facility channels' reasoning again — everything a team owns is in one place. What the public channel cannot share is a *name*: the adoption pass tells two channels in one category apart by name, type and parent and nothing else, so the public text channel takes a `-public` suffix rather than the plain slug.

**Characters who belong to no team get a role each, and a category to live in.** The two press outlets, HM Government and the three Freelancers have no Corporation and no gang, so there was nothing to permission a channel against and they had nowhere at all — a gang has a locked room to plan in and a Freelancer had a DM thread. Each now has a role of their own and a private text channel in an `Independents` category, plus a public voice channel, because the whole of a Freelancer's game is being available to whoever wants to hire them. A role rather than a per-member overwrite for the reason a Runner's key to a Facility is the other way round: who the press are is a standing fact about the guild, not something true for ten minutes.

Who counts is read off the *absence of a team* rather than off a list of roles, so a character Control invents mid-game — a second Government department, the Runner Representative of 3.1.3's own agenda card — is housed without new code. Naming HM Government in the blueprint would be the mistake `characters.council_votes` already avoids.

**Each press outlet publishes where everyone reads and only they write**, and it is one channel per outlet rather than a shared `#press`: Business Times and Th3 Undergr0und are rival papers, and a single feed would run their copy together under one masthead. The publication takes the plain slug and the private desk takes a `-desk` suffix, because the channel everyone reads should be the one named after the paper. Control can post in both — a correction, a wire story, a ruling — the same grant `#announcements` and `#facility-list` already carry.

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
"Political Will" because both kinds are at the same ballot form.

**And the same reasoning reaches the Chair**, which used to be the one thing a
seat could not do. `council_sessions.chair` is a morph too, for the reason the
ballot's voter is: a CEO chairs for their Corporation and the Government chairs
as itself. It was a `chair_corporation_id` on the reading that 3.1 rotates the
Chair between the Corporations — but footnote 1 makes the rotation "an order
announced by Council Control on the day" rather than a rule about who may be in
it, and the game opens with HM Government in the Chair, so the column was
refusing the first sitting of every game. `CouncilSession::isChairedBy()` is
how anything asks, because a Corporation and a character can share a row id and
the bare number would put one in the other's place.

**A CEO still never chairs as themselves.** They chair for their Corporation,
which is what goes in the Chair, so `setChair()` refuses one by name and points
at the Corporation instead — the same answer `seat()` already gives a CEO asking
for a bloc of their own. `CouncilSessionPolicy::chair()` then reads the Chair
back the way it was stored: a Corporation's authority from the CEO seat it
fields, a character's from the character itself.

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

**A Corporation is in the rotation by being a Corporation; a seat is in it only
when Control has put it there.** That asymmetry is the rule rather than an
oversight, and `characters.council_chair_order` is its half: a Corporation with
a CEO has a seat whether or not anybody has ordered it, so one Control has not
ordered sorts last rather than dropping out, while a bloc Control has seated
votes without ever coming round to chair unless Control says otherwise. The
roster writes HM Government in at 1 and the Corporations follow from 2, which
is the whole of "the game starts with HM Government being the chair" — and
`CreateDefaultRoster::reservedChairOrders()` is why the Corporations start after
it rather than colliding with it.

Saving the rotation saves the *whole* list, because that is what rearranging one
is, and it is what lets a seat leave the rotation by being left out of it. A
Corporation cannot leave: it is in either way, so a Corporation the list omits
keeps whatever order it had. Handing somebody the Chair *now* is a separate act
and deliberately immediate — it is a ruling about the sitting in front of you,
where the rotation is an order for the turns after.

**Taking a seat away takes it out of the rotation with it**, which is the one
thing about the rotation that is *not* Control's to announce: somebody who no
longer sits at the Council cannot chair it. `CouncilService::seat()` clears
`council_chair_order` alongside the votes, because an order left behind on the
row would put them silently back in the rotation the moment Control seated them
again — and seating somebody still never puts them in it, which is the
asymmetry above. Changing what a seat is *worth* leaves its place alone.

**Who is chairing is said outright on Control's panel**, not left to be found. It was only a badge inside the rotation list, which failed twice over: a seat can be in the Chair without being in the rotation at all — how every game opens, with HM Government chairing from the "seats that never come round to chair" line underneath — and **most of the time Control is looking at the panel the Council has not sat yet**, so there was no chair to mark and the page said nothing whatsoever. So the sitting card carries a banner in all three states rather than the one: who is chairing, that the Chair is vacant, or, before the sitting is made, whose turn it is by the rotation. That last is `next_chair` on the control payload, stepped through the order by turn number server-side, because a browser repeating that arithmetic is a second implementation of the rotation. Both lists mark the chairing seat, and the rotation marks who chairs next *only* while nothing is chairing — after that the seat in the Chair is the answer, and a second marker about the same turn reads as two different seats. The Chamber already said it in as many words, which is why nothing moved there.

**The rotation reads as columns, not as a wrapped row of controls.** Every row is the same shape — number, badge, name, the two arrows, then the actions — with the name taking the slack, so everything after it starts at the same place down the list. Anything that varies per row (the In the Chair badge, a seat's Remove) sits *inside* the name's column rather than between two of them, which is what made the list ragged when it was one `flex-wrap` line — Remove in particular is on the far side of the name from the actions, because a button one row in six carries would otherwise push that row's arrows and Chair now out of line with every other row's. The buttons lost their sentences for the same reason: five copies of "Give them the Chair now" is what the width was going on, and the sentence lives in each button's `aria-label` where it is still read out.

**The panel's rotation list is the server's, rearranged locally.** Laying a list
out is local until it is saved, but *who is in it* is not: seating somebody,
taking a seat away, and the Add and Take out buttons all post and come back with
a new rotation. The list was `useState`'d once and never re-read, so those four
went on drawing the list React was first handed — and Add and Take out, which
post immediately rather than waiting for Save, looked like they did nothing at
all. It re-syncs during render when the server's list changes, which is React's
own answer for state derived from a prop; an order Control has rearranged and
not yet saved survives a poll, because the server's list is unchanged.

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
it once they and Control agree, so agreeing is the player's to do too.

**Writing one takes a seat at the Council.** 3.1.3 hands blank cards to
"players" rather than to "CEOs" and this was read literally at first — any
character in the game could write one. It is the designer's ruling that it
takes a seat, and that somebody without one who wants an agenda raised has to
convince somebody who has one. `Character::scopeOnTheCouncil` is who that is,
and the whole of the Chamber is behind it now: see *The front door, and the
theme* below for the four places that ask the one predicate.

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

**A CEO builds their own Facilities; Control keeps the overrides.** Every Corporate seat reads the type sheet on `/facilities` — prices, what each type grants, and what a Runner gets for accessing one — because it is the whole Corporation's business, and before it was there the only way to learn a price was to ask Control. CEOs are the only ones allowed to build Facilities (`CorporationPolicy::requisition`) — a ruling, not a sign-off step: there is no requisition slip for Security to raise — and the player route pays the type sheet's price with no cost field at all: it opens next turn, during Setup only, and the refusals are `RequisitionFacility`'s, shared with Control's panel. Control's form keeps both overrides — a blank cost is the sheet's price, 0 is free, any other number is a ruling (Construction Leader is one) — and Build now. The name clash check lives in the action for the same reason.

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

**Where a technology is stored is Security's too, and it is dragged on the same
board.** The rulebook has a Research player place a technology when they
research it and then says nothing about moving it afterwards, because at the
table the cards are in front of you and you pick one up. What the application
had instead was `TechnologyService::place()` and a Control route that *nothing
on any screen called* — so a card could not be moved at all without somebody
hand-writing a PATCH, and a Corporation that had just lost a Facility, or wanted
its four pieces of Power in four buildings rather than one, had no way to say
so.

It is Security's for the reason the stacks are: which Facility holds a
technology is a decision about what a Run would come away with and what is worth
defending, made by the person already making the other half of it, and 3.4.2
keeps a Facility's contents Secret from outside the Corporation rather than from
Security. `FacilityPolicy::defend` is the whole boundary again, asked of the
Facility the card is going *into* — which is both what the gesture names and
what the route's path carries. The controller adds the second half of it, as
`RunController` does: the policy only asks whether that Facility is yours, so
without a check that the card's Corporation matches, a Security player could
pull a rival's technology across into their own building. The CEO and the
Research player read the cards where they are stored and cannot move them, and
they could not read them on that page at all before — the technologies were
drawn on the Security tier only, which is the wrong line: 3.4.2 draws it around
the Corporation, not around the seat.

**A technology shelf is not a stack, and the board says so in what it draws.**
Storage has no order — nothing is met first, and a Run draws from it blind — so
the shelf holds chips rather than the column of card faces the stacks use, with
the whole card a hover away and in the drag overlay. Four gestures on one board
now, and the two that cannot mean anything are refused by name rather than
silently ignored: a Protection Card dropped on a shelf is told it goes in a
stack, and a technology dropped on a stack or in the hand is told it is stored
rather than installed.

**Every stored card also carries a Move menu, and that is not a nicety.** A drag
has no keyboard path here — the board registers dnd-kit's `KeyboardSensor` for
sorting a stack, not for carrying a card across the page — and on a phone the
Facility you are moving to is usually scrolled off the screen the card is on.
Same reasoning as the Remove button on every installed card. The menu lists only
the Facilities that would actually take the card, and it goes through the same
guarded path the drop does rather than a second copy of the refusals. Control's
panel has the same menu on its own route, because Control holds no Corporate
seat and so never sees `/facilities`' own tier.

**A card a Run took is not in the building to be moved.** `place()` refuses a
Destroyed or Stolen holding outright: the row stays because what a Corporation
once had is worth more than a tidy table, but putting one back is `restore()`
and Control's judgement rather than something a drag does quietly. Control's
update route therefore restores *before* it places, so salvaging a card and
saying where it goes is still one request. The same reading fixed a quieter bug
one layer along: `GamePresenter::facility()` listed every holding whatever its
status, so a card Control had destroyed stayed on the Facility board and counted
towards a capacity the server was not enforcing it against. It filters on
`TechnologyHoldingStatus::occupiesStorage()` now, which is the predicate the Run
side already used.

**Control installs through the shop's picker, not a `select`.** The list a stack
offers is the whole catalogue of its kind minus what is already in the
Facility - forty-odd cards, and a native `select` holding that is a list nobody
finds anything in, which is the reasoning the shop's `SearchPicker` already
carries. It searches the printed code and the card's *words* as well as its
name, because the question Control is answering here is "what do I want the
Runners to hit": typing "end the run" and seeing which thirteen cards do it is
the point of having a search. The kind is deliberately not searchable - a stack
only ever offers its own, so the term would match everything.

**And the refusal is drawn per stack, which is what made the picker usable.**
Installing spends a copy out of the Corporation's hand and the picker offers
cards it may hold none of, so "no copies left to install" is the refusal Control
actually meets - and nothing rendered it, so the Install button simply did
nothing. It is kept on the stack rather than read off the page, for the reason
the research table's `ScoreForm` and the run screen's `useRunAction` do: a
Facility has two stacks and a game has a page full of Facilities, and every one
of those posts reports against the same `protection_card_type_id` key, so a
page-level `errors` would put one stack's refusal under every stack on screen.
`ProtectionCardStackTest` pins the key the panel reads, because reporting it
anywhere else puts the button back to doing nothing quietly.

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

### Plot Facilities

**A Plot Facility is a Facility with no Corporation.** Control builds it for the Runners to run against, so the rulebook has nothing to say about it — it belongs to no roster seat, serves no Corporation's economy, and exists because the story wants somewhere to break into. `facilities.corporation_id` is nullable and that is the whole of the mechanism: `Facility::isPlotFacility()` reads the column, and everything a Run already operates on — the ordered stacks, the four steps, the accesses, the Discord channels it happens in — goes on working because it is the same table. A second model would have been the entire Run loop written twice.

The delete rule stays cascading. A Corporation removed from the roster still takes its Facilities with it, because turning them into Plot Facilities would quietly hand Control buildings it never built.

**What changes is everything that was a Corporation's.** Each of these is a consequence of there being nobody to charge rather than a rule of its own:

| | A Corporation's Facility | A Plot Facility |
|---|---|---|
| Stack slots | 3 of each, widened by Security Facilities | **no limit at all** |
| Installing a card | spends a copy out of the hand | spends nothing; the catalogue is enough |
| Removing a card | 1 Credit after the first each turn | free, and no copy comes back |
| Reordering | 1 Credit per card that moves | free |
| Security budget | escrowed off the Corporation, returned unspent | a number Control writes; no escrow, no ledger row |
| Technologies stored | 2 per Corporate Facility | **none, ever** |
| Who defends it | the Corporation's Security seat, and Control | Control alone |

**No slot limit is the one that is not an accident.** The cap exists to make Security Facilities worth building, which is a decision inside a Corporation's economy — Control is not playing it, so a plot building is as deep as the story needs. `FacilityDefenceService::slotsFor()` answers null for one, and null means no limit everywhere it travels, including the `slots` field the board draws.

**It stores no technologies**, so a technology access inside one finds empty racks and the Credits card pays on its installed cards alone. `TechnologyService::capacityFor()` returns nought and `place()` refuses by name rather than letting the "not your Facility" message stand in for it.

**Players see it as "Independent", never as a Plot Facility.** `Facility::INDEPENDENT_OWNER` is the one place that word is written. It is on `#facility-list`, on `/facilities` and on the target list every Runner chooses from — they have to see it, or Control has built a target nobody can aim at — and it is drawn with a `FactionBadge` like any other owner, so committing `public/images/logos/independent.webp` gives it a logo with no further code. Calling it a Plot Facility in public would announce Control's own hand: that name is Control's panel and this file.

**Refusing a defender by name, not by query.** `FacilityPolicy::defend` and `RunPolicy::isDefending` both return false for a Plot Facility before they go near the database. Left to the query, `where('corporation_id', null)` becomes `whereNull`, which reads as "characters in no Corporation" — every Runner in the game. `RunEngine::securitySeatOf()` is the same trap and the same answer.

**Its Discord channels sit in one shared category, locked to Control.** `GuildBlueprint::CATEGORY_PLOT_FACILITIES` — one category for all of them rather than one each, because what they share is that Control built them and there is no team to group them under. The category is only in the blueprint when the game has a Plot Facility, so a game without one grows no empty category. Everything else is unchanged: the pair is created by the same job, reconciled by the same provision run, and the Runners hitting one are let in by the same per-member overwrites for the length of their run.

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

**Equipment reaches a run in three places, and all three are the player's.** A
permanent item is equipped at `/runs` while the run is still Submitted - 3.4.1
places it in front of you, and the engine refuses it once the run has gone in.
A This-run or Single-use card is played during a step, one per Runner per
*step* rather than per run, which is what the worked examples make it. And what
either card then *does* is declared on the challenge form, in three boxes:
extra dice, die size, rerolling the misses once, and +1s to put on dice already rolled.

**Declared rather than parsed, for the reason nothing else here is parsed
either.** The seventy-four printed effects would be a second rulebook to keep
in step, and a card Control invented mid-game would get nothing from it. The
player is holding the card; `App\Support\Runs\RollModifiers` takes what they
say it grants this roll.

**A skill and a die are different things, and the difference is the halving.**
3.4.2 takes half of a skill rounded down for everybody who is not leading, so
"+2 Brute" is two dice to the Run Leader and one to anybody else, while "+1 die
for this roll" is one die to whoever rolls. So a card that changes a *skill*
goes on `run_participants.brawn_adjustment` / `hack_adjustment` and lasts the
run; a card that changes the *dice* goes in `RollModifiers` and lasts the roll.
Putting a skill through `RollModifiers` would quietly pay a non-leader double.

The adjustment sits on the participant rather than the character because Brawn
and Hack are Trackers - permanent, ledgered, argued about three turns later -
and a Shiv is carried into one Facility and out again. `RunParticipant::skill()`
is the one place the two are added, which is what keeps the pool the screen
quotes and the pool the engine throws from disagreeing: the challenge roll, the
access roll and the readout all go through it.

**A "+1 to one of your dice" is not a die in the pool.** Armour's effect lands
*after* the throw, on a face rather than on the count, which is the only way a
4 becomes the success it was one short of. `RollModifiers::bump()` puts each +1
on the highest die that is not yet a success - which is where it can do
something and is also optimal play, so applying it rather than asking takes no
decision off anybody. One per die, because the card says "one of your dice";
whether two may stack on one die is printed nowhere and is Control's call.

**A reroll and a +1 are both real, and they happen in that order.** `EEP001`
Mind jack retries the failures once and the new faces stand - a reroll keeping
the better of the two would be a different card - and Armour's +1 lands after
it. That order is not arbitrary: a +1 put on a die that is about to be thrown
again would be spent on a face nobody keeps. The wound Mind jack costs per use
is taken at the end of the run and is Control's, because nothing here counts
how many times a card was leaned on.

Both rules live on `RollModifiers` beside the other two, so what the dice did
is described in one class and `RunEngine::challenge` only says when.

**Both acts are `act` plus a check that the character named is one you hold.**
`RunPolicy::act` only asks whether you are on this run, which every Runner on
it passes - so without the controller's own half, a Runner could kit out a
gangmate or spend their cards. Exactly the boundary the accesses already draw,
and `RunController::authoriseActingAs` is now the one implementation of it.

**What is equipped is the whole group's to read; a hand is its owner's.** 3.4.1
equips "by placing them in front of you" - face up, on the table, where the
four people going in can all see it, and a group deciding who takes a
consequence needs to know who has the Armour on. The cards you have *not*
played are still in your pocket.

**Security sees none of it, and that is the one place `$privileged` is the
wrong question.** It means "the Security side or Control", and Security reading
the group's kit would know exactly what was coming down the corridor.
`RunPresenter::seesRunnerKit()` asks the right one - on the run, or Control -
and the *log* needs it too: a line naming what somebody equipped would hand
Security the loadout the payload carefully withholds, so it is redacted to
"equipped what they are carrying" rather than dropped. A card **played** during
the run is not redacted, because that one is laid on the table in front of
everybody.

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

**And the channel is emptied when the turn ends.** A Facility's channels are
permanent and every Run against it happens in the same pair, so without this a
group hitting Attercliffe Yard on turn 4 opens the channel and reads turn 3's
group working out exactly what was in the stack, in what order, and what each
card cost to get past. 3.4.2 makes that Secret and reconnaissance is what you
spend an action to find out, so last turn's transcript sitting in the room is a
free recon action for everybody who comes after. `App\Actions\ClearRunChannels`
is the sweep and `App\Jobs\ClearRunChannels` the fail-soft wrapper.

**What is worth reading is kept somewhere better, which is why deleting is
safe.** The run's own log is `run_events` - every card, roll, consequence and
departure, with who did it and when - and it is on the run screen and Control's
panel for the rest of the game. Nothing here touches it. What goes is the
conversation around it, which is the half that leaks. (This is the one place
that qualifies "a Facility Control removes keeps its channel, because the Run
that happened in it is still worth reading": the *channel* still outlives the
Facility, and the run is still readable - in the application, where it always
was.)

**A pinned message survives**, which is the override: Control pins whatever
should outlive the turn and the sweep leaves it exactly where it is.

**At the end of the turn, not the end of the Action phase**, so the group has
Team Time to read back over how it went. `TurnEngine::advance()` dispatches it
on the branch where Team Time ends and a new turn is created.

**Only the Facilities a group actually went into.** Read off the runs rather
than the roster, and only those with a `started_at`: a run submitted and never
begun put nobody in the channel. Sweeping every channel in the guild every turn
would be a pile of requests against a rate limit Discord enforces hard, for
channels where nothing was said.

**Two things about Discord's bulk delete, and both are load-bearing.** It
refuses a batch of one outright, and it refuses the *whole batch* if anything in
it is over a fortnight old - so one stale message would otherwise take the rest
of the channel's tidy-up down with it. `ClearRunChannels` splits the page and
falls back to single deletes for both cases. Paging is by snowflake and the
`before` id is taken *before* anything is deleted, because paging from a deleted
id returns nothing at all.

## The card lists

Three card families, all real data from the game's own card sheet, all seeded per game and all editable by Control. They live in `App\Support` beside the Facility type sheet rather than in `config/running_hot.php`, because three hundred cards do not read comfortably in a config file: `ProtectionCardBlueprint` (83), `EquipmentCardBlueprint` (74) and `TechnologyBlueprint` (144). `Game::booted` seeds all three when a game is created — none of them depends on the roster, so they arrive before it does.

**The code printed on a card is what identifies it, not its title.** Doppleganger is two cards, `PX011` in the physical stack and `PX012` in the cyber one, so `protection_card_types` is unique on `(game_id, code)` and titles may repeat. The one-copy-per-Facility rule of 3.3.4 keys on the card type rather than the title, so a Facility can hold both Dopplegangers and still only one of each.

**ANT's five cards are distinct cards, not its own names for the other five.** Öryggissveit, Takkaborðið, Öryggisluggari, Vélfærafræði sporðdreka and Engill (`PS015`–`PS020`) are ANT's own, and the four other Corporations hold Security team, Keypad, Security shutter, Roboscorpion and Angel instead. Everyone holds Orc.

**Their printed stats are identical pair for pair, which is exactly why it is worth writing down: they are still separate rows, and merging them would be wrong.** Öryggissveit and Vélfærafræði sporðdreka really do carry the Charges Security team and Roboscorpion carry, the card sheet included. The committed artwork for `PS015` and `PS018` shows no CHARGE panel and is *stale*: it was read once as evidence that neither card has a Charge, and the Charges were taken off the blueprint on the strength of it. They were put back. Where a card's artwork and the card sheet disagree, the sheet is the newer of the two - and the artwork is deleted rather than left to be read again.

**No card's printed strength counts the Alerts standing, and that is arithmetic rather than transcription.** `App\Support\Runs\ChallengeStrength` adds `AlertSchedule::strengthBonus()` to *every* card, so a card whose own strength reads "Number of alerts+2" has the Alerts counted twice — once in the number Security types into the challenge form, and again in the curve underneath it. Security shutter (`PS006`) and Öryggisluggari (`PS017`) were transcribed that way, and both are now a flat `Brute/Hack (2)` — the card sheet had flattened them to 4 and the designer has since rebalanced them to 2, which is the floor the old formula had anyway at nought Alerts standing. Öryggisluggari moves with Security shutter because ANT's five are identical pair for pair, above. `ProtectionCardBlueprintTest::test_no_printed_strength_counts_the_alerts_standing` sweeps the whole list rather than the two codes, because the next card to do it would be as wrong and as quiet. A formula counting something the Run does *not* already track — wounds, runners, cards underneath, times encountered — is fine and several cards carry one.

**A challenge is the sentence the card prints, not a skill and a number.** Most cards are a plain `Brute (6)`, but `Brute/Hack (2)` lets the Runners choose, `Hack (4+N) - where N is the number of cards underneath this` is not known until the card is met, and `Hack (4), followed by Brute (4)` is two challenges on one card. There is no parsed skill or strength column: the words are stored and shown, and the table converts them, which is what it does with the card in hand anyway. Runs will need to read the text.

**Availability is read off the code prefix, and that is a reading rather than a rule.** The card sheet has no availability column. `PS` and `PE` cards are the ones the card sheet prices and the briefings hand out; every `PR` and `PX` card is the target of some technology's `Unlock:` effect, so they are seeded research-only. Three (`PR001`, `PR011`, `PR013`) are unlocked by no technology in the list at all, so Control has to add the research or open them up by hand. `ProtectionCardBlueprint` says all of this in one place instead of deriving it at runtime.

**No card carries a shop price, deliberately.** The card sheet has a cost column, but the Corporation shop and the Runners' market do not work the way it suggests, so seeding those numbers would encode a pricing model the game does not use — and a wrong price is worse than none, because the shop would be built on it. `cost` stays a nullable column that Control can fill in for a one-off ruling, and the price a card actually sells at is a **shop listing** — per game, set by Control, and nothing to do with the catalogue. See The shop, below. A Charge is unaffected: its cost is Credits Security spends during a Run (3.3.5), printed on the card and nothing to do with buying one, so it is seeded. Research costs are unaffected too — those are Research Points, not Credits.

**Cards are found by code, and a card with no artwork is normal.** `App\Support\CardImage` resolves `public/images/cards/<CODE>.{webp,png,jpg}` and answers null when there is nothing there. Control invents cards mid-game — the rulebook has research proposals priced and added to the tree during play, and DTC's "Unfortunate Malfunction" hands out a bypass card named after whichever Protection Card it counters — and those have never been printed. The `CardFace` React component shows artwork where there is any and a card-shaped box of the card's own text where there is not, so the text box is the other normal case rather than a fallback.

**The card sheet is the source of a card's text, and artwork that disagrees with it is deleted.** The sheet is maintained; the faces were rendered once, and six of them print something the sheet no longer says — `PR010` Anzû a three-part consequence where the sheet has one line, `PE004` Giant and `PR002` Nachtkrapp a longer Charge, `PS015` and `PS018` no CHARGE panel at all where the sheet gives both a Charge, and `PS006` Security shutter an Alert-scaled strength that is now a flat 2. Deleting is safe precisely because a card with no artwork is a normal card: `CardFace` draws its text instead, and the text is the thing that was right. Do not re-derive a card's rules from its picture — that reading is what put the application and the card list out of step, and every remaining face was checked against the sheet rather than trusted.

**A research card has two faces, filed `<CODE>_F` and `<CODE>_B`.** A technology is printed proposal side up and flipped over once researched (3.2.2), and both faces are public — a technology is not secret, only which Facility is storing it is. The two need no columns: they are the fields the application already has, split the way the card splits them, with the front the name, description, suit costs, prerequisites and required Facility type and the back the effect with its copy and destroy strengths. A single-sided card resolves from `<CODE>_F` or from the bare `<CODE>`; the back has no such fallback, because an unsuffixed file is the front of a one-sided card and treating it as a back would have every card offering a flip that showed the same picture twice. Either face may be the one that is missing — sixteen technologies have a back and no front — so anything showing a card takes whichever it has.

**The three families are not the same shape, and a card is the size of a card.** Equipment is printed portrait at 600×817 and both the Protection and research cards landscape at 600×440, so no single aspect ratio may be imposed across all three — forcing one crops two thirds of the game. What `CardFace` takes instead is the family's shape, from the call site, and it holds *both* faces to it: a card shown as its own text is the same size as the artwork beside it, rather than as tall as its words, so a list lines up whether or not the artwork has been drawn. That means a wordy text card can outrun its box, which is why the words fade at the bottom rather than being cut, why the availability line sits below the fade, and why every card — not just the ones with artwork — carries its full text in a tooltip and in the page for a screen reader. The table thumbnail is a different problem and is held to a height instead, so a landscape and a portrait card still line their rows up. `CardFace`'s footer is part of that text rather than a caption, so it is drawn only on a card being shown as its words — anything that has to be legible on the artwork as well lives outside `CardFace` and on top of it, which is what the `×N` copy count on a card in Security's hand is. Card artwork lives under `public/` because it is referenced from an `img` tag at runtime and the page is served by Laravel — the opposite of the icon font below.

**The artwork directory is read once per request, not once per card.** Three hundred cards against four extensions is the better part of a thousand `stat` calls a page, and worst on a checkout with no artwork at all. `CardImage` lists the directory once and answers from memory, which also makes lower-case file names resolve and lets a `webp` supersede a `png` by extension rather than by directory order. Tests that write artwork must call `CardImage::flush()` — `TestCase` does it for every test — and must never write over a real code: the game's artwork is committed, so a test cleaning up after itself would delete it. One did.

**Every player reads the Protection Card and Equipment lists at `/cards`**, and that is the designer's ruling. The shop still keeps the Protection Card *counter* to the Corporate seats, but the printed list is not what 3.4.2 keeps Secret: which cards stand in which Facility is, and so is how many. So `GamePresenter::publicCardList()` sends the cards as printed and leaves off `installed_count` — copies standing across the game's Facilities is reconnaissance by arithmetic — and Control's `notes`. Technologies are not on it at all: a tech tree is its Corporation's, on `/research`. It is in the sidebar for anybody signed in, a seat or no seat, as the Facility list is.

**Owning copies is modelled; buying them is not.** `protection_card_holdings` is a count per Corporation per card, and it is what caps how far a card stretches: one copy per Facility, so four copies of Security Team defend four Facilities and no more. `FacilityDefenceService::install()` is the only place a copy leaves a hand and `remove()` the only place one comes back — a copy in a Facility is a row in `facility_protection_cards`, so the hand plus the installed copies is still the briefing's count. Installing with none left is refused; Control raises the count first.

Control sets any count outright via `ProtectionCardHoldingController`. Auctions, research grants and Security players trading between themselves all happen at the table, so the application records where a count ended up rather than replaying how it got there. The one route to a copy that *is* modelled is buying one — see The shop, below, which goes through `FacilityDefenceService::giveCopy` rather than writing the holding itself.

**Starting cards are drawn from what a Corporation actually holds.** `installed_in_each` in `config/running_hot.php` says how many of each kind a starting Facility opens with (one each), and `CreateDefaultFacilities` picks the card the Corporation has most copies of that the Facility does not already have. That is what makes ANT's Facilities open with ANT's own cards, and it spreads the load — a Corporation holds four copies of its commonest card against five Facilities, so no single card can cover them all. Running out is not an error: a Facility simply opens thinner.

**Technologies are catalogue only.** A technology carries its effect as printed text, because the research game that would act on it is unbuilt, along with its four suit costs, its prerequisites as printed titles (Control may add a technology others already name), a required Facility type, and its copy and destroy strengths.

**Equipment is not, any more.** `equipment_holdings` is a count per **Character** per card, and per Character is what the rulebook says twice: 3.4.1 caps *you* at three equipped permanent items and one copy of each by title, and 3.4.2 hands *your* permanent Equipment to the Security player when you are carried out. Neither sentence means anything about a shared pile, so a gang's kit is four or five separate hands. The effects themselves are still printed text — `RollModifiers` is what the player holding the card says it grants this roll, rather than seventy-four effects parsed into a little language that a card Control invents mid-game would fall straight out of.

**`EquipmentService` is the one writer of that table**, the way `FacilityDefenceService` is for a Corporation's Protection Cards. It was `RunEngine` alone, because a run spending a consumable and a carried-out Runner losing their permanents were the only two ways a count could move; Control handing a card out is the third and happens nowhere near a run. The run loop asks the service rather than a Control route growing its own copy of the write.

None of it is a Tracker. A Tracker is a number the game moves and argues about afterwards, which is why every one of those leaves a `tracker_adjustments` row; how many Shivs somebody is carrying is a holding, and a holding records where a count ended up rather than replaying how it got there.

**Control hands cards out at `/control/games/{game}/cards`**, under the Equipment list rather than on a page of its own — that is where Control is already looking when somebody asks for a card. The rest of that page is still a card list to read: the Protection Card catalogue is edited on the Facility Defence page instead, beside installing.

**Anybody on the roster may be handed one.** A Corporate seat was refused outright, on the reasoning that a CEO with a Katana in hand is a row nothing reads — and it turned out to be in the way of the thing it was protecting. 2.1 says a Runner "may buy equipment, either from the market or from other players", so a card reaches a Facility by way of whoever was holding it, and that is as likely to be a CEO who bought it to hand over as a gangmate. Who may hold what is Control's call, which means the refusal was the application making a ruling the rulebook does not. So `equipment_holdings` takes any character, `/equipment` is offered to anybody holding a seat, and `GamePresenter::equipmentHoldings()` groups by team rather than by gang — the gangs first, then the Corporations, then everybody in neither.

**Giving and setting are two writes, because they answer different questions.** `EquipmentService::giveCopies()` *adds*, and is what Control reaches for at the table: it knows what it is handing over and not what is already in the hand, so a give that set the count would quietly take away the two Shivs somebody was carrying. `setCopiesInHand()` replaces it, which is the correction — a card spent, a haul split, a number typed wrong.

**A card is given by clicking it**, in the card list itself rather than through a form beside it. The gesture is the card because the card is what is being asked for at the table: somebody wants the Katana, the list's own search has already narrowed to it, and it is on screen under Control's pointer — so picking it out of a second list of seventy-four was work the page had already done. What the dialog still asks for is who, which stays a `SearchPicker` for the reason the shop's is a combobox: a roster of forty is a list nobody finds anybody in as a native `select`. Setting a count stays where it corrects, on the hand.

**Both catalogues take that gesture, and the one difference is the rulebook's.** An Equipment card goes to a *person* (3.4.1 caps and takes it away per player); a Protection Card goes to a *Corporation*, because 3.3.4 makes those copies the Corporation's rather than the Security player's. So the picker holds the roster in one list and the Corporations in the other, `GamePresenter::equipmentRecipients()` and `protectionCardRecipients()` are the two lists, and `FacilityDefenceService::giveCopies()` is the Protection Card write — `EquipmentService::giveCopies()`'s counterpart, adding where `setCopiesInHand()` replaces, and now what `giveCopy()` is one copy of. Correcting a Protection Card count outright stays on the Facility Defence page, beside the stacks the count feeds: the card list is where a card is handed over, and that page is where one is argued about.

**Players hand cards to each other, and that is 2.1's own second half.** "You may buy equipment, either from the market or from other players" — the market is the shop's counter, and the other players are `POST /equipment/give`. It is the one thing on `/equipment` that is not read-only.

**Only the card moves, and there is deliberately no price box.** What came back — Credits, a favour, a share of the next job — is settled at the table, for the reason a research point trade settles there: 3.2.5 has players trading "by passing over the requisite tokens", so a transfer is one-way and one-sided, one hand goes down and the other goes up. That one-sidedness is also what makes it safe to hand a player at all. Giving spends only what is yours; a transfer that *also* took the recipient's Credits would be one player reaching into another's purse on the strength of a price only the giver had typed in. If a price ever does have to travel, it needs the other player's consent first, and that is a second act rather than a wider form.

**`EquipmentService::transfer()` is the write**, in a transaction with the giver's row locked, because two copies given away at once out of the same hand is exactly what a double-clicked button is. The row is left at nought rather than deleted, as `setCopiesInHand` leaves one — **and the page does not draw it**: a hand holds what it holds, and a card face with a `×0` on it says somebody is carrying something they handed over. `GamePresenter::equipmentHoldings()` filters the empty rows out, so the row goes on recording where the count ended up without the hand claiming it.

**`CharacterPolicy::giveEquipment` is who**, and it is `ShopListingPolicy`'s division again: the seat and the claim are the policy's, and whether there are copies to give at all is the service's. The seat has to be *yours*, because giving spends what is in that hand — but who may **receive** is not asked at all, since 2.1 names no restriction on the far side and who may hold a card is Control's call. Control trades out of anybody's hand.

**There is deliberately no *phase* clock on a trade**, and that is where it parts company with the shop. The shop is a counter Control opens and shuts and 3.3.3 says so in as many words, but handing a card to somebody is two players agreeing in a Discord channel, and the channels are open all turn. 2.1 listing it under the Setup Phase describes when the *market* runs rather than forbidding a Runner from passing a Shiv across during the Action phase — and a refusal there would only have taught people to phone Control instead, which is the shape of rule this application keeps deciding against. The one thing the run loop needs is that a card already spent is gone from the hand, and the service's own count holds that.

**The game's own clock is asked about**, which is *A game off the clock* below drawing its line in the usual place: a seat is a fact about the roster and acting is a question about the clock. Reading a hand is why `/equipment` opens either side of the evening — the sidebar offers it to a CEO in a game that has not started — and handing a card over is an act, so `giveEquipment` asks `isRunning()` where the act is, exactly as `CouncilSessionPolicy::vote` does beside `hasSeat()`.

**And it is the same gesture on `/equipment`: click the card you are handing over.** Which seat is giving and which card is going are both answered by *which card you clicked*, on a page where a player may hold two seats and every card is already laid out to be read. `GiveCardDialog` is the one implementation, shared with Control's list above. A card in a hand nobody may give out of — somebody else's, or your own before the game is running — is drawn as the same card with no button around it, rather than a control that does nothing when pressed. The trigger is a real button wrapping the face rather than a click handler on it, so the keyboard reaches it, and it is the only tab stop on a card because `CardFace`'s own tooltip is deliberately not focusable.

Its refusal is kept on the dialog for the reason the run screen's `useRunAction` keeps its own: a page draws a great many of these and they all report against the same handful of keys, so a page-level `errors` would put one card's refusal under every card on screen. The copies box carries no `max`, for the reason the run screen's top-up learned not to: a browser-side constraint that blocks the submit outright makes a refusal look like a dead button, so the server is what says how many are there.

Everything else is still a conversation: splitting a haul and an auction end with Control writing down where the count ended up.

**Every Runner opens the game carrying what their briefing prints**, seeded by `SeedEquipmentHoldings` from per-Runner lists in `config/running_hot.php` — per Runner because the briefings are one document per player, so there is deliberately no gang-level list to be mis-keyed against somebody else. Two readings are worth knowing. A briefing's **"Ability" section is a card too**: what is printed under it is the effect text of `EEP014`–`EEP016`, the three Reconnaissance cards, reproduced almost word for word, so Ballet, Bitter and Z3R0 are seeded as the cards they are. And a **Freelancer carrying nothing is the right answer**, not an unfinished one — all three are given "Special rules" in place of a kit, and none of those is an Equipment card.

The seeder **skips a code it cannot find**, which is right when Control has deleted a card and wrong when somebody has fat-fingered a digit — and the two are indistinguishable at run time, so a Runner would simply open the game one card lighter than their briefing. `EquipmentSeedingTest` checks the configuration against the catalogue for exactly that, rather than transcribing the eighteen kits a second time where they would agree with themselves instead of with the briefings.

**Players read their own hand at `/equipment`, and the tier line is a ruling rather than a reading.** You see the seats you have claimed and nobody else; Control sees everybody. The rulebook does *not* make a hand Secret the way 3.4.2 makes a Facility's stack — this is here because a gang reading each other's kit off a screen is a gang that never has the conversation, and at the table you would have to ask. `GamePresenter::equipmentHoldings()` takes an optional viewer and is the only thing that decides: passing nobody is the Control panel's whole-game view, passing a player narrows to their own seats, and passing Control widens again. One implementation, so the page filters nothing and a hand that is not yours never reaches the browser.

It is its own page rather than a corner of the dashboard, because a hand is what you work from: choosing three permanent items to equip means laying the cards out and reading them, so they are drawn as `CardFace`s grouped by category. The `×N` copy count sits *outside* `CardFace` and on top of it, for the reason the defence board's does — it has to stay legible over artwork as well as over the text box. The team band is drawn only for Control: a player holding one Runner already knows which gang they are in, and it is what makes forty hands readable.

One thing on it is not read-only, and it is **handing a card to another player** (2.1) — see above. **Splitting a haul and an auction stay conversations at the table**, so Control sets the count for those on their own panel. Buying from the market does not — see The shop, below.

**Technology trees attach to Corporations late.** A game is created before its roster exists, so `SeedTechnologies` writes every technology unattached and fills in `corporation_id` on a second run, after `CreateDefaultRoster`. `CreateDefaultFacilities` makes that second call. Note the codes do not identify the tree reliably — Gordon and Genetic Equity both take a `G` — so the tree comes from the sheet's own column.

**Not modelled, deliberately:** auctions, research grants, and trading copies between Security players. Each is a conversation with Control, who then sets the count.

## The shop

Where cards come from (rulebook 3.3.3 for the Corporation shop, and 2.1 for the
Runners' market). Control puts cards out at a price, players buy them, and the
counts land in the holdings that were already there.

**A price is a line in a game's shop, not a column on a card.** The card sheet
has a cost column and the application still deliberately does not seed it: the
shop does not price cards the way that column suggests, and a wrong price in the
catalogue would be worse than none because everything downstream would be built
on it. So `shop_listings` is per game, Control writes every number on it, and
the same card can go for five Credits on Saturday and twelve on Sunday without
either game touching the other.

**Two counters, and they are different transactions rather than one with a
parameter.** A Protection Card is bought by a Security player, out of the
*Corporation's* Credits, into the Corporation's hand — 3.3.3 puts the list in
Security's hands and 3.3.4 makes the copies the Corporation's. An Equipment card
is bought by a Runner or Freelancer, out of *their own* Credits, into their own
hand, because 3.4.1 caps and takes away equipment per person. Which of the two
applies is read off the listing and nowhere else, so no caller ever has to say.
That is why `shop_listings.stockable` is a morph rather than two nullable
columns, the reasoning `council_ballots.voter` already follows.

**The shop writes no holding itself.** A copy reaches a Corporation through
`FacilityDefenceService::giveCopy` and a Runner through
`EquipmentService::giveCopy`, because each is the one writer of its own table,
and Credits move through `TrackerService` like every other number the game
argues about. A shop that grew its own copy of either write would be a second
place a count could move.

**Rumoured is a line, not an absent one**, and it is the whole reason
`ShopListingStatus` is not a boolean. 3.3.3 hands Security a list in three
parts — available now, "rumoured to be in progress", and research-only — and a
card nobody can buy yet is still something Security plans around. "Hold your
Credits, the Angel lands next turn" is the decision the list exists to let them
make, so a shop that left it out would be a shop that told them nothing. Nothing
moves a line onto sale on its own: 3.3.3 has Control announcing it.

**Any card can be put out, a research-only one included.** 3.3.3 says those
"will not be available for general sale", and that was enforced here until it
got in the way of the thing it was protecting: the shop is how Control hands a
card over at a price, and a card the tree was meant to unlock is exactly the
sort of thing that gets sold once in a game because the table went somewhere
interesting. Control always wins, and a rule the organisers have to go and edit
a catalogue to get round is a rule fighting them. So the sentence is read as
being about the ordinary run of the game rather than about what Control may do.
The card's own `availability` still travels to the panel and the picker says
"research only" beside it, so a line Control might not have meant to put out
reads as unusual rather than being silently missing.

**Withdrawn is how a line leaves without taking its sales with it.** A listing
that has been bought from cannot be deleted, because the purchases hanging off
it are how Control answers "where did that card come from?" three turns later.
An unsold line deletes freely, which is what a typo wants.

**Stock is a count, and no count is a line that never runs out.** Null and nought
are different answers and the distinction is load-bearing: nought is sold out,
null is Control saying the shop has as many of these as anybody wants. Which is
why Control's form reads a blank box as null rather than through
`$request->integer()`, which would turn every unlimited line into a sold-out
one. "First come first served" is the only allocation rule the rulebook gives,
so the count is all the shop needs — there is deliberately no per-buyer limit,
because there is none in the book.

**...and first come first served is a row lock.** `ShopService::buy` locks the
listing for the length of the sale, because two Security players reaching for
the last Angel at the same moment is exactly the case 3.3.3 is answering, and
without it they would both get one.

**`shop_purchases` is how the stock count stays believable.** The count says
where the shelf ended up; the rows say how it got there, which is the division
`tracker_adjustments` already draws. It is not a duplicate of that ledger: a card
given away at nought Credits moves no tracker and so leaves no ledger row at all.
Two people are on a purchase and they are different questions — the character is
who stood at the counter, the corporation is whose Credits paid — and the price
is kept on the row rather than read back off the line, because Control raising a
price next turn must not rewrite what was paid last turn.

**A refund moves all three together or none of them.** Credits back, copy back,
shelf back up — the counterpart of unscoring an equation, and there for the same
reason: a shop run live will sell somebody the wrong card. It is refused when the
copy is not in hand to give back, which a Protection Card standing in a Facility
is not. That refusal is better than a refund that quietly leaves the Corporation
a card up; Control takes it off the stack first.

**The clock is a policy question, which is what lets Control through it.** "The
Corporation shop will be open during the Setup Phase" (3.3.3), and 2.1 puts the
market there too — so `ShopListingPolicy` asks about the phase, the seat and the
claim, and `before()` hands Control the lot. A player will phone a purchase in or
turn up at the desk between phases, and none of that can wait for the clock to
come round again. What `ShopService` refuses, though, it refuses to Control as
well: the market bills the buyer's *own* Credits and a Corporate seat has none —
they spend their Corporation's. That is the whole of why a CEO cannot buy from
it, and it is no longer a rule about who may *hold* an Equipment card: 2.1 has
Runners buying equipment "from other players", so anybody may hold one and
Control hands it over on the card list page instead.

**Which counter you see is the seat you hold.** 3.3.3 hands the Protection Card
list to the Security players, so it goes to the Corporate seats; the market is
the Runners' — and the other half of 2.1's sentence, one player buying from
another, is on `/equipment` rather than here, because it spends out of a hand
rather than off a shelf. A price list is not one of the things 3.4.2 keeps Secret, but
handing every Runner a catalogue of the cards they are about to meet is
reconnaissance the rulebook makes them pay for, so `ShopPresenter` draws the same
line the rulebook does. Inside a Corporation the CEO and the Research player read
the list and only Security buys, which is `FacilityPolicy::defend`'s boundary
again and the same reasoning: a purse two people can spend out of is a purse
neither can plan with.

**The page polls**, for the reason the research table and the Chamber do. Control
announces a card mid-phase, a rumoured line comes on sale, and somebody else
takes the last Angel — none of it anything the reader's browser did, and first
come first served is not a rule you can play to against a stale page.

**Two catalogues of eighty-odd cards need searching, so the picker is a
combobox.** A native `select` holding 83 Protection Cards is a list nobody finds
anything in, and worst of all on a phone — which is where half of this game is
played. `resources/js/components/search-picker.tsx` is the shadcn combobox
(`cmdk` inside a Radix popover, which is what those two dependencies are for),
and it is deliberately generic rather than a card picker: Control also picks a
character to sell to out of a roster of forty-odd, and that is the same control
with different words in it. It matches a **plain substring** over a `search`
string the option supplies, rather than cmdk's own fuzzy scoring, because the
useful terms are not always the ones on screen — a card is looked up by its
printed code as often as by its name, and a Runner as often by their gang.

**The lists themselves are filtered rather than paged**, by
`shop-filter.tsx`, which both the players' counters and Control's list share so
they cannot drift on what searching means. It always shows a count, since a
search that matches nothing and a shop that is empty look identical without one.

**The picker draws the card before it is priced.** Pricing a card you cannot
see is guesswork, and the thing somebody at the table will be holding is the
artwork — so choosing one puts a `CardFace` on the form, above the price and
stock boxes rather than after the line is on the list. Which means
`ShopPresenter::unlistedProtection()` and `unlistedEquipment()` shape their
cards through the same `protectionCard()` / `equipmentCard()` the listings use
rather than a thinner list of their own: a second shape would be one more place
for the two to disagree about what a card is. A card with no artwork draws its
own words, which for one Control invented mid-game is the normal case rather
than a failure.

**Neither control hides itself, and both learned that the hard way.** The filter
had a threshold — it appeared only past eight lines, on the reasoning that a
shop with four things on it does not need searching — and the first thing that
happened was somebody setting a shop up, looking for the search they had asked
for, and not finding it. The picker had the matching problem from the other end:
its trigger is a button, which reads as an ordinary select, and nobody clicks a
select expecting to type. So the threshold is gone, the trigger carries a
magnifier until something is picked, and its placeholder says how many there are
to search. A control that hides until it is "needed" is a control nobody knows
is there.

**`w-[var(--radix-popover-trigger-width)]`, not `w-[--radix-popover-trigger-width]`.**
The bare form is Tailwind 3 shorthand for a CSS variable; this project is on
Tailwind 4, which emits it as invalid CSS — so the popover silently sized to its
content instead of matching the trigger. It is the kind of mistake that survives
`tsc`, eslint and the whole test suite, and the tell is the built stylesheet:
grep `public/build/assets/*.css` for the variable name and it is simply absent.
`ui/sidebar.tsx` uses the `w-(--sidebar-width)` form and `ui/select.tsx` the
`[var(--…)]` one; either is fine, the bare one is not.

**Adding those two components with the shadcn CLI needs watching.** It pulled in
`cn` and `radix-ui` — an unrelated utility package and the umbrella Radix
build — where this repo uses `@/lib/utils` and individual `@radix-ui/react-*`,
and it silently rewrote `ui/dialog.tsx` to a newer shadcn layout that imports
from both. Only `cmdk` and `@radix-ui/react-popover` were wanted; `dialog.tsx`
was reverted and `command.tsx`'s `CommandDialog` removed, since nothing here
wants a command palette and keeping it would couple the file to whichever
dialog version happens to be in tree. `resources/js/components/ui/*` is
prettier-ignored, so those two files keep shadcn's own formatting like the rest
of the kit.

**Auctions are not modelled, and that is a decision.** "Control may also decide
to auction Protection Cards - in those cases the Security player who pays the
most will receive a copy" happens in the room, and what the application wants
afterwards is the result: Control moves the Credits with the tracker controls and
raises the holding on the card page, both of which already exist. A bidding UI is
a worse version of a conversation with everyone in front of you.

| Concern | Location |
|---|---|
| The list, the price, the stock and the exchange | `App\Services\ShopService` |
| A line, and whether a copy could be sold at all | `App\Models\ShopListing`, `App\Enums\ShopListingStatus` |
| What has left the shop | `App\Models\ShopPurchase` |
| Who may buy, and when | `App\Policies\ShopListingPolicy` |
| What each counter looks like to whoever is at it | `App\Support\ShopPresenter` |
| The counters players shop from | `App\Http\Controllers\ShopController`, `resources/js/pages/shop.tsx` |
| Searching a catalogue, and filtering a list | `resources/js/components/search-picker.tsx`, `shop-filter.tsx` |
| Control announcing the list | `App\Http\Controllers\Control\ShopController`, `resources/js/pages/control/games/shop.tsx` |

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

**A marking is written four ways, because the tile it is drawn on is 56 pixels
wide.** `CardMarking` answers with the printed words (`label`), what they do
(`description`), the word the tile has room for (`shortLabel`) and the icon of
the suit it names (`glyph`) — and `ResearchPresenter` sends all four. "Other side
must be Cog" truncated on a card tile loses the suit at the end of it, which is
the only part a player cannot work out for themselves, so the tile draws "Other"
and the Cog icon and the tooltip says the sentence. A marking naming no suit has
no glyph rather than a blank one. The full wording stays in the card button's
`aria-label` rather than being left to the tooltip: a tooltip is only in the page
while it is open, so a keyboard user tabbing a hand would otherwise reach a card
with nothing said about what it may not do.

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

## The front door, and the theme

**There is no landing page.** Everybody who opens this application signs in — a
player to their seats, Control to its panel — so a page describing the game to
somebody already here to play it was a door nobody wanted to be shown. `/` is a
redirect instead: to `login` for a visitor, to `dashboard` for somebody already
signed in, so the one URL anybody types lands them where they were going rather
than making them click through. The route keeps the name `home`, because logging
out, deleting an account and asking for a fresh verification mail all redirect
to it and Fortify's own `home` config is a separate thing pointing at
`/dashboard`.

Which makes the **login page the first thing anybody sees of the game**, so it is
dressed as the game: `AuthSimpleLayout` throws the logo's own glow onto the page
behind it and puts the form in a panel. The mark there is deliberately not a
link any more — it used to point at `home`, which now redirects straight back to
the form it is sitting on.

**The login page offers Discord and nothing else**, unless
`RUNNING_HOT_DIRECT_LOGIN` says otherwise. A seat is claimed by Discord handle,
so an account made any other way is an account holding nothing — the player
signs in, lands on a dashboard with no characters on it, and reads that as the
application being broken rather than as the queue it is. The email form, the
sign-up link and the passkey button are all behind that one flag, and the page
says **"Confirm with Control that you are set up before logging in!"** above the
buttons either way, because after you have signed in is too late to be told.

It is on in `.env.example` and off in `config/running_hot.php`, which is
deliberate in both directions: a fresh checkout can use the password logins
`DemoGameSeeder` prints, and a deployment that never sets it gets the
Discord-only page. The heading follows the state through `setLayoutProps` —
telling somebody to enter a password above a page with no password box is how
people end up hunting for a form that is not there.

**And `POST /login` is refused, not merely unlinked.** A hidden form whose
endpoint still takes credentials is a signpost pretending to be a lock.
`App\Actions\Fortify\EnsureDirectLoginIsEnabled` is the first pipe in
Fortify's login pipeline, and it is a *pipe* rather than a check inside
`Fortify::authenticateUsing()` for a specific reason: that callback replaces the
credential check outright, so using it would mean reimplementing password
verification, remember-me and rehashing here in order to refuse one case.
Refusing early and letting Fortify's own actions do the work is the whole point.
The refusal names Discord, because a bare "these credentials do not match our
records" has somebody retyping a password that was never going to be looked at.

The pipeline is installed with `Fortify::authenticateThrough()`, which is
evaluated per request — that is what lets the setting be changed in a test
without rebooting the application. Everything after the first pipe is Fortify's
own default list, reproduced; if Fortify ever gains a pipe it has to be added
there too, and `AuthenticationTest` is what would notice.

**The suite runs on the shipped default, which is off.** Only three test files
actually post to the login route — `AuthenticationTest`, `TwoFactorChallengeTest`
and `DemoGameSeederTest` — and each turns the setting back on in its own
`setUp()`. That is deliberate rather than convenient: it means the other
eleven hundred tests prove the application works in the posture a deployment
ships with, and `actingAs()` does not go near the route anyway.

**What is still open is the passkey endpoint.** The button is hidden with the
form, but `POST /passkeys/login` has no callback hook of Fortify's to hang a
refusal on, so it would need a middleware. It is the lesser case — a passkey
only exists if that user registered one while signed in, so it is not a way in
for somebody Control has never set up — but it is not closed, and turning
Fortify's features off instead is still the wrong lever for the Wayfinder
reason above.

**And there is a way back in for whoever the handle failed.** A seat is
reserved against a Discord handle, and that is wrong often enough to matter:
Control types them off a sign-up list, people rename themselves between signing
up and turning up, and a handle Control never got leaves somebody signing in to
a dashboard with no characters on it — which reads as the application being
broken. `characters.email` is the second claim ticket, and `/claim` is where a
player redeems it: name the address you signed up with, and the seat is bound
to the Discord account that comes back.

**It works from both sides of the sign in, because both cases are real.** A
visitor names their address and is sent through the Discord sign in that
already exists; somebody *already signed in* — because signing in worked, it
just found them nothing — is bound on the spot, since there is no reason to
send them round through Discord to learn what the session already knows. So the
route sits in neither the `guest` nor the `auth` group: either one would shut
the door on half the people it exists for.

**The address rides the session, not the character.** `CharacterClaimController::PENDING`
holds the address across the OAuth round trip and `DiscordController` redeems
it, and it is looked up *again* at the far end rather than resolved once on the
way out — what Control edits between one step and the next is the roster, so a
correction made in the meantime is picked up. It is `pull`ed rather than read,
because an address that found nothing must not sit in the session waiting to
fire on a later sign in.

**The claim writes the handle on as well as the `user_id`.** The id is the
permanent binding; the handle goes on so the Control panel shows the seat as
linked and every later sign in claims it the ordinary way. Only if the sign in
brought one, though — a password account has none, and writing null over what
Control typed would throw it away.

**The address proves nothing, and that is a decision rather than an oversight.**
Anybody who knows a player's email can link that seat to their own Discord
account. Two things hold it down: a claim only ever takes a seat with no
`user_id`, so it can never take one somebody already holds, and Control can
release a character from the panel, which is the existing undo. A one-time code
to the address was the alternative and was turned down — `MAIL_MAILER` is `log`
in `.env.example`, so it would have meant depending on SMTP being right on the
night, and if it were not then the people who could not self-serve would be
exactly the people this exists for. If that trade ever stops being acceptable,
the code goes between `store()` and the redirect to Discord and nothing else
moves.

**Two characters in one game may not share an address.** A claim takes *every*
unheld seat on one, so a duplicate would hand whoever got there first both of
them. It is the same bound the Discord handle already carries and for the same
reason; a player genuinely on two seats in one game takes the second by handle.
Across games it is fine, because a different game is a different roster.

**Control seats are deliberately not in this.** `control_members` goes on
claiming by handle alone: an organiser whose handle was mistyped is fixed by
another organiser editing the Control team, and the same failure does not leave
them locked out of a game they are playing.

**The refusals name which case you are in** rather than failing blankly —
nobody reserved for that address, every seat on it already claimed, or you
already hold them all. That does leak whether an address is on a roster, which
is consistent with the trust the flow already extends and is not worth being
vague about when the alternative is a player who cannot tell whether they typed
it wrong.

**Dark is the default, and "follow the system" is still a setting.** The game is
played in the evening and the application is themed off a neon sign, so dark is
the design rather than a preference — `system` as a default put half the table
on a white screen. The default is written in *three* places that have to agree —
`HandleAppearance`, the `@class` on the `html` element and the inline script
above it — because a server painting one theme and the client swapping to the
other is the exact flash that inline script exists to prevent. `AppearanceTest`
pins all three.

The three-way choice stays on the settings page; the sidebar gets a one-click
`AppearanceToggle` instead, because "follow the system" is a preference you set
once and "the lights just went up" is a thing that happens mid-game. It reads
`resolvedAppearance` rather than `appearance`, so somebody on `system` at night
is offered light — the question is what they are looking at, not what they once
chose.

**The sidebar draws the seats a player holds, not every page the game has.**
`App\Support\Navigation` decides it server-side from the characters they have
claimed, the way `GamePresenter` decides which tier of the Facility board they
get, and shares it as a `nav` prop. A plain shared prop rather than
`Inertia::always()`, which is right here for once: the list only changes when
Control seats somebody, a partial reload leaves the client holding what it had,
and the query never runs on a poll. `undefined` therefore means "a poll did not
re-send it" rather than "none", so the sidebar falls back to drawing everything
rather than blanking itself mid-poll.

Dashboard and Facilities are everybody's — the Facility list is posted in a
Discord channel the whole game reads, and a Runner picks their target off it.
Everything else follows the seat: Runs and Shop to both sides, Research to any
Corporate seat, Council to a CEO or to a character Control has written
`council_votes` on. Equipment goes to anybody holding a seat at all, since 2.1
has Runners buying equipment from other players and a Corporate seat may be
holding the card it bought to hand over — and to nobody holding none, who has no
hand to read. Control gets the lot, as everywhere.

**Research is narrower than the rest of this file describes it**, and that is
the designer's ruling rather than a reading. The table's public tier is called
"everybody's" in the research section above; a Runner is no longer offered the
link to it. Hiding a link is not closing a door, though — the page still
filters its own payload, so somebody typing `/research` gets what they always
got. That is a curiosity; a link nobody can use is a bug.

**The Council went further, and is properly closed.** 3.1.3 hands blank agenda
cards to "players" rather than to "CEOs", and this application read that
literally at first: any character in a running game could open the Chamber and
write one. The designer's ruling is that a seat is what it takes, and that
somebody without one who wants an agenda raised has to convince somebody who
has one — which is the conversation the Council is for. So the Chamber returns
403 without a seat, `AgendaCardPolicy::create` asks for one, and the sidebar
link follows.

**A seat is one predicate, asked in four places.**
`Character::scopeOnTheCouncil` is it: a CEO, whose vote is their Corporation's
Political Will, or anybody Control has written a `council_votes` bloc on.
`CouncilService::hasSeat()` wraps it, and the Chamber, `CouncilSessionPolicy::vote`,
`AgendaCardPolicy::create` and `Navigation` all ask *that* rather than keeping
four copies — `vote` used to carry its own and no longer does. Control is
deliberately not in the predicate: the override belongs in the policies'
`before()`, and a service that answered "yes, Control" would put it in two
places.

Note the instance method `Character::sitsOnCouncil()` is the *narrower*
question and stays that way — it asks about the `council_votes` kind alone,
because a CEO has no bloc of their own and never should.

**Holding a seat is not the same as signing with it.** A player holding both a
CEO chair and a Runner may raise an agenda, but as the CEO:
`StoreAgendaCardRequest` checks the *character* named on the card, because
otherwise the Chair is handed a card from somebody who is not in the room. The
policy asks whether this user may write one at all; the request asks which of
their seats is signing it.

**The palette is sampled off the logo rather than chosen.** The neon tube in
`public/images/running-hot.webp` sits at hue 33–40 with a chroma of about 0.24
and its ground at a lightness of 0.067, which is where `--primary` and the dark
theme's background come from. Three things about that are worth not undoing:

- **Every value was checked against the sRGB gamut, and every readable pair
  against WCAG AA.** An oklch outside the gamut is clipped *silently*, so the
  colour that ships is not the one in the file and nothing says so. The tight
  pairs are the light theme's `--primary` (white label on it, 4.71) and its
  `--muted-foreground` (5.79) — raising the lightness of either is what breaks
  them.
- **`--destructive` moved to hue 14–16, away from the usual 27.** The primary is
  now an orange-red, and a Delete button the same colour as the Log in button is
  a Delete button nobody sees coming. It still reads unmistakably red; it is
  just on the other side of the tube.
- **The inline `<style>` in `app.blade.php` holds the same two background
  colours a second time**, because it paints the `html` element before the CSS
  bundle lands. It is the one place the tokens are duplicated, and leaving it
  behind is a flash of the old white on every dark-theme load.

**The theme is the tokens, and almost nothing else.** Every shadcn component
already reads `--primary`, `--muted` and the rest, so the restyle is that one
file — which is also why the starter kit's literal `neutral-*` chrome had to go
(the appearance tabs, the avatar fallbacks, the header, the footer links): a
cold grey does not follow a warm theme, and it was the one thing on the page
still the colour the starter kit left it. The literal `amber`, `red` and `green`
in the game's own components are deliberately left alone: those carry meaning
rather than brand, and a warning that changes colour with the theme is a warning
nobody can rely on.

## A game off the clock

**A game is readable before it starts and after it ends, and playable only in
between.** Every player-facing page hangs off `Game::current()`, which used to
answer only for a Running game — so a player reading their briefing the morning
before a session and a player looking back at how last night went were both
shown "No game is running", with the sidebar collapsed to the dashboard alone.
There was never nothing to read: the roster, the Facilities, the card lists and
the starting kits are all seeded when the game is created, and claiming already
worked in a Draft game, so somebody could sign in successfully, have their seats
bound, and be told the application had nothing for them. That reads as a broken
application rather than as a queue.

**Widening the lookup is the whole of the mechanism, and it is safe because the
clock was never enforced there.** Every act asks about `GameStatus::Running` in
its own right — `RunPolicy`, `FacilityPolicy`, `CorporationPolicy`,
`CouncilSessionPolicy`, `ShopListingPolicy` and `AgendaCardPolicy`, plus
`GamePresenter::mayDefend()` and `ShopPresenter::isOpen()` — so the pages come up
read-only by construction rather than needing a second set of views. Do not move
any of those checks into the lookup: a page that is read-only because nothing
found it is a page that goes writable the moment somebody widens a query.

**A running game always wins, whatever its id.** Otherwise Control setting next
Saturday's game up mid-session would pull tonight's out from under the table it
is being played on. Past that it is simply the newest game, which is the one
coming rather than the one gone — a Draft over last night's Finished, because
what a player wants before a session is the game they are about to play.

**A seat is a fact about the roster; acting is a question about the clock.**
`CouncilService::hasSeat()` used to answer no for a game that was not running,
which would have left the Chamber 403ing at the very CEOs who sat in it while
every other page opened. It carries no clock now, and the two acts that used to
lean on it for one — `CouncilSessionPolicy::vote` and `AgendaCardPolicy::create`
— ask `isRunning()` beside it, where the act is. Same division as *Who you are
and what you may do are different questions* above.

**The secrets stay kept.** `RunPresenter` and `CouncilPresenter` key what they
withhold off the run and the sitting rather than off the game's status, so a
stack's depth and a secret ballot's breakdown are as hidden the morning after as
they were on the night. There is a real argument that a finished game should open
its stacks — reading back what was actually in one is the debrief — but that is
the designer's call to make deliberately rather than something to fall out of
this change.

**And the page says which it is.** `resources/js/components/game-state-notice.tsx`
draws one line at the top of all seven player pages, because a board whose
buttons have all gone is otherwise indistinguishable from a broken one. It
enforces nothing and must not: the server has already refused everything: this is
the sentence explaining what is on screen. A `game` of null now means there is no
game at all rather than none running, which is why those empty states read "No
game has been set up yet."

## Built so far

The turn engine, the trackers, Discord-handle character claiming, Discord server provisioning with role assignment, Facility Defence — Facilities, the ordered stacks and the security budget — the game's three real card lists with the Protection Card inventory, their printed artwork and the icon font, the drag-and-drop board Security arranges their own defences on, logos wherever the application names a team or one of the three characters that is an organisation, the Council — the game's agenda deck with Control picking what goes up, the Chair's powers over it, and Political-Will-weighted voting with secret ballots — the research sub-game: the equation card game, the tech trees, deck customisation, point trading and technology copies — and Runs: submitting and ordering the groups at a Facility, the four steps, every consequence, both ways a run can end, the accesses a successful one buys, the screen players work it from, the Runners being let into the Facility's own Discord channels for the length of it, and what each Runner is carrying — the Equipment holdings, seeded from the briefings and handed out by Control — and the shop: Control putting cards out at a price with a stock behind them, and both counters players buy from, the Corporation shop out of the Corporation's Credits and the market out of their own.

**What is left is tracked as GitHub issues**, each written against the relevant rulebook section — start there rather than re-deriving the scope. Runs are the highest-value piece and the last of the sub-games, and everything a Run operates on is now built: the Facilities, their Protection Card stacks, and the technologies stored in them. `#facility-list` now carries the Facility list once Control publishes it.
