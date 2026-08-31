<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgendaCardRequest;
use App\Models\AgendaCard;
use App\Services\CouncilService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Custom agenda cards, as players write them (rulebook 3.1.3).
 *
 * The handshake the rulebook describes is three steps and all three are here:
 * the player writes the card, Control adds its remarks and gives it back, and
 * then - only if the player still wants to - the player submits it to the
 * Chair. Control's step is Control's own route; the two either side are the
 * author's, which is why a card stays editable while it is in their hands and
 * stops being so the moment it is not.
 */
class AgendaCardController extends Controller
{
    public function __construct(private readonly CouncilService $council) {}

    public function store(StoreAgendaCardRequest $request): RedirectResponse
    {
        $card = $this->council->draftCustomCard(
            $request->author(),
            $request->string('title')->toString(),
            $request->input('body'),
            $request->resolutions(),
        );

        return back()->with('status', sprintf('%s is drafted. Give it to Control when it is ready.', $card->title));
    }

    public function update(AgendaCard $card, StoreAgendaCardRequest $request): RedirectResponse
    {
        Gate::authorize('update', $card);

        $this->council->reviseCard(
            $card,
            $request->string('title')->toString(),
            $request->input('body'),
            $request->resolutions(),
        );

        return back()->with('status', $card->title.' rewritten.');
    }

    /**
     * Give the card to Control, who adds any additional remarks.
     */
    public function submitToControl(Request $request, AgendaCard $card): RedirectResponse
    {
        Gate::authorize('submit', $card);

        $this->council->submitToControl($card);

        return back()->with('status', $card->title.' is with Control.');
    }

    /**
     * Once the player and Control agree, the player submits it to the Chair.
     */
    public function submitToChair(Request $request, AgendaCard $card): RedirectResponse
    {
        Gate::authorize('submit', $card);

        $this->council->submitToChair($card);

        return back()->with('status', $card->title.' is with the Chair.');
    }
}
