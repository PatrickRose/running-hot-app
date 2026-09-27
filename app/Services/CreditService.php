<?php

namespace App\Services;

use App\Enums\CharacterRole;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Players paying each other.
 *
 * A megagame runs on money changing hands - a Corporation hiring a gang, a
 * Runner settling a debt, a gang splitting a haul - and the rulebook prices
 * none of it, because it is a conversation. So this is a research point trade
 * (3.2.5) with Credits in it: one-way and one-sided, one purse goes down and
 * the other goes up, and whatever came back is settled at the table.
 *
 * There are two kinds of purse, and a seat spends exactly one of them. A Runner
 * or a Freelancer carries Credits of their own; a Corporate seat spends the
 * Corporation's, and of those seats only the CEO may hand them out - a purse
 * two people can spend out of is a purse neither can plan with, and Security
 * already has the budget and the shop to spend it on. Press and HM Government
 * carry no Credits at all (CharacterRole::carriesOwnTrackers), so they have
 * nothing to give and nowhere to put what they are given.
 *
 * Both movements go through TrackerService, each naming the other side, so the
 * ledger reads the trade from either end.
 */
class CreditService
{
    public function __construct(private TrackerService $trackers) {}

    /**
     * The purse a seat pays out of, or null for a seat with none.
     */
    public function purseFor(Character $character): Character|Corporation|null
    {
        if ($character->role->carriesOwnTrackers()) {
            return $character;
        }

        if ($character->role === CharacterRole::Ceo) {
            return $character->corporation;
        }

        return null;
    }

    /**
     * Move Credits out of the purse this seat spends into somebody else's.
     */
    public function give(Character $from, Character|Corporation $to, int $amount, ?User $actor = null): void
    {
        $payer = $this->purseFor($from);

        if ($payer === null) {
            throw ValidationException::withMessages([
                'from_character_id' => sprintf('%s has no Credits of their own to give.', $from->name),
            ]);
        }

        if ($to instanceof Character && ! $to->role->carriesOwnTrackers()) {
            throw ValidationException::withMessages([
                'to_id' => $to->corporation === null
                    ? sprintf('%s carries no Credits.', $to->name)
                    : sprintf('%s spends %s\'s Credits - pay the Corporation instead.', $to->name, $to->corporation->name),
            ]);
        }

        if ($payer->is($to)) {
            throw ValidationException::withMessages([
                'to_id' => 'Those Credits are already theirs.',
            ]);
        }

        if ($payer->game_id !== $to->game_id) {
            throw ValidationException::withMessages([
                'to_id' => sprintf('%s is in a different game.', $to->name),
            ]);
        }

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => 'Give at least one Credit.',
            ]);
        }

        DB::transaction(function () use ($payer, $to, $amount, $actor): void {
            // Read under the lock rather than off the model: a purse loaded
            // before the last write would let the same Credits be given twice,
            // and Credits have no floor to stop them going negative.
            $held = (int) $payer->newQuery()->whereKey($payer->getKey())->lockForUpdate()->value('credits');

            if ($held < $amount) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        '%s has %d %s.',
                        $payer->name,
                        $held,
                        $held === 1 ? 'Credit' : 'Credits',
                    ),
                ]);
            }

            $this->trackers->adjust(
                $payer,
                $this->trackerFor($payer),
                -$amount,
                sprintf('Gave %d to %s', $amount, $to->name),
                $actor,
            );

            $this->trackers->adjust(
                $to,
                $this->trackerFor($to),
                $amount,
                sprintf('Given %d by %s', $amount, $payer->name),
                $actor,
            );
        });
    }

    private function trackerFor(Character|Corporation $purse): Tracker
    {
        return $purse instanceof Corporation ? Tracker::CorporationCredits : Tracker::CharacterCredits;
    }
}
