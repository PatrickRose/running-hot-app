<?php

namespace App\Actions;

use App\Enums\PhaseType;
use App\Enums\Tracker;
use App\Models\Phase;
use App\Models\User;
use App\Services\TrackerService;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The mechanical half of Team Time (rulebook 2.3): pay each Corporation its
 * Income, and heal one Wound for free on every Runner and Freelancer.
 *
 * Deliberately not automated here:
 *
 * - Setting Income itself. Income is the abstraction of a corporation's stock
 *   price rather than something derived from it, so it moves for narrative
 *   reasons only Control can judge. This action just pays out whatever Income
 *   Control has recorded.
 * - Tag removal. It costs 3 Credits and is the player's choice, not automatic.
 * - Political Will, Notoriety, Stability and Civil Unrest, all of which move for
 *   narrative reasons that only Control can judge.
 */
class ApplyTeamTimeUpkeep
{
    public function __construct(private readonly TrackerService $trackers) {}

    /**
     * @return array{income: array<string, int>, healed: array<string, int>, skipped: bool}
     */
    public function handle(Phase $phase, ?User $actor = null): array
    {
        if ($phase->type !== PhaseType::TeamTime) {
            throw new InvalidArgumentException('Upkeep may only be applied during a Team Time phase.');
        }

        if ($phase->upkeep_applied_at !== null) {
            return ['income' => [], 'healed' => [], 'skipped' => true];
        }

        $game = $phase->game();
        $turnNumber = $phase->turn->number;

        $income = [];

        foreach ($game->corporations()->orderBy('name')->get() as $corporation) {
            if ($corporation->income === 0) {
                continue;
            }

            $this->trackers->adjust(
                $corporation,
                Tracker::CorporationCredits,
                $corporation->income,
                sprintf('Turn %d income', $turnNumber),
                $actor,
                automated: true,
            );

            $income[$corporation->name] = $corporation->income;
        }

        $healed = [];

        foreach ($game->characters()->where('wounds', '>', 0)->orderBy('name')->get() as $character) {
            if (! $character->role->healsDuringTeamTime()) {
                continue;
            }

            $this->trackers->adjust(
                $character,
                Tracker::Wounds,
                -1,
                sprintf('Turn %d Team Time: free Wound recovery', $turnNumber),
                $actor,
                automated: true,
            );

            $healed[$character->name] = 1;
        }

        $phase->forceFill(['upkeep_applied_at' => Carbon::now()])->save();

        return ['income' => $income, 'healed' => $healed, 'skipped' => false];
    }
}
