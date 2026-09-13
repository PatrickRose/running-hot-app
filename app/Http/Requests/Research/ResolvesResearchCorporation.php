<?php

namespace App\Http\Requests\Research;

use App\Enums\CharacterRole;
use App\Models\Corporation;
use App\Models\Game;

/**
 * Finding the Corporation whose research game a request is about.
 *
 * None of the player-facing research routes names a Corporation, and that is
 * deliberate: a player has exactly one, so asking them to send it would be
 * asking them for something the server already knows and would have to check
 * anyway. So the seat decides, exactly as it does on the Facility board.
 *
 * A player holding a Corporate seat that is not Research still resolves to
 * their Corporation - they simply fail the policy, and get told they may not
 * act rather than that they have no Corporation.
 */
trait ResolvesResearchCorporation
{
    private ?Corporation $researchCorporation = null;

    /**
     * The running game, which is the only one players ever act in.
     */
    protected function researchGame(): ?Game
    {
        return Game::current();
    }

    protected function researchCorporation(): ?Corporation
    {
        if ($this->researchCorporation !== null) {
            return $this->researchCorporation;
        }

        $game = $this->researchGame();
        $user = $this->user();

        if ($game === null || $user === null) {
            return null;
        }

        return $this->researchCorporation = $user->corporationIn($game, CharacterRole::Research)
            ?? $user->corporationIn($game);
    }

    protected function mayPlayResearch(): bool
    {
        $corporation = $this->researchCorporation();

        return $corporation !== null && ($this->user()?->can('research', $corporation) ?? false);
    }
}
