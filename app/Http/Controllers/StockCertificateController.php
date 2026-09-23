<?php

namespace App\Http\Controllers;

use App\Enums\StockCertificateOption;
use App\Models\Character;
use App\Models\StockCertificate;
use App\Services\StockCertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A holder cashing a Stock Certificate in, or handing it on (rulebook 3.4.3).
 *
 * Thin, like every player-facing route: whether the certificate is yours is
 * App\Policies\StockCertificatePolicy's, and what cashing it pays is
 * App\Services\StockCertificateService's. Control reaches both through the
 * policy's before(), so a certificate can be cashed on a player's behalf.
 */
class StockCertificateController extends Controller
{
    public function __construct(private readonly StockCertificateService $certificates) {}

    public function cashIn(Request $request, StockCertificate $certificate): RedirectResponse
    {
        Gate::authorize('cashIn', $certificate);

        $validated = $request->validate([
            'option' => ['required', Rule::enum(StockCertificateOption::class)],
        ]);

        $option = StockCertificateOption::from($validated['option']);

        $cashed = $this->certificates->cashIn($certificate, $option, $request->user());

        return back()->with('status', sprintf(
            '%s cashes a %s Stock Certificate in for %d Credit%s%s.',
            $cashed->cashedBy->name ?? 'Somebody',
            $cashed->corporation->name,
            $cashed->credits_paid,
            $cashed->credits_paid === 1 ? '' : 's',
            $option->incomeReduction() > 0
                ? sprintf(', and %s\'s Income falls by %d', $cashed->corporation->name, $option->incomeReduction())
                : '',
        ));
    }

    public function give(Request $request, StockCertificate $certificate): RedirectResponse
    {
        Gate::authorize('give', $certificate);

        $validated = $request->validate([
            'to_character_id' => [
                'required', 'integer',
                Rule::exists('characters', 'id')->where('game_id', $certificate->game_id),
            ],
        ]);

        /** @var Character $to */
        $to = Character::query()->findOrFail($validated['to_character_id']);

        $from = $certificate->holder;

        $this->certificates->transfer($certificate, $to);

        return back()->with('status', sprintf(
            '%s hands %s a %s Stock Certificate.',
            $from->name ?? 'Control',
            $to->name,
            $certificate->corporation->name,
        ));
    }
}
