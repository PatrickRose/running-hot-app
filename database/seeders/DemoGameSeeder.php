<?php

namespace Database\Seeders;

use App\Actions\ClaimControlSeatsForUser;
use App\Actions\CreateDefaultFacilities;
use App\Actions\CreateDefaultRoster;
use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use App\Support\DiscordHandle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A playable game for developing against.
 *
 * The roster is the real one, built by the same action the Control panel uses,
 * so what a developer sees locally is what Control gets on the night.
 */
class DemoGameSeeder extends Seeder
{
    /**
     * The password every account the seeder makes shares, Control's included.
     */
    private const PASSWORD = 'password';

    /**
     * The domain the demo logins are made under. Reserved by RFC 2606, so no
     * password reset or verification mail can ever reach a real inbox.
     */
    private const EMAIL_DOMAIN = 'example.com';

    public function __construct(
        private readonly CreateDefaultRoster $roster,
        private readonly CreateDefaultFacilities $facilities,
        private readonly ClaimControlSeatsForUser $claimSeats,
    ) {}

    /**
     * @param  string|null  $controlDiscord  Discord handles to seat on the demo
     *                                       game's Control team, comma or space
     *                                       separated. Falls back to
     *                                       running_hot.demo_control_discord.
     */
    public function run(?string $controlDiscord = null): void
    {
        $control = User::query()->firstOrCreate(
            ['email' => 'control@'.self::EMAIL_DOMAIN],
            [
                'name' => 'Control',
                'password' => Hash::make(self::PASSWORD),
            ],
        );

        $control->forceFill(['is_control' => true, 'email_verified_at' => now()])->save();

        $game = Game::create([
            'name' => 'Running Hot — Procatorion',
            'stability' => 6,
            'civil_unrest' => 0,
            // No webhook: provisioning the game's Discord server makes one.
            // Seeding must never post to a live channel, and a stand-in URL
            // would only look configured while every announcement failed.
        ]);

        $seated = $this->seatControlTeam(
            $game,
            $controlDiscord ?? (string) config('running_hot.demo_control_discord'),
        );

        $created = $this->roster->handle($game);
        $defences = $this->facilities->handle($game);
        $logins = $this->createCharacterLogins($game);

        $this->command->info(sprintf(
            'Demo game seeded: %d corporations, %d gangs, %d characters.',
            $created['corporations'],
            $created['gangs'],
            $created['characters'],
        ));
        $this->command->info(sprintf(
            '%d Facilities built, %d card holdings given out, %d cards installed.',
            $defences['facilities'],
            $defences['holdings'],
            $defences['installed'],
        ));
        $this->command->info(sprintf(
            '%d Protection Cards, %d Equipment cards and %d technologies catalogued.',
            $game->protectionCardTypes()->count(),
            $game->equipmentCardTypes()->count(),
            $game->technologyTypes()->count(),
        ));

        $this->command->info(sprintf(
            'Every login below takes the password "%s".',
            self::PASSWORD,
        ));
        $this->command->table(
            ['Character', 'Role', 'Team', 'Login'],
            [
                ['Control', 'Control', 'Control', $control->email],
                ...$logins,
            ],
        );

        if ($seated === []) {
            $this->command->warn('Nobody is on the game\'s Control team. Set DEMO_CONTROL_DISCORD to your Discord handle to sign in with Discord as Control.');
        } else {
            $this->command->info(sprintf(
                'Control team: @%s. A seat is claimed the first time that handle signs in.',
                implode(', @', $seated),
            ));
        }

        $this->command->warn('Add a Discord server to the game in the Control panel before announcements will land.');
    }

    /**
     * Seat the developer on the demo game's Control team.
     *
     * Signing in with Discord otherwise lands on a player's dashboard with no
     * way through to Control, since the only Control account the seeder makes
     * is a password login. A seat is the same claim ticket a character carries,
     * so this may name a handle that has never signed in.
     *
     * db:seed takes no options of its own, so the handles come from config -
     * or as an argument, which is how one seeder calls another:
     * `$this->call(DemoGameSeeder::class, false, ['controlDiscord' => '...'])`.
     *
     * @return array<int, string> the handles seated
     */
    private function seatControlTeam(Game $game, string $handles): array
    {
        $seated = collect(preg_split('/[,\s]+/', $handles) ?: [])
            ->map(fn (string $handle): ?string => DiscordHandle::normalise($handle))
            ->filter()
            ->unique()
            ->values();

        foreach ($seated as $handle) {
            $game->controlMembers()->create(['discord_username' => $handle]);

            // Bind anyone who has already signed in, rather than making them
            // log out and back in to become Control of the game just seeded.
            $user = User::query()->whereRaw('LOWER(discord_username) = ?', [$handle])->first();

            if ($user !== null) {
                $this->claimSeats->handle($user);
            }
        }

        /** @var array<int, string> */
        return $seated->all();
    }

    /**
     * Give every character in the demo game a password login of its own.
     *
     * A real game claims characters by Discord handle, which is no use to a
     * developer who wants to see the game from a CEO's seat, then a Security
     * player's, then a Runner's: it would want a Discord account per seat. So
     * each character is claimed here by an account named after it, and the
     * whole list is printed to sign in with.
     *
     * This writes a starting position rather than a change, exactly as the
     * roster does, so it claims the character directly instead of going through
     * ClaimCharactersForUser - there is no Discord handle here to match on.
     *
     * Every character is claimed, because the roster action made them all a
     * moment ago and a claim is the point: an unclaimed seat has no dashboard
     * to sign in to.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function createCharacterLogins(Game $game): array
    {
        $rows = [];

        // Hashed once: bcrypt is deliberately slow, and forty-odd characters
        // all sharing one password would otherwise pay for it forty-odd times.
        $password = Hash::make(self::PASSWORD);

        $characters = $game->characters()
            ->with(['corporation:id,name', 'gang:id,name'])
            ->orderBy('id')
            ->get();

        foreach ($characters as $character) {
            $email = $this->loginFor($character);

            $user = User::query()->create([
                'name' => $character->name,
                'email' => $email,
                'password' => $password,
            ]);

            // Verified on the way in, as signing in with Discord does it, and
            // forced because email_verified_at is not mass assignable: every
            // page worth testing sits behind the `verified` middleware.
            $user->forceFill(['email_verified_at' => now()])->save();

            $character->update(['user_id' => $user->id]);

            $rows[] = [
                $character->name,
                $character->role->label(),
                $character->corporation->name ?? $character->gang->name ?? 'Unaffiliated',
                $email,
            ];
        }

        return $rows;
    }

    /**
     * An address a developer can guess from the character's name.
     *
     * Nothing makes a character's name unique - Control may field two people
     * called the same thing, and a runner's name may slug down to nothing at
     * all - so a clash is numbered rather than allowed to hit the unique index
     * on users.email and take the whole seed down.
     */
    private function loginFor(Character $character): string
    {
        $slug = Str::slug($character->name) ?: 'character-'.$character->id;
        $email = $slug.'@'.self::EMAIL_DOMAIN;
        $taken = 1;

        while (User::query()->where('email', $email)->exists()) {
            $email = sprintf('%s-%d@%s', $slug, ++$taken, self::EMAIL_DOMAIN);
        }

        return $email;
    }
}
