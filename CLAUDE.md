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

**Never write a tracker directly.** All movement of Income, Political Will, Credits, Notoriety, Wounds, Tags, Stability and Civil Unrest goes through `TrackerService`, which writes a `tracker_adjustments` row recording before, after, delta, actor and reason. That ledger is how Control answers "why did that number change?" three turns later. `$model->update(['wounds' => ...])` bypasses it and is a bug.

**The clock is server-authoritative.** A phase stores an absolute `ends_at` that extensions and pauses mutate directly; remaining time is always derived from it. The browser only counts down between polls and must never be able to make a phase run long.

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
| A game's starting Facilities and card catalogue | `config/running_hot.php`, `App\Actions\CreateDefaultFacilities` |
| Team Time income and wound recovery | `App\Actions\ApplyTeamTimeUpkeep` |
| Discord announcements | `App\Services\DiscordAnnouncer` |
| What a game's Discord server should look like | `App\Support\Discord\GuildBlueprint` |
| The `#facility-list` embed, and posting it | `App\Support\Discord\FacilityListEmbed`, `App\Actions\PublishFacilityList` |
| Building and reconciling that server | `App\Actions\ProvisionDiscordGuild` |
| Handing a player their Discord roles | `App\Actions\SyncDiscordRolesForUser` |
| Discord REST calls as the bot | `App\Services\Discord\DiscordApi` |
| Inertia payload shaping | `App\Support\GamePresenter` |
| What a player may see of the Facilities | `App\Http\Controllers\FacilityBoardController` |
| Auto-advance and its backstop | `App\Jobs\AdvancePhase`, `game:tick` |

## Gotchas that have already cost time

- **Delayed jobs do nothing useful on the `sync` queue driver** — it ignores `delay()` and runs immediately. `TurnEngine` therefore skips scheduling auto-advance under `sync`, and `AdvancePhase` returns rather than re-dispatching when it fires early. Re-dispatching there caused an infinite loop.
- **Auto-advance needs a queue worker, and the backstop needs the scheduler.** In production run both `queue:work` and `schedule:work`. `composer run dev` starts a worker but not the scheduler.
- **Wayfinder's generated modules are gitignored.** `resources/js/routes`, `resources/js/actions` and `resources/js/wayfinder` do not exist in a clean checkout, so `tsc` cannot resolve imports until `php artisan wayfinder:generate --with-form` (or `npm run build`) has run. Always pass `--with-form`: without it the `.form()` helpers vanish and starter-kit pages break.
- **Feature tests need built frontend assets.** Rendering an Inertia page throws without a Vite manifest, so CI runs `composer setup` before the suite.
- **Freshly created models may not have every column hydrated.** Cast defensively when reading a boolean straight after `create()`.

## Commands

```shell
composer run dev          # serve + queue worker + vite + logs
composer ci:check         # everything CI runs: eslint, prettier, tsc, pint, phpstan, tests
php artisan test --compact --filter=SomeTest
php artisan migrate:fresh --seed --seeder=DemoGameSeeder   # demo game, control@example.com / password
php artisan game:tick     # advance any phase whose clock has expired
```

PHP 8.5 is the minimum, and CI runs the same version.

## Discord integration

Three independent mechanisms, and it is worth keeping them straight:

- **OAuth**, for identity. Players sign in with Discord; `identify` and `email` scopes only. Login matches the immutable snowflake, never the handle, because handles can be changed. A character's `discord_username` is only a claim ticket, resolved once to a `user_id`.
- **An incoming webhook**, for announcements. Posts as itself, needs no bot token, and is fail-soft so an outage cannot stall the clock. Set per game — there is deliberately no global default to post to by mistake, and a game without one announces nowhere rather than somewhere wrong. Provisioning creates it, so it is not asked for when a game is created: requiring it there asked Control for the thing the application was about to make, and the only way through was to invent a URL. If you find yourself wanting a placeholder webhook, that is the bug.
- **A bot**, for provisioning. `DISCORD_BOT_TOKEN` lets the application build a game's server out: the roles, the channels, the per-team permissions, its own announcement webhook and a join invite. Needs Manage Roles, Manage Channels, Manage Webhooks and Create Instant Invite in the guild. Keep it as a third integration rather than an extension of the two above; leave the token unset and provisioning and role assignment report themselves unconfigured while sign in and announcements carry on working.

