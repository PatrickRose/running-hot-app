<?php

namespace Database\Seeders;

use App\Actions\ClaimControlSeatsForUser;
use App\Actions\CreateDefaultFacilities;
use App\Actions\CreateDefaultRoster;
use App\Models\Game;
use App\Models\User;
use App\Support\DiscordHandle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A playable game for developing against.
 *
 * The roster is the real one, built by the same action the Control panel uses,
 * so what a developer sees locally is what Control gets on the night.
 */
class DemoGameSeeder extends Seeder
{
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
            ['email' => 'control@example.com'],
            [
                'name' => 'Control',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $control->forceFill(['is_control' => true])->save();

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
        $this->command->info('Control login: control@example.com / password');

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
}
