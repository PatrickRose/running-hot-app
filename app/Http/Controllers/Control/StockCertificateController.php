<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\StockCertificate;
use App\Services\StockCertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Control handing out a Stock Certificate, and taking one back.
 *
 * A Runner spends an access on a Corporate Facility's own effect and chooses a
 * certificate (3.4.3); Control hands it over here. Cashing it in and handing
 * it on are the holder's, on their own routes - which Control reaches too.
 */
class StockCertificateController extends Controller
{
    public function __construct(private readonly StockCertificateService $certificates) {}

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'corporation_id' => [
                'required', 'integer',
                Rule::exists('corporations', 'id')->where('game_id', $game->id),
            ],
            'character_id' => [
                'required', 'integer',
                Rule::exists('characters', 'id')->where('game_id', $game->id),
            ],
        ]);

        /** @var Corporation $corporation */
        $corporation = $game->corporations()->findOrFail($validated['corporation_id']);

        /** @var Character $holder */
        $holder = $game->characters()->findOrFail($validated['character_id']);

        $this->certificates->issue($corporation, $holder);

        return back()->with('status', sprintf(
            '%s takes a %s Stock Certificate.',
            $holder->name,
            $corporation->name,
        ));
    }

    public function destroy(Game $game, StockCertificate $certificate): RedirectResponse
    {
        abort_unless($certificate->game_id === $game->id, 404);

        $this->certificates->revoke($certificate);

        return back()->with('status', 'Stock Certificate taken back.');
    }
}
