<?php

namespace Database\Seeders;

use App\Actions\CreateDefaultRoster;
use App\Models\Game;
use App\Models\User;
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
    public function __construct(private readonly CreateDefaultRoster $roster) {}

    public function run(): void
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

        $created = $this->roster->handle($game);

        $this->command->info(sprintf(
            'Demo game seeded: %d corporations, %d gangs, %d characters.',
            $created['corporations'],
            $created['gangs'],
            $created['characters'],
        ));
        $this->command->info('Control login: control@example.com / password');
        $this->command->warn('Add a Discord server to the game in the Control panel before announcements will land.');
    }
}
