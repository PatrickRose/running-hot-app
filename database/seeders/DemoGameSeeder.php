<?php

namespace Database\Seeders;

use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Gang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A playable-looking game for developing against: four Corporations, as bought
 * the land near Sheffield in the setting, plus three runner gangs.
 */
class DemoGameSeeder extends Seeder
{
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
        ]);

        $corporations = collect([
            ['name' => 'Aldermarch Dynamics', 'income' => 12, 'political_will' => 6],
            ['name' => 'Bellweather Systems', 'income' => 9, 'political_will' => 5],
            ['name' => 'Corvid Biotics', 'income' => 14, 'political_will' => 4],
            ['name' => 'Duncastle Armour', 'income' => 8, 'political_will' => 7],
        ])->map(fn (array $attributes): Corporation => Corporation::create([
            'game_id' => $game->id,
            ...$attributes,
            'credits' => 20,
        ]));

        foreach ($corporations as $corporation) {
            foreach ([CharacterRole::Ceo, CharacterRole::Security, CharacterRole::Research] as $role) {
                Character::create([
                    'game_id' => $game->id,
                    'corporation_id' => $corporation->id,
                    'name' => sprintf('%s %s', $corporation->name, $role->label()),
                    'role' => $role,
                    'brawn' => 2,
                    'hack' => 2,
                    'body' => 3,
                    'credits' => 0,
                ]);
            }
        }

        $gangs = collect(['The Kestrels', 'Nightshift', 'Sixth Signal'])
            ->map(fn (string $name): Gang => Gang::create([
                'game_id' => $game->id,
                'name' => $name,
                'notoriety' => 0,
            ]));

        foreach ($gangs as $index => $gang) {
            foreach (range(1, 3) as $number) {
                Character::create([
                    'game_id' => $game->id,
                    'gang_id' => $gang->id,
                    'name' => sprintf('%s Runner %d', $gang->name, $number),
                    'role' => CharacterRole::Runner,
                    'brawn' => 3 + $index,
                    'hack' => 5 - $index,
                    'body' => 3,
                    'credits' => 10,
                ]);
            }
        }

        $this->command->info('Demo game seeded. Control login: control@example.com / password');
    }
}
