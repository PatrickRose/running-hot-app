<?php

namespace App\Policies;

use App\Models\StockCertificate;
use App\Models\User;

/**
 * Who may do what with a Stock Certificate.
 *
 * `CharacterPolicy::giveEquipment`'s division, a table along: this answers
 * *who*, and App\Services\StockCertificateService answers what the rules
 * allow - so a certificate already cashed is refused there, to Control as well.
 */
class StockCertificatePolicy
{
    /**
     * Control reaches every certificate in a game it is running.
     */
    public function before(User $user, string $ability, ?StockCertificate $certificate = null): ?bool
    {
        $isControl = $certificate === null
            ? $user->isControl()
            : $user->isControlFor($certificate->game);

        return $isControl ? true : null;
    }

    /**
     * Cash it in, which is its holder's decision and nobody else's.
     *
     * Asks the game's clock because cashing is an act, where reading the
     * certificate in your hand is not - the division "A game off the clock"
     * draws everywhere else.
     */
    public function cashIn(User $user, StockCertificate $certificate): bool
    {
        return $this->holds($user, $certificate);
    }

    /**
     * Hand it to somebody else, which is how one is sold (3.4.3).
     */
    public function give(User $user, StockCertificate $certificate): bool
    {
        return $this->holds($user, $certificate);
    }

    protected function holds(User $user, StockCertificate $certificate): bool
    {
        if (! $certificate->game->isRunning()) {
            return false;
        }

        return $certificate->holder !== null
            && $certificate->holder->user_id === $user->id;
    }
}
