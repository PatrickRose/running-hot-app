<?php

namespace App\Support;

use App\Actions\RollDice;
use App\Models\DiceRoll;
use App\Models\Game;
use App\Models\User;

/**
 * What a roll looks like on the page, to whoever may read it.
 *
 * Two readers and no more: the player who rolled, and Control. The results are
 * shared with Control rather than posted anywhere the rest of the game reads,
 * because a roll is evidence for a ruling and the ruling is Control's - so the
 * narrowing is here, on the query, and a roll that is not yours never reaches
 * the browser.
 */
class DiceRollPresenter
{
    /** How many rolls either page carries. */
    public const RECENT = 50;

    /**
     * The rolls this viewer may read, newest first.
     *
     * Control reads every roll in the game; a player reads their own.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(Game $game, User $viewer): array
    {
        $query = $game->diceRolls()
            ->with(['user', 'character'])
            ->latest('id')
            ->limit(self::RECENT);

        if (! $viewer->isControlFor($game)) {
            $query->where('user_id', $viewer->id);
        }

        return $query->get()
            ->map(fn (DiceRoll $roll): array => $this->roll($roll))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function roll(DiceRoll $roll): array
    {
        return [
            'id' => $roll->id,
            'd6' => $roll->d6,
            'd8' => $roll->d8,
            'faces' => [
                'd6' => array_values($roll->faces['d6'] ?? []),
                'd8' => array_values($roll->faces['d8'] ?? []),
            ],
            'successes' => $roll->successes,
            'success_on' => RollDice::SUCCESS_ON,
            'purpose' => $roll->purpose,
            'character_name' => $roll->character?->name,
            'user_name' => $roll->user?->name,
            'rolled_at' => $roll->created_at?->toIso8601String(),
        ];
    }
}
