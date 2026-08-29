<?php

namespace App\Actions;

use App\Enums\ProtectionKind;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Models\ProtectionCardHolding;
use App\Models\ProtectionCardType;
use App\Services\FacilityDefenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gives every Corporation the Facilities and the Protection Cards it opens with,
 * as configured in config/running_hot.php.
 *
 * "Each Corporation will begin with a number of Facilities and some basic
 * Protection Cards" (rulebook 3.3), and until they exist there is nothing for
 * Runners to run against and nothing for Security to install into.
 *
 * The card catalogue itself is not written here - a game gets that when it is
 * created, along with the Facility types and the other two card lists, because
 * none of them depend on the roster. What this adds is the part that does: which
 * Corporation owns which Facilities, and which cards each Corporation was given.
 *
 * Runs after {@see CreateDefaultRoster}, because Facilities and holdings both
 * belong to Corporations. Like that action this only ever writes a starting
 * position, so the Facilities are built free and the cards installed free: there
 * is no before state for the ledger to record, and no CEO signed anything off.
 * The cards installed are still taken out of the Corporation's holdings, because
 * that is a card moving rather than a cost.
 *
 * A game that already has Facilities is left alone, so applying it twice cannot
 * hand a Corporation a second Armoury and quietly widen every stack in the
 * game.
 */
class CreateDefaultFacilities
{
    public function __construct(
        private readonly FacilityDefenceService $defence,
        private readonly SeedProtectionCardHoldings $holdings,
        private readonly SeedTechnologies $technologies,
    ) {}

    /**
     * @return array{facilities: int, holdings: int, installed: int, skipped: bool}
     */
    public function handle(Game $game): array
    {
        if ($game->facilities()->exists()) {
            return ['facilities' => 0, 'holdings' => 0, 'installed' => 0, 'skipped' => true];
        }

        return DB::transaction(function () use ($game): array {
            // The trees were written when the game was created, before there
            // were Corporations to attach them to. Now there are.
            $this->technologies->handle($game);

            // Before any Facility opens, because opening one hands cards out of
            // these holdings.
            $holdings = $this->holdings->handle($game);

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
                'holdings' => $holdings['holdings'],
                'installed' => $installed,
                'skipped' => false,
            ];
        });
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
     * Install the basic cards this Facility opens with (rulebook 3.3).
     *
     * Drawn from what the Corporation actually holds, which is the whole point:
     * ANT's Facilities open with ANT's own cards, and no Facility opens with a
     * card its Corporation was never given.
     *
     * The card with the most copies left goes in first. A Corporation holds four
     * copies of its commonest card and opens with four or five Facilities, so no
     * single card can cover them all - spreading across the cards it holds is
     * what lets every Facility open defended, and it satisfies the
     * one-copy-per-Facility rule of 3.3.4 without having to think about it.
     *
     * Running out is not an error. A Corporation whose briefing gave it fewer
     * cards than its Facilities need simply opens some of them thinner, which is
     * a starting position Control can see and top up.
     */
    private function installBasicCards(Facility $facility): int
    {
        $installed = 0;

        foreach ($this->configuredStack() as $kind => $wanted) {
            for ($slot = 0; $slot < $wanted; $slot++) {
                $cardType = $this->deepestHolding($facility, $kind);

                if ($cardType === null) {
                    break;
                }

                $this->defence->install($facility, $cardType);
                $installed++;
            }
        }

        return $installed;
    }

    /**
     * How many cards of each kind a starting Facility opens with.
     *
     * @return array<string, int>
     */
    private function configuredStack(): array
    {
        /** @var array<string, int> $stack */
        $stack = config('running_hot.installed_in_each', []);

        $ordered = [];

        // In encounter order, so a Facility with room for only some of them
        // opens with the ones Runners meet first.
        foreach (ProtectionKind::encounterOrder() as $kind) {
            $wanted = (int) ($stack[$kind->value] ?? 0);

            if ($wanted > 0) {
                $ordered[$kind->value] = $wanted;
            }
        }

        return $ordered;
    }

    /**
     * The card of one kind this Corporation has most copies of and has not
     * already put in this Facility.
     */
    private function deepestHolding(Facility $facility, string $kind): ?ProtectionCardType
    {
        /** @var Collection<int, int> $alreadyHere */
        $alreadyHere = $facility->protectionCards()->pluck('protection_card_type_id');

        $held = ProtectionCardHolding::query()
            ->where('corporation_id', $facility->corporation_id);

        /** @var ProtectionCardType|null $cardType */
        $cardType = ProtectionCardType::query()
            ->whereIn('id', (clone $held)
                ->where('copies', '>', 0)
                ->select('protection_card_type_id'))
            ->where('kind', $kind)
            ->whereNotIn('id', $alreadyHere)
            // Deepest hand first, so the load spreads across the cards a
            // Corporation holds rather than emptying one of them.
            ->orderByDesc((clone $held)
                ->whereColumn('protection_card_type_id', 'protection_card_types.id')
                ->select('copies'))
            // Ties broken by code so a game's starting position is the same
            // every time it is built.
            ->orderBy('code')
            ->first();

        return $cardType;
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
