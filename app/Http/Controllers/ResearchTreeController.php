<?php

namespace App\Http\Controllers;

use App\Enums\CharacterRole;
use App\Enums\PhaseType;
use App\Http\Requests\Research\CustomiseDeckRequest;
use App\Http\Requests\Research\ResearchTechnologyRequest;
use App\Http\Requests\Research\TransferResearchPointsRequest;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Models\TechnologyHolding;
use App\Models\TechnologyType;
use App\Services\ResearchTableService;
use App\Services\TechnologyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Spending Research Points (rulebook 3.2.2, 3.2.3, 3.2.5).
 *
 * Climbing the tree and customising a deck both happen during the Setup phase,
 * and that is enforced here rather than in the service: the rule belongs to the
 * clock rather than to the tree, and Control - who has to be able to settle a
 * ruling at any point in the evening - reaches the same service through its own
 * routes without it. That is the shape TrackerController::removeTag already
 * uses for the Tag rule.
 *
 * Trading points is not tied to a phase. 3.2.5 has players trading "however
 * they wish", and a trade agreed during a Run is still a trade.
 */
class ResearchTreeController extends Controller
{
    public function __construct(
        private readonly TechnologyService $technologies,
        private readonly ResearchTableService $table,
    ) {}

    /**
     * Buy a technology off the tree and house it (rulebook 3.2.2).
     */
    public function research(ResearchTechnologyRequest $request): RedirectResponse
    {
        $corporation = $this->corporationFor($request);

        $this->assertSetupPhase($corporation->game);

        /** @var TechnologyType $technology */
        $technology = $corporation->game->technologyTypes()
            ->findOrFail($request->integer('technology_type_id'));

        /** @var Facility $facility */
        $facility = $corporation->facilities()->findOrFail($request->integer('facility_id'));

        $claimId = $request->integer('technology_holding_id');

        /** @var TechnologyHolding|null $claim */
        $claim = $claimId === 0
            ? null
            : $corporation->technologyHoldings()->findOrFail($claimId);

        $this->technologies->research($corporation, $technology, $facility, $claim, $request->user());

        return back()->with('status', sprintf(
            '%s researched and housed in %s.',
            $technology->name,
            $facility->name,
        ));
    }

    /**
     * Buy a card into the deck (rulebook 3.2.3).
     */
    public function customiseDeck(CustomiseDeckRequest $request): RedirectResponse
    {
        $corporation = $this->corporationFor($request);

        $this->assertSetupPhase($corporation->game);

        /** @var TechnologyType $technology */
        $technology = $corporation->game->technologyTypes()
            ->findOrFail($request->integer('technology_type_id'));

        $card = $this->table->customiseDeck(
            $corporation,
            $technology,
            $request->suits(),
            $request->integer('value'),
            $request->user(),
        );

        return back()->with('status', sprintf(
            '%s shuffled into %s\'s research deck.',
            $card->label(),
            $corporation->name,
        ));
    }

    /**
     * Hand Research Points to another Corporation (rulebook 3.2.5).
     */
    public function transferPoints(TransferResearchPointsRequest $request): RedirectResponse
    {
        $corporation = $this->corporationFor($request);

        /** @var Corporation $recipient */
        $recipient = $corporation->game->corporations()->findOrFail($request->integer('corporation_id'));

        $amount = $request->integer('amount');

        $this->technologies->transferPoints(
            $corporation,
            $recipient,
            $request->suit(),
            $amount,
            $request->user(),
        );

        return back()->with('status', sprintf(
            '%d %s Research Point(s) handed to %s.',
            $amount,
            $request->suit()->label(),
            $recipient->name,
        ));
    }

    /**
     * The Corporation this player plays for.
     *
     * The request has already authorised it; this is the same lookup done
     * again for the model itself, which is cheap and keeps the controller
     * honest about what it is acting on.
     */
    private function corporationFor(Request $request): Corporation
    {
        $game = Game::current();

        abort_if($game === null, 404);

        $user = $request->user();
        abort_if($user === null, 403);

        $corporation = $user->corporationIn($game, CharacterRole::Research)
            ?? $user->corporationIn($game);

        abort_if($corporation === null, 403);

        return $corporation;
    }

    /**
     * "During the Setup Phase, you may spend the Research Points" (3.2.2).
     */
    private function assertSetupPhase(Game $game): void
    {
        if ($game->currentPhase()?->type !== PhaseType::Setup) {
            throw ValidationException::withMessages([
                'technology_type_id' => 'Research Points are spent during the Setup phase.',
            ]);
        }
    }
}
