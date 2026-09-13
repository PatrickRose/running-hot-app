<?php

namespace App\Actions;

use App\Enums\TechnologyHoldingStatus;
use App\Enums\TechnologyOrigin;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use App\Services\TechnologyService;
use App\Support\TechnologyBlueprint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Give every Corporation the technologies it opens the game already holding
 * (rulebook 3.2.2).
 *
 * Twenty of them across the five Corporations: ANT's four pieces of Power,
 * DTC's four of Arms, Genetic Equity's four of Miracle Genetics, McCullough's
 * Factory and Construction Leader, and Gordon's three plot hooks. Which ones
 * is the `starting` column on the tree rather than a list here, so Control can
 * mark one on a Corporation they invented.
 *
 * A starting position rather than a change, on the same terms as the Facilities
 * these are housed in: the cards are free, nothing goes through TrackerService,
 * and no Research Points move because none were ever spent. What is *not*
 * skipped is where they go - 3.2.2 houses every technology in a Facility, and a
 * starting card that sat nowhere would leave the storage count wrong from the
 * first turn.
 *
 * Origin is Researched, which matters more than it looks: 3.2.7 lets the
 * Corporation that owns a split technology work it holding any single piece, and
 * that only follows from having researched it rather than having copied it. ANT
 * opens with Power working.
 *
 * Runs after {@see CreateDefaultFacilities}, because there has to be somewhere
 * to put them. Idempotent: a Corporation that already holds a card is left
 * alone, so re-running cannot hand out a second Power.
 */
class GrantStartingTechnologies
{
    public function __construct(private readonly TechnologyService $technologies) {}

    /**
     * @return array{granted: int, unhoused: array<int, string>}
     */
    public function handle(Game $game): array
    {
        $granted = 0;
        $unhoused = [];

        foreach ($game->corporations()->orderBy('name')->get() as $corporation) {
            foreach ($this->startingTechnologies($corporation) as $technology) {
                if ($this->alreadyHeld($corporation, $technology)) {
                    continue;
                }

                $facility = $this->facilityFor($corporation, $technology);

                if ($facility === null) {
                    // Nowhere legal to put it, which is a roster Control built
                    // rather than an error: a Corporation with no Corporate
                    // Facility can store nothing at all. Reported so whoever
                    // set the game up can see it, never thrown - a game must
                    // still be creatable.
                    $unhoused[] = $corporation->name.': '.$technology->name;

                    continue;
                }

                $this->grant($corporation, $technology, $facility);
                $granted++;
            }
        }

        return ['granted' => $granted, 'unhoused' => $unhoused];
    }

    /**
     * The starting technologies on one Corporation's tree, plus any on the
     * common set - a technology every Corporation opens with is a thing Control
     * might write, even though the game's own twenty are all on one tree each.
     *
     * An unattached technology is not a common one, which is the trap here. A
     * tree whose Corporation this game does not have keeps a null
     * corporation_id (see {@see SeedTechnologies}), so matching on null alone
     * would hand every Corporation in a custom roster all four pieces of Power,
     * all four of Arms and everything else nobody claimed. The common set is
     * the one that says so on the tree column.
     *
     * @return Collection<int, TechnologyType>
     */
    private function startingTechnologies(Corporation $corporation): Collection
    {
        return $corporation->game
            ->technologyTypes()
            ->where('starting', true)
            ->where(fn ($query) => $query
                ->where('corporation_id', $corporation->id)
                ->orWhere(fn ($common) => $common
                    ->whereNull('corporation_id')
                    ->where('tree', TechnologyBlueprint::COMMON)))
            ->with('requiredFacilityType')
            ->orderBy('code')
            ->get();
    }

    private function alreadyHeld(Corporation $corporation, TechnologyType $technology): bool
    {
        return $corporation->technologyHoldings()
            ->where('technology_type_id', $technology->id)
            ->exists();
    }

    /**
     * Where to put one, or null when there is nowhere it may legally go.
     *
     * The type the card names comes first, because most cards name none and the
     * two that do have exactly one Facility to go in. Among the Facilities that
     * will take it, the emptiest wins: spreading them keeps a Corporation's
     * storage open for the technologies it researches on the night, and stops
     * four pieces of Power sitting in one building for a Runner to take in a
     * single Run.
     */
    private function facilityFor(Corporation $corporation, TechnologyType $technology): ?Facility
    {
        $required = $technology->required_facility_type_id;
        $turn = $corporation->game->currentTurn()?->number;

        $candidates = $corporation->facilities()
            ->when($required !== null, fn ($query) => $query->where('facility_type_id', $required))
            ->orderBy('name')
            ->get()
            ->filter(fn (Facility $facility): bool => $facility->isAvailableOnTurn($turn))
            ->filter(fn (Facility $facility): bool => $this->technologies->storedIn($facility)
                < $this->technologies->capacityFor($facility));

        return $candidates
            ->sortBy(fn (Facility $facility): int => $this->technologies->storedIn($facility))
            ->first();
    }

    private function grant(Corporation $corporation, TechnologyType $technology, Facility $facility): void
    {
        TechnologyHolding::create([
            'game_id' => $corporation->game_id,
            'corporation_id' => $corporation->id,
            'technology_type_id' => $technology->id,
            'facility_id' => $facility->id,
            'status' => TechnologyHoldingStatus::Researched,
            'origin' => TechnologyOrigin::Researched,
            'discount_percent' => 0,
            'researched_at' => Carbon::now(),
        ]);
    }
}
