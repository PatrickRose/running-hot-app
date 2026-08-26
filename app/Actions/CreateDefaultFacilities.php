<?php

namespace App\Actions;

use App\Enums\ProtectionCardAvailability;
use App\Enums\ProtectionKind;
use App\Enums\RunnerSkill;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Models\ProtectionCardType;
use App\Services\FacilityDefenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gives a new game its Protection Card catalogue and every Corporation the
 * Facilities it opens with, as configured in config/running_hot.php.
 *
 * "Each Corporation will begin with a number of Facilities and some basic
 * Protection Cards" (rulebook 3.3), and until they exist there is nothing for
 * Runners to run against and nothing for Security to install into.
 *
 * Runs after {@see CreateDefaultRoster}, because Facilities belong to
 * Corporations. Like that action this only ever writes a starting position, so
 * the Facilities are built free and the cards installed free: there is no
 * before state for the ledger to record, and no CEO signed anything off.
 *
 * A game that already has Facilities is left alone, so applying it twice cannot
 * hand a Corporation a second Armoury and quietly widen every stack in the
 * game.
 */
class CreateDefaultFacilities
{
    public function __construct(private readonly FacilityDefenceService $defence) {}

    /**
     * @return array{facilities: int, card_types: int, installed: int, skipped: bool}
     */
    public function handle(Game $game): array
    {
        if ($game->facilities()->exists()) {
            return ['facilities' => 0, 'card_types' => 0, 'installed' => 0, 'skipped' => true];
        }

        return DB::transaction(function () use ($game): array {
            $cardTypes = $this->createCatalogue($game);

            $facilities = 0;
            $installed = 0;

            foreach ($game->corporations()->orderBy('name')->get() as $corporation) {
                foreach ($this->startingFacilities($corporation) as $facility) {
                    $facilities++;
                    $installed += $this->installBasicCards($facility);
                }
            }

            return [
                'facilities' => $facilities,
                'card_types' => $cardTypes,
                'installed' => $installed,
                'skipped' => false,
            ];
        });
    }

    /**
     * Write the catalogue Security players are handed at the start of the game
     * (rulebook 3.3.3).
     *
     * Existing entries are left as they are: Control may already have edited a
     * card, and the catalogue is theirs once the game exists.
     */
    private function createCatalogue(Game $game): int
    {
        $created = 0;

        foreach ($this->configuredList('running_hot.protection_cards') as $card) {
            $name = (string) $card['name'];
            unset($card['name']);

            $cardType = $game->protectionCardTypes()->firstOrCreate(
                ['name' => $name],
                [
                    'kind' => ProtectionKind::Physical,
                    'challenge_skill' => RunnerSkill::Brawn,
                    'availability' => ProtectionCardAvailability::Available,
                    ...$card,
                ],
            );

            if ($cardType->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * The Facilities this Corporation opens with, from its briefing.
     *
     * Counts and types come from the briefings and are not uniform: a second
     * Security Facility widens every one of that Corporation's stacks, and a
     * third Corporate Facility raises what every one of its Facilities can
     * store.
     *
     * Each one is named in the configuration, because a Facility's name is what
     * players call it all game and "Gordon Corporate 2" is a label rather than a
     * name. An entry with no name falls back to the Corporation's short name
     * and the type, so a Corporation added later still gets something usable.
     *
     * @return array<int, Facility>
     */
    private function startingFacilities(Corporation $corporation): array
    {
        $types = $corporation->game->facilityTypes()->get()->keyBy('key');
        $facilities = [];
        $unnamed = [];

        foreach ($this->configuredFacilities($corporation->name) as $planned) {
            $type = $types->get((string) ($planned['type'] ?? ''));

            // A type Control has renamed away is skipped rather than invented:
            // the catalogue is theirs, and a Facility with no type is not a
            // Facility.
            if ($type === null) {
                continue;
            }

            $name = (string) ($planned['name'] ?? '');

            if ($name === '') {
                $unnamed[$type->key] = ($unnamed[$type->key] ?? 0) + 1;

                $name = sprintf('%s %s', $this->shortName($corporation->name), $type->name);

                if ($unnamed[$type->key] > 1) {
                    $name .= ' '.$unnamed[$type->key];
                }
            }

            /** @var Facility $facility */
            $facility = $corporation->facilities()->create([
                'game_id' => $corporation->game_id,
                'facility_type_id' => $type->id,
                'name' => $name,
                'available_from_turn' => Facility::FIRST_TURN,
            ]);

            $facilities[] = $facility;
        }

        return $facilities;
    }

    /**
     * The configured Facilities for one Corporation.
     *
     * A Corporation the configuration says nothing about opens with none,
     * rather than with a guessed set.
     *
     * @return array<int, array<string, mixed>>
     */
    private function configuredFacilities(string $corporationName): array
    {
        foreach ($this->configuredList('running_hot.corporations') as $configured) {
            if (($configured['name'] ?? null) !== $corporationName) {
                continue;
            }

            /** @var array<int, array<string, mixed>> $facilities */
            $facilities = $configured['facilities'] ?? [];

            return $facilities;
        }

        return [];
    }

    /**
     * Install the basic cards every Facility opens with.
     *
     * Installed in configured order, and installing puts each card in front of
     * the one before it, so the last name in the list is the one Runners meet
     * first.
     */
    private function installBasicCards(Facility $facility): int
    {
        $installed = 0;

        /** @var array<int, string> $names */
        $names = config('running_hot.installed_in_each', []);

        foreach ($names as $name) {
            /** @var ProtectionCardType|null $cardType */
            $cardType = $facility->game->protectionCardTypes()->where('name', $name)->first();

            if ($cardType === null) {
                continue;
            }

            $this->defence->install($facility, $cardType);
            $installed++;
        }

        return $installed;
    }

    /**
     * The first word of a Corporation's name, which is what people call it.
     *
     * "McCullough Calibrated Mechanical Armoury" is nobody's idea of a Facility
     * name; "McCullough Armoury" is.
     */
    private function shortName(string $name): string
    {
        return Str::before($name, ' ');
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
