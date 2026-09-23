<?php

namespace App\Services;

use App\Enums\StockCertificateOption;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\StockCertificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock Certificates: handed out, handed on, and cashed in (rulebook 3.4.3).
 *
 * The one writer of `stock_certificates`, the way EquipmentService is of
 * `equipment_holdings`. Everything a certificate moves - the holder's Credits
 * and the issuing Corporation's Income - goes through TrackerService, so
 * "why did DTC's Income drop?" is answered by the ledger like every other
 * number.
 */
class StockCertificateService
{
    public function __construct(private readonly TrackerService $trackers) {}

    /**
     * Control handing a Runner the certificate they took out of a Facility.
     *
     * Any character may be handed one rather than only a Runner, because 3.4.3
     * has certificates being "sold to Corporations or other characters" and
     * Control may need to put one straight where it ended up.
     */
    public function issue(Corporation $corporation, Character $holder): StockCertificate
    {
        if ($corporation->game_id !== $holder->game_id) {
            throw ValidationException::withMessages([
                'character_id' => sprintf('%s is playing a different game.', $holder->name),
            ]);
        }

        return StockCertificate::query()->create([
            'game_id' => $corporation->game_id,
            'corporation_id' => $corporation->id,
            'character_id' => $holder->id,
        ]);
    }

    /**
     * Hand a certificate to somebody else.
     *
     * The other half of "can be sold to Corporations or other characters". As
     * with Equipment, only the certificate travels: whatever was agreed for it
     * is settled at the table, so nobody's purse is reached into on the
     * strength of a price only one side typed in.
     */
    public function transfer(StockCertificate $certificate, Character $to): StockCertificate
    {
        if ($to->game_id !== $certificate->game_id) {
            throw ValidationException::withMessages([
                'to_character_id' => sprintf('%s is playing a different game.', $to->name),
            ]);
        }

        return DB::transaction(function () use ($certificate, $to): StockCertificate {
            /** @var StockCertificate $locked */
            $locked = StockCertificate::query()->lockForUpdate()->findOrFail($certificate->id);

            $this->requireUncashed($locked);

            if ($locked->character_id === $to->id) {
                throw ValidationException::withMessages([
                    'to_character_id' => sprintf('%s is already holding it.', $to->name),
                ]);
            }

            $locked->forceFill(['character_id' => $to->id])->save();

            return $locked;
        });
    }

    /**
     * Cash a certificate in, once.
     *
     * The issuing Corporation's Income is read fresh at the moment of cashing,
     * inside the lock, so two certificates cashed together each see the Income
     * the other left behind.
     *
     * The Credits are paid to whoever is holding it - into their own purse for
     * a Runner or anybody else with one, and into their Corporation's Credits
     * for a Corporate seat, because a Corporate seat spends its Corporation's
     * money rather than a purse of its own. Nothing is taken out of the issuing
     * Corporation's Credits: the card pays a share *of its Income*, which is the
     * abstraction of its stock price rather than money in its vault.
     */
    public function cashIn(
        StockCertificate $certificate,
        StockCertificateOption $option,
        ?User $actor = null,
    ): StockCertificate {
        return DB::transaction(function () use ($certificate, $option, $actor): StockCertificate {
            /** @var StockCertificate $locked */
            $locked = StockCertificate::query()
                ->with(['corporation', 'holder.corporation'])
                ->lockForUpdate()
                ->findOrFail($certificate->id);

            $this->requireUncashed($locked);

            $holder = $locked->holder;

            if ($holder === null) {
                throw ValidationException::withMessages([
                    'certificate' => 'Nobody is holding that certificate to cash it in.',
                ]);
            }

            /** @var Corporation $issuer */
            $issuer = Corporation::query()->lockForUpdate()->findOrFail($locked->corporation_id);

            $credits = $option->creditsFor($issuer->income);

            $reason = sprintf('Cashed a %s Stock Certificate (%s)', $issuer->name, strtolower($option->label()));

            if ($credits > 0) {
                $payee = $this->payeeFor($holder);

                $this->trackers->adjust(
                    $payee,
                    $payee instanceof Corporation ? Tracker::CorporationCredits : Tracker::CharacterCredits,
                    $credits,
                    $payee instanceof Corporation ? sprintf('%s, for %s', $reason, $holder->name) : $reason,
                    $actor,
                );
            }

            if ($option->incomeReduction() > 0) {
                $this->trackers->adjust(
                    $issuer,
                    Tracker::Income,
                    -$option->incomeReduction(),
                    sprintf('%s cashed a Stock Certificate in', $holder->name),
                    $actor,
                );
            }

            $locked->forceFill([
                'cashed_as' => $option,
                'credits_paid' => $credits,
                'cashed_at' => now(),
                'cashed_by_character_id' => $holder->id,
            ])->save();

            return $locked;
        });
    }

    /**
     * Take back a certificate that was handed out by mistake.
     *
     * Refused once it has been cashed, because the Credits and the Income it
     * moved are in the ledger and deleting the row would leave them there
     * unexplained. Control undoes those with the tracker controls instead.
     */
    public function revoke(StockCertificate $certificate): void
    {
        $this->requireUncashed($certificate);

        $certificate->delete();
    }

    protected function payeeFor(Character $holder): Character|Corporation
    {
        if ($holder->role->isCorporate() && $holder->corporation !== null) {
            return $holder->corporation;
        }

        return $holder;
    }

    protected function requireUncashed(StockCertificate $certificate): void
    {
        if ($certificate->isCashed()) {
            throw ValidationException::withMessages([
                'certificate' => 'That certificate has already been cashed in.',
            ]);
        }
    }
}
