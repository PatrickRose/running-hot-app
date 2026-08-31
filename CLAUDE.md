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
| A game's starting Facilities and card holdings | `config/running_hot.php`, `App\Actions\CreateDefaultFacilities` |
| The three card lists | `App\Support\ProtectionCardBlueprint`, `EquipmentCardBlueprint`, `TechnologyBlueprint` |
| Seeding them into a game | `App\Actions\SeedProtectionCards`, `SeedEquipmentCards`, `SeedTechnologies`, `SeedProtectionCardHoldings` |
| Finding a card's artwork from its code | `App\Support\CardImage` |
| What each icon in the game's font means | `App\Support\IconFont` |
| Team Time income and wound recovery | `App\Actions\ApplyTeamTimeUpkeep` |
| Discord announcements | `App\Services\DiscordAnnouncer` |
| Who Control is, per game | `App\Models\ControlMember`, `App\Actions\ClaimControlSeatsForUser` |
| What a game's Discord server should look like | `App\Support\Discord\GuildBlueprint` |
| The `#facility-list` embed, and posting it | `App\Support\Discord\FacilityListEmbed`, `App\Actions\PublishFacilityList` |
| Building and reconciling that server | `App\Actions\ProvisionDiscordGuild` |
| A Facility's own channels | `App\Actions\ProvisionFacilityChannels`, `App\Jobs\SyncFacilityChannels` |
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
- **Feature tests need built frontend assets.** Rendering an Inertia page throws without a Vite manifest, so CI runs `composer setup` before the suite.
- **Freshly created models may not have every column hydrated.** Cast defensively when reading a boolean straight after `create()`.

## Commands

