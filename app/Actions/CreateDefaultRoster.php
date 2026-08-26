<?php

namespace App\Actions;

use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\Gang;
use Illuminate\Support\Facades\DB;

/**
 * Populates a new game with the roster of the real game, as configured in
 * config/running_hot.php.
 *
 * This only ever writes a starting position. Every number it sets is a Tracker
 * that Control moves during play, so the action deliberately does not go
 * through TrackerService: there is no "before" state for the ledger to record
 * and no decision here for Control to answer for later.
 *
 * Applying the roster twice to the same game would violate the unique index on
 * a team's name, so a game that already has teams is left alone.
 */
class CreateDefaultRoster
{
    /**
     * The three roles every Corporation fields (rulebook 1.3).
     *
     * @var array<int, CharacterRole>
     */
    private const CORPORATE_ROLES = [
        CharacterRole::Ceo,
        CharacterRole::Security,
        CharacterRole::Research,
    ];

    /**
     * @return array{corporations: int, gangs: int, characters: int, skipped: bool}
     */
    public function handle(Game $game): array
    {
        if ($game->corporations()->exists() || $game->gangs()->exists() || $game->characters()->exists()) {
            return ['corporations' => 0, 'gangs' => 0, 'characters' => 0, 'skipped' => true];
        }

        return DB::transaction(function () use ($game): array {
            $characters = $this->createCorporations($game)
                + $this->createGangs($game)
                + $this->createUnaffiliated($game);

            return [
                'corporations' => count($this->corporations()),
                'gangs' => count($this->gangs()),
                'characters' => $characters,
                'skipped' => false,
            ];
        });
    }

    /**
     * Corporate characters are seats rather than named people: a player is
     * assigned to "Gordon CEO" when they claim it.
     */
    private function createCorporations(Game $game): int
    {
        $created = 0;

        foreach ($this->corporations() as $attributes) {
            // Read by CreateDefaultFacilities and SeedProtectionCardHoldings
            // rather than by the model. Stripped rather than left for mass
            // assignment to drop, because seeding runs unguarded - a stray key
            // reaches the insert and takes the whole seeder down.
            unset($attributes['facilities'], $attributes['protection_cards']);

            $corporation = Corporation::create([
                'game_id' => $game->id,
                ...$attributes,
            ]);

            foreach (self::CORPORATE_ROLES as $role) {
                Character::create([
                    'game_id' => $game->id,
                    'corporation_id' => $corporation->id,
                    'name' => sprintf('%s %s', $corporation->name, $role->label()),
                    'role' => $role,
                    'credits' => $this->startingCredits('corporate'),
                ]);

                $created++;
            }
        }

        return $created;
    }

    private function createGangs(Game $game): int
    {
        $created = 0;

        foreach ($this->gangs() as $attributes) {
            /** @var array<int, array<string, mixed>> $runners */
            $runners = $attributes['runners'] ?? [];
            unset($attributes['runners']);

            $gang = Gang::create([
                'game_id' => $game->id,
                ...$attributes,
            ]);

            foreach ($runners as $runner) {
                Character::create([
                    'game_id' => $game->id,
                    'gang_id' => $gang->id,
                    'role' => CharacterRole::Runner,
                    'credits' => $this->startingCredits('other'),
                    ...$runner,
                ]);

                $created++;
            }
        }

        return $created;
    }

    private function createUnaffiliated(Game $game): int
    {
        $created = 0;

        foreach ($this->unaffiliated() as $attributes) {
            Character::create([
                'game_id' => $game->id,
                'credits' => $this->startingCredits('other'),
                ...$attributes,
            ]);

            $created++;
        }

        return $created;
    }

    private function startingCredits(string $key): int
    {
        return (int) config("running_hot.starting_credits.{$key}", 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function corporations(): array
    {
        return $this->configuredList('running_hot.corporations');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function gangs(): array
    {
        return $this->configuredList('running_hot.gangs');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function unaffiliated(): array
    {
        return $this->configuredList('running_hot.unaffiliated');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function configuredList(string $key): array
    {
        /** @var array<int, array<string, mixed>> $list */
        $list = config($key, []);

        return $list;
    }
}
