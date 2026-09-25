<?php

namespace App\Support;

use App\Models\Corporation;
use App\Models\FacilityType;
use App\Models\TechnologyHolding;
use App\Services\TechnologyService;
use Illuminate\Support\Collection;

/**
 * Which Facility types a Corporation may build (rulebook 3.3.1).
 *
 * Research, Security and Corporate are every Corporation's from the start -
 * `available_from_start` on the type. Every other type is unlocked by a
 * technology ("Unlock: Arms facility"), so it is on a Corporation's list once
 * that technology is working for it, and not before: a claimed copy is paper,
 * and a thief needs every piece of a split technology (3.2.7), which is
 * TechnologyService::isUsable()'s question rather than this one's.
 *
 * A type Control invents is on nobody's list until Control opens it to every
 * Corporation or writes a technology that unlocks it. This is only the players'
 * list: Control builds any type for anybody from its own panel.
 */
class BuildableFacilityTypes
{
    public function __construct(private readonly TechnologyService $technologies) {}

    /**
     * @return Collection<int, FacilityType>
     */
    public function for(Corporation $corporation): Collection
    {
        $unlocking = $corporation->technologyHoldings()
            ->researched()
            ->with(['technologyType', 'corporation'])
            ->get()
            ->filter(fn (TechnologyHolding $holding): bool => $this->technologies->isUsable($holding))
            ->map(fn (TechnologyHolding $holding) => $holding->technologyType)
            ->unique('id');

        return $corporation->game->facilityTypes()
            ->orderBy('build_cost')
            ->orderBy('name')
            ->get()
            ->filter(fn (FacilityType $type): bool => $type->available_from_start
                || $unlocking->contains(fn ($technology): bool => $type->isUnlockedBy($technology)))
            ->values();
    }

    public function allows(Corporation $corporation, FacilityType $type): bool
    {
        return $this->for($corporation)->contains('id', $type->id);
    }
}
