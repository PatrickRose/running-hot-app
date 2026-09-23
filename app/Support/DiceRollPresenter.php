<?php

namespace App\Support;

use App\Actions\RollDice;
use App\Models\Character;
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
    /** How many rolls a player's own page carries. */
    public const RECENT = 50;

    /**
     * How many Control's log carries.
     *
     * More than a player's, because Control searches it - "when did Wicker
     * last roll?" - and a search over the last fifty is a search that misses.
     * The list is filtered in the browser, so this is the whole of what it can
     * find.
     */
    public const CONTROL_LOG = 500;

    /**
     * The rolls this viewer may read, newest first.
     *
     * Control reads every roll in the game; a player reads their own.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(Game $game, User $viewer): array
    {
        $isControl = $viewer->isControlFor($game);

        $query = $game->diceRolls()
            ->with(['user', 'character.gang', 'character.corporation', 'phase.turn'])
            ->latest('id')
            ->limit($isControl ? self::CONTROL_LOG : self::RECENT);

        if (! $isControl) {
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
            // Only the characters that are organisations have artwork of their
            // own, so this is null for almost everybody - the team's badge is
            // the one that is always drawn.
            'character_logo_path' => $roll->character === null ? null : LogoImage::pathFor($roll->character->name),
            'team' => $this->teamOf($roll->character),
            'turn' => $roll->phase?->turn->number,
            'phase_label' => $roll->phase?->type->label(),
            'user_name' => $roll->user?->name,
            'rolled_at' => $roll->created_at?->toIso8601String(),
        ];
    }

    /**
     * The gang or Corporation a character rolled for, as a badge.
     *
     * Null for somebody in neither - the press, HM Government, a Freelancer -
     * and for Control rolling as itself.
     *
     * @return array<string, mixed>|null
     */
    protected function teamOf(?Character $character): ?array
    {
        if ($character?->gang_id !== null) {
            return FactionBadge::for($character->gang->name);
        }

        if ($character?->corporation_id !== null) {
            return FactionBadge::for($character->corporation->name);
        }

        return null;
    }
}