**The application never creates the guild.** Discord's Create Guild endpoint only works for bots in fewer than ten guilds and hands back a server nobody is a member of, so Control makes the server by hand and the application is given it. Snowflakes are stored as strings — a bare one is a numeric string that PHP will silently coerce to an integer array key, which is why `ProvisionDiscordGuild` indexes them behind an `id:` prefix.

**Nobody should have to copy a snowflake.** Three ways to attach a server, in order of least work: the bot-add OAuth flow (`connect` → Discord's own server picker → `callback`), which is the good path because `response_type=code` plus a registered `redirect_uri` makes Discord hand `guild_id` back, so the invite and the ID happen in one click; a list of the servers the bot is already in, from `GET /users/@me/guilds`, served as an `Inertia::optional()` prop so opening the Control panel never calls Discord; and a plain text field for correcting one by hand. The callback also checks the granted permission bits, because Discord lets the person untick boxes on the way through and a missing Manage Roles would otherwise only surface halfway through a provision run. `DISCORD_BOT_REDIRECT_URI` must be registered in the Developer Portal next to the login one.

**The bot holds the Control role, and takes it before making any channel.** Discord drops every permission in a channel its caller cannot view, and `@everyone` is the bot's only source of View Channel — so a category locked to one team locks the bot out of it too, and it then cannot create the channels that belong inside it. Every private channel in the blueprint already grants Control, so holding that role is all the access the bot needs. The role carries `permissions: 0` and only ever opens channels, so this grants the bot nothing at guild level. Do not reorder `giveBotTheControlRole` after `reconcileChannels`: provisioning dies on the first private category.

**Provisioning reconciles; it never resets.** Every object the application creates is recorded in `discord_resources` against a stable key (`role:control`, `channel:gang:7:text`), so a re-run renames what drifted, rebuilds what someone deleted by hand, and adds whatever the roster has grown. It is safe mid-game, and it never deletes: a gang leaving the game does not take its channel history with it. The old Discord bot's `reset` command did delete and rebuild — do not go back to that. Anything in the guild the application did not create belongs to Control and is left alone.

The roster has to exist first, since team channels are permissioned from it. A blueprint for a game with no corporations and no gangs is just Control plus the common channels, which is correct rather than an error.

**Roles are handed out on every sign in**, not just the first — same reasoning as character claiming. Assigning a role needs the player to already be a guild member: Discord answers 404 otherwise, and the only way round it is the `guilds.join` scope, which would widen login beyond the `identify`/`email` it deliberately asks for. So a non-member is recorded as `not_a_member` in `discord_member_syncs`, the dashboard shows them the invite, and the next sign in tries again. A sync only ever adds or removes this game's own recorded roles, so a role Control granted by hand survives it.

**Never let a test reach Discord.** `TestCase` calls `Http::preventStrayRequests()` and `phpunit.xml` blanks `DISCORD_WEBHOOK_URL`, because the sync queue driver runs the announcement job inline: without both, a real webhook in `.env` gets posted to for real. `SendDiscordAnnouncement` deliberately rethrows `StrayRequestException` so this fails loudly rather than being swallowed by its fail-soft catch.

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

**One embed per Corporation**, coloured with `GuildBlueprint::colourFor()` so a Corporation matches the Discord role its players already wear. The cost is Discord's cap of ten embeds per message, against twenty-five fields had it been one embed of fields — a game with more Corporations than ten gets the first ten and a footer saying so. Two Corporations can still collide on a colour, because `colourFor` is a hash of the name across ten colours; that is true of the roles too.

**It is posted once and then rewritten.** A list that changes every time a Facility opens would otherwise leave the channel full of superseded copies, and a player reading the wrong one is worse than a player reading none. The message id is a `discord_resources` row (`message:facility-list`), so the reconcile pattern already covers it. This needs the *bot*, not the webhook: a webhook only posts to the channel it was made in.

**Control publishes it; the application never creates it unprompted.** `PublishFacilityList::refresh()` keeps an existing list current as each Setup phase opens and does nothing at all until Control has published one, so provisioning a server can never surprise it with a post. Refresh is fail-soft for the same reason announcements are — a list a turn out of date must never stall the clock.

**Players read the Facilities at `/facilities`, in two tiers, and the line between them is the point.** Everyone sees the same public list the embed carries — Corporation, Facility name, type, building. A player holding a Corporate seat additionally sees *their own* Corporation's stacks in full, because 3.4.2 makes a stack Secret from everyone else, not from the Corporation that installed it. So a Runner learns nothing there that reconnaissance would otherwise have to buy, and a Security player cannot read a rival's stack. `GamePresenter::facilityBoard()` decides which tier a viewer gets, from the Corporate characters they have claimed rather than from a column.

**That page is read-only.** Security tells Control what to install and where to Direct Security, exactly as they would hand over a requisition slip at the table, so every write stays on a `control.` route.

**Directing Security is not secret.** The rulebook has Security committing simultaneously with Runners choosing targets, but that has since changed: Security decides what to protect after the attacks land, so there is deliberately no commit-then-reveal machinery here.

**A new game opens with Facilities already standing.** `CreateDefaultFacilities` runs after `CreateDefaultRoster`, because Facilities belong to Corporations, and both are governed by the same "start empty" choice on the create form. It writes a starting position rather than a change, so the Facilities are built free and the basic cards installed free — nothing goes through `TrackerService`, because there is no before state. Re-running is a no-op: a second Armoury would silently widen every stack in the game.

**Every Facility is named in `config/running_hot.php`, not labelled from its type.** A Facility's name is what players call it all game, and "Gordon Corporate 2" is a label. The names there are flavour rather than briefing data — places near Sheffield, since that is where Procatorion was bought — so rename them freely; nothing keys off them. An entry with no name falls back to the Corporation's short name and the type, so a Corporation added later still works.

**Starting Facilities are per Corporation and the differences are mechanical**, not decorative. They live beside each Corporation in `config/running_hot.php`, from the briefing documents: DTC's second Security Facility widens every one of its stacks, Gordon's three Corporate Facilities make it the only Corporation storing six technologies per Facility, and Genetic Equity's three Research Facilities are its whole strategy. A Corporation the config says nothing about opens with none rather than a guessed set.

**The Protection Card catalogue in `config/running_hot.php` is still a placeholder.** Eleven cards are known by title: Orc, which everyone holds, plus Security Team, Keypad, Security Shutter, Roboscorpion and Angel, and the five ANT holds instead — Öryggissveit, Takkaborðið, Öryggisluggari, Vélfærafræði sporðdreka and Engill. ANT's are *distinct cards*, not its own names for the other five. What is not known is kind, cost, challenge or consequence, and a card with no kind cannot be installed at all, because installation is per stack. The invented titles stay until the real attributes land; emptying the list is safe.

**Owning copies of cards is coming.** The briefings give each Corporation counts ("4 copies of Security Team"), which is the inventory this application does not yet model. It arrives with the real card list.

**Not modelled:** owning copies of cards. Buying from the Corporation shop, auctions, research grants and trading copies between Security players all happen at the table, and installing is free in the rulebook, so a card's `cost` is catalogue data. Installing reads the catalogue directly rather than consuming an inventory.

## Built so far

The turn engine, the trackers, Discord-handle character claiming, Discord server provisioning with role assignment, and Facility Defence — Facilities, the Protection Card catalogue, the ordered stacks, and Directing Security.

**What is left is tracked as GitHub issues**, each written against the relevant rulebook section — start there rather than re-deriving the scope. Runs are the highest-value piece, but they are blocked on Facilities and Protection Cards, which are the state a Run operates on. The Council and the Research game are independent of both and can be picked up in parallel. `#facility-list` now carries the Facility list once Control publishes it.