```shell
composer run dev          # serve + queue worker + vite + logs
composer ci:check         # everything CI runs: eslint, prettier, tsc, pint, phpstan, tests
php artisan test --compact --filter=SomeTest
php artisan migrate:fresh --seed --seeder=DemoGameSeeder   # demo game, control@example.com / password
DEMO_CONTROL_DISCORD=your_handle php artisan migrate:fresh --seed --seeder=DemoGameSeeder   # ...and sign in with Discord as Control
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

**Every Facility gets a private text and voice channel**, which is where its Runs will happen. They sit in the Corporation's own category, beside the two channels its players talk in, so everything a Corporation owns is in one place. Locked to Control and the owning Corporation; the Runners attacking a Facility are added when a Run starts, because they choose their target in Secret (3.4.1) and access any earlier would leak who is hitting what.

**A missing pair is visible and fixable.** Because the job is fail-soft, a Discord outage during a requisition leaves a Facility with no channels and nothing retrying, so the Control panel badges each Facility with whether its channels are on record and offers to build just that pair. A full provision run recovers them too — they are in the blueprint — but at the cost of re-PATCHing every channel and role in the guild, which is a heavy hammer for one missing channel mid-game.

**A Facility built mid-game gets its channels from a job, not a provision run.** Re-provisioning the whole guild for one requisition would re-PATCH every channel and role in it, which Discord rate-limits hard, so `ProvisionFacilityChannels` creates just the pair and `ProvisionDiscordGuild` still reconciles them on its next run. The job is fail-soft for a sharper reason than most: the `sync` driver runs it inline, so a throw would come back out of `Facility::create()` and take the requisition with it. A Facility with no channel is a nuisance; a CEO who cannot sign off a build because Discord is down would stop the game.

**Provisioning reconciles; it never resets.** Every object the application owns is recorded in `discord_resources` against a stable key (`role:control`, `channel:gang:7:text`), so a re-run renames what drifted, rebuilds what someone deleted by hand, and adds whatever the roster has grown. It is safe mid-game, and it never deletes: a gang leaving the game does not take its channel history with it. The old Discord bot's `reset` command deleted and rebuilt on every run — do not go back to that. Anything in the guild the blueprint does not ask for belongs to Control and is left alone.

**A key with no record is looked for by name before it is created.** The recorded snowflake is the right thing to match on, right up until there is no record: a rebuilt database, a game set up again for a new session, or a server Control built by hand against the blueprint. Matching on the key alone then meant a second Control role and a second category per team on every single run, which is what a test server looks like after a few months. Adoption is the reconcile the recorded key would have done, on the evidence available — the object is claimed, recorded, and brought in line with the blueprint in the same pass. A role matches on name; a channel has to match name, type *and* parent category, because a Corporation's category and its voice channel share a name and nothing else would tell them apart. Two keys can never take the same object: `claimedSnowflakes` is what stops it. `@everyone` and managed roles are never candidates — Discord will not let anybody rename or grant them.

**`ResetDiscordGuild` is the one destructive path, and it is nothing to do with provisioning.** It empties a guild so it can be built again from nothing: every channel, and every role bar `@everyone` and the managed ones Discord refuses to delete. That is far more than the application made — Control's own channels go too — which is why it is behind Control typing the game's name out, and why nothing else calls it. Children are deleted before their categories, because Discord orphans a category's children at the top level rather than taking them with it. A 403 on one object is counted and reported rather than abandoning the wipe: every guild has at least one role above the bot's. The guild itself survives; a bot cannot delete a server it did not create, and the invite links everyone has already used are worth more than the mess. Afterwards the game has no recorded snowflakes, no webhook URL and no invite, because all three now point at things that no longer exist.

The roster has to exist first, since team channels are permissioned from it. A blueprint for a game with no corporations and no gangs is just Control plus the common channels, which is correct rather than an error.

**Control is a roster, not just a flag.** `users.is_control` is granted from the console and means Control of every game there will ever be — right for whoever owns the deployment, useless for the four friends helping run tonight's game. So a game has a **Control team**: a `control_members` row per organiser, named by Discord handle in the game's Control panel and claimed at sign in exactly as a character is. `User::isControl()` is "Control of something" and gates the Control area; `isControlFor($game)` is "Control of this game" and gates every route carrying a `{game}`, so a seat on Saturday's game is not a seat on someone else's. A seat carries the Control Discord role in that game's guild and nowhere else, and whoever creates a game is seated on it, since otherwise they would be bounced off the panel of the game they had just made. Removing a seat takes the role with it. There is deliberately no way to grant the account-wide flag from the web: the thing Control actually needs to say is who is running *this* game.

**The demo seeder can seat you.** `db:seed` takes no options of its own, so the handles come from `DEMO_CONTROL_DISCORD` (via `running_hot.demo_control_discord`), and `DemoGameSeeder::run()` takes the same string as an argument, which is how one seeder passes it to another: `$this->call(DemoGameSeeder::class, false, ['controlDiscord' => ...])`. Without it the demo game's only Control is the password login the seeder prints, so signing in with Discord locally lands on a player's dashboard with no way through — which is the whole reason it is there.

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

**Directing Security is not secret.** The rulebook has Security committing simultaneously with Runners choosing targets, but that has since changed: Security decides what to protect after the attacks land, so there is deliberately no commit-then-reveal machinery here.

**A new game opens with Facilities already standing.** `CreateDefaultFacilities` runs after `CreateDefaultRoster`, because Facilities belong to Corporations, and both are governed by the same "start empty" choice on the create form. It writes a starting position rather than a change, so the Facilities are built free and the basic cards installed free — nothing goes through `TrackerService`, because there is no before state. Re-running is a no-op: a second Armoury would silently widen every stack in the game.

**Every Facility is named in `config/running_hot.php`, not labelled from its type.** A Facility's name is what players call it all game, and "Gordon Corporate 2" is a label. The names there are flavour rather than briefing data — places near Sheffield, since that is where Procatorion was bought — so rename them freely; nothing keys off them. An entry with no name falls back to the Corporation's short name and the type, so a Corporation added later still works.

**Starting Facilities are per Corporation and the differences are mechanical**, not decorative. They live beside each Corporation in `config/running_hot.php`, from the briefing documents: DTC's second Security Facility widens every one of its stacks, Gordon's three Corporate Facilities make it the only Corporation storing six technologies per Facility, and Genetic Equity's three Research Facilities are its whole strategy. A Corporation the config says nothing about opens with none rather than a guessed set.

## The card lists

Three card families, all real data from the game's own card sheet, all seeded per game and all editable by Control. They live in `App\Support` beside the Facility type sheet rather than in `config/running_hot.php`, because three hundred cards do not read comfortably in a config file: `ProtectionCardBlueprint` (83), `EquipmentCardBlueprint` (74) and `TechnologyBlueprint` (144). `Game::booted` seeds all three when a game is created — none of them depends on the roster, so they arrive before it does.

**The code printed on a card is what identifies it, not its title.** Doppleganger is two cards, `PX011` in the physical stack and `PX012` in the cyber one, so `protection_card_types` is unique on `(game_id, code)` and titles may repeat. The one-copy-per-Facility rule of 3.3.4 keys on the card type rather than the title, so a Facility can hold both Dopplegangers and still only one of each.

**ANT's five cards are distinct cards, not its own names for the other five.** Öryggissveit, Takkaborðið, Öryggisluggari, Vélfærafræði sporðdreka and Engill (`PS015`–`PS020`) are ANT's own, and the four other Corporations hold Security team, Keypad, Security shutter, Roboscorpion and Angel instead. Everyone holds Orc. Their printed stats are identical pair for pair, which is exactly why it is worth writing down: they are still separate rows, and merging them would be wrong.

**A challenge is the sentence the card prints, not a skill and a number.** Most cards are a plain `Brute (6)`, but `Brute/Hack (2)` lets the Runners choose, `Hack (4+N) - where N is the number of cards underneath this` is not known until the card is met, and `Hack (4), followed by Brute (4)` is two challenges on one card. There is no parsed skill or strength column: the words are stored and shown, and the table converts them, which is what it does with the card in hand anyway. Runs will need to read the text.

**Availability is read off the code prefix, and that is a reading rather than a rule.** The card sheet has no availability column. `PS` and `PE` cards are the ones the card sheet prices and the briefings hand out; every `PR` and `PX` card is the target of some technology's `Unlock:` effect, so they are seeded research-only. Three (`PR001`, `PR011`, `PR013`) are unlocked by no technology in the list at all, so Control has to add the research or open them up by hand. `ProtectionCardBlueprint` says all of this in one place instead of deriving it at runtime.

**No card carries a shop price, deliberately.** The card sheet has a cost column, but the Corporation shop and the Runners' market do not work the way it suggests, so seeding those numbers would encode a pricing model the game does not use — and a wrong price is worse than none, because the shop would be built on it. `cost` stays a nullable column that Control can fill in for a one-off ruling, and **pricing arrives with the shop**, which is its own piece of work. A Charge is unaffected: its cost is Credits Security spends during a Run (3.3.5), printed on the card and nothing to do with buying one, so it is seeded. Research costs are unaffected too — those are Research Points, not Credits.

**Cards are found by code, and a card with no artwork is normal.** `App\Support\CardImage` resolves `public/images/cards/<CODE>.{webp,png,jpg}` and answers null when there is nothing there. Control invents cards mid-game — the rulebook has research proposals priced and added to the tree during play, and DTC's "Unfortunate Malfunction" hands out a bypass card named after whichever Protection Card it counters — and those have never been printed. The `CardFace` React component shows artwork where there is any and a card-shaped box of the card's own text where there is not, so the text box is the other normal case rather than a fallback.

**A research card has two faces, filed `<CODE>_F` and `<CODE>_B`.** A technology is printed proposal side up and flipped over once researched (3.2.2), and both faces are public — a technology is not secret, only which Facility is storing it is. The two need no columns: they are the fields the application already has, split the way the card splits them, with the front the name, description, suit costs, prerequisites and required Facility type and the back the effect with its copy and destroy strengths. A single-sided card resolves from `<CODE>_F` or from the bare `<CODE>`; the back has no such fallback, because an unsuffixed file is the front of a one-sided card and treating it as a back would have every card offering a flip that showed the same picture twice. Either face may be the one that is missing — sixteen technologies have a back and no front — so anything showing a card takes whichever it has.

**The three families are not the same shape, and a card is the size of a card.** Equipment is printed portrait at 600×817 and both the Protection and research cards landscape at 600×440, so no single aspect ratio may be imposed across all three — forcing one crops two thirds of the game. What `CardFace` takes instead is the family's shape, from the call site, and it holds *both* faces to it: a card shown as its own text is the same size as the artwork beside it, rather than as tall as its words, so a list lines up whether or not the artwork has been drawn. That means a wordy text card can outrun its box, which is why the words fade at the bottom rather than being cut, why the availability line sits below the fade, and why every card — not just the ones with artwork — carries its full text in a tooltip and in the page for a screen reader. The table thumbnail is a different problem and is held to a height instead, so a landscape and a portrait card still line their rows up. `CardFace`'s footer is part of that text rather than a caption, so it is drawn only on a card being shown as its words — anything that has to be legible on the artwork as well lives outside `CardFace` and on top of it, which is what the `×N` copy count on a card in Security's hand is. Card artwork lives under `public/` because it is referenced from an `img` tag at runtime and the page is served by Laravel — the opposite of the icon font below.

**The artwork directory is read once per request, not once per card.** Three hundred cards against four extensions is the better part of a thousand `stat` calls a page, and worst on a checkout with no artwork at all. `CardImage` lists the directory once and answers from memory, which also makes lower-case file names resolve and lets a `webp` supersede a `png` by extension rather than by directory order. Tests that write artwork must call `CardImage::flush()` — `TestCase` does it for every test — and must never write over a real code: the game's artwork is committed, so a test cleaning up after itself would delete it. One did.

**Owning copies is modelled; buying them is not.** `protection_card_holdings` is a count per Corporation per card, and it is what caps how far a card stretches: one copy per Facility, so four copies of Security Team defend four Facilities and no more. `FacilityDefenceService::install()` is the only place a copy leaves a hand and `remove()` the only place one comes back — a copy in a Facility is a row in `facility_protection_cards`, so the hand plus the installed copies is still the briefing's count. Installing with none left is refused; Control raises the count first.

Control sets any count outright via `ProtectionCardHoldingController`. The Corporation shop, auctions, research grants and Security players trading between themselves all happen at the table, so the application records where a count ended up rather than replaying how it got there. **Buying from the shop is a follow-up.**

**Starting cards are drawn from what a Corporation actually holds.** `installed_in_each` in `config/running_hot.php` says how many of each kind a starting Facility opens with (one each), and `CreateDefaultFacilities` picks the card the Corporation has most copies of that the Facility does not already have. That is what makes ANT's Facilities open with ANT's own cards, and it spreads the load — a Corporation holds four copies of its commonest card against five Facilities, so no single card can cover them all. Running out is not an error: a Facility simply opens thinner.

**Equipment and technologies are catalogue only.** Both carry their effects as printed text, because the Run loop and the research game that would act on them are unbuilt. Equipment's `category` is the one attribute the application reads, since it decides when a card may be played and whether it counts against the three equipped items of 3.4.1. A technology carries its four suit costs, its prerequisites as printed titles (Control may add a technology others already name), a required Facility type, and its copy and destroy strengths. Control reads both lists at `/control/games/{game}/cards`, which is read-only on purpose.

**Technology trees attach to Corporations late.** A game is created before its roster exists, so `SeedTechnologies` writes every technology unattached and fills in `corporation_id` on a second run, after `CreateDefaultRoster`. `CreateDefaultFacilities` makes that second call. Note the codes do not identify the tree reliably — Gordon and Genetic Equity both take a `G` — so the tree comes from the sheet's own column.

**Not modelled, deliberately:** the Corporation shop, auctions, research grants, trading copies between Security players, and who owns which Equipment card. Each is a conversation with Control, who then sets the count.

## The icon font

The rulebook prints the four Research Point suits as icons and never names them in its body text, which is why they do not survive `pdftotext` and why issue #6 says to read the PDF for §3.2. The game's own font draws them, and it is committed at `resources/fonts/RunningHot-Font.ttf`.

**It is an icon font: every icon is drawn by an ASCII capital.** So a "glyph" is a plain letter — Physical is `E`, Brain is `B` — and it only reads as a picture while the font is loaded. Two consequences, both load-bearing:

- **A screen reader left to itself says "E" where the page means Physical.** So the glyph is always `aria-hidden` with its name in an `sr-only` span beside it, and nothing renders a glyph directly: it goes through the `GameIcon` React component, which is what stops the pairing being forgotten at the next call site.
- **A missing glyph fails quietly**, rendering a bare capital rather than nothing, which reads as a styling bug. `tests/Unit/IconFontTest.php` therefore parses the font's own `cmap` table and asserts every icon in use is really in the file. `N` is the one capital the font has no icon for, which makes it the canary that keeps that test from passing vacuously.

**What each icon means is recorded in `App\Support\IconFont`, and nowhere else.** The glyph names inside the font are only the letters, so nothing in the file says what any of them is — working it out again means rendering the font and looking at it. The three enums that draw themselves (`ProtectionKind`, `EquipmentCategory`, `ResearchSuit`) take their glyphs from there. `G` (Boost) and `Y` (a research wildcard) are drawn and recorded but unused, because Runs and the research game are not built.

**The font is loaded through Vite, not from `public/`.** `laravel-vite-plugin` sets Vite's `publicDir` to `false`, and in development the stylesheet is served from the Vite origin — so a root-relative `url('/fonts/…')` asks the dev server for a directory it does not serve, 404s, and the icons silently degrade to bare letters for everybody running `composer run dev` while working perfectly once built. Anything referenced from CSS has to live under `resources/` and be referenced relatively. Card artwork is the opposite case and belongs in `public/`.

## Built so far

The turn engine, the trackers, Discord-handle character claiming, Discord server provisioning with role assignment, Facility Defence — Facilities, the ordered stacks and Directing Security — the game's three real card lists with the Protection Card inventory, their printed artwork and the icon font, and the drag-and-drop board Security arranges their own defences on.

**What is left is tracked as GitHub issues**, each written against the relevant rulebook section — start there rather than re-deriving the scope. Runs are the highest-value piece, but they are blocked on Facilities and Protection Cards, which are the state a Run operates on. The Council and the Research game are independent of both and can be picked up in parallel. `#facility-list` now carries the Facility list once Control publishes it.
