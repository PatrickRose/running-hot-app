<?php

namespace App\Support;

use App\Enums\StockCertificateOption;
use App\Models\Game;
use App\Models\StockCertificate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * What a Stock Certificate looks like to whoever may read it.
 *
 * The tier line is the Equipment page's: a player reads the certificates in
 * the hands they hold, and Control reads every one in the game - cashed ones
 * included, because "where did that Income go?" is a question Control gets
 * asked. What each option would pay is worked out here from the Corporation's
 * Income as it stands, which 2.3.1 makes public knowledge, so a holder can see
 * what they are choosing between before they choose.
 */
class StockCertificatePresenter
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forGame(Game $game, ?User $viewer = null): array
    {
        $query = $game->stockCertificates()
            ->with(['game', 'corporation', 'holder', 'cashedBy'])
            ->orderBy('id');

        if ($viewer !== null && ! $viewer->isControlFor($game)) {
            $query->whereNull('cashed_at')
                ->whereHas('holder', fn ($holder) => $holder->where('user_id', $viewer->id));
        }

        return $query->get()
            ->map(fn (StockCertificate $certificate): array => $this->certificate($certificate, $viewer))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function certificate(StockCertificate $certificate, ?User $viewer = null): array
    {
        $income = $certificate->corporation->income;

        return [
            'id' => $certificate->id,
            'corporation' => [
                'id' => $certificate->corporation_id,
                ...FactionBadge::for($certificate->corporation->name),
                'income' => $income,
            ],
            'holder_character_id' => $certificate->character_id,
            'holder_name' => $certificate->holder?->name,
            'text' => StockCertificate::TEXT,
            'options' => array_map(
                fn (StockCertificateOption $option): array => [
                    'value' => $option->value,
                    'label' => $option->label(),
                    'credits' => $option->creditsFor($income),
                    'income_reduction' => $option->incomeReduction(),
                ],
                StockCertificateOption::cases(),
            ),
            'cashed' => $certificate->isCashed(),
            'cashed_as' => $certificate->cashed_as?->label(),
            'credits_paid' => $certificate->credits_paid,
            'cashed_by' => $certificate->cashedBy?->name,
            'cashed_at' => $certificate->cashed_at?->toIso8601String(),
            'can_cash' => $viewer !== null && ! $certificate->isCashed()
                && Gate::forUser($viewer)->allows('cashIn', $certificate),
            'can_give' => $viewer !== null && ! $certificate->isCashed()
                && Gate::forUser($viewer)->allows('give', $certificate),
        ];
    }
}
