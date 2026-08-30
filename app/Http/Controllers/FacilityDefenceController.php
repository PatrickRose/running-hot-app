<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstallProtectionCardRequest;
use App\Http\Requests\ReorderProtectionCardsRequest;
use App\Models\Facility;
use App\Models\FacilityProtectionCard;
use App\Models\ProtectionCardType;
use App\Services\FacilityDefenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Security arranging their own Corporation's defences (rulebook 3.3.4).
 *
 * This used to be Control's alone. It is not any more: Security drags the cards
 * themselves, which is what they do at the table, and Control is left for the
 * rulings only Control can make. Nothing about the rules moved with it - every
 * write here goes through FacilityDefenceService, so a full stack is still
 * refused, a reorder is still charged, and every Credit still lands in the
 * tracker ledger with the Security player's name against it.
 *
 * What is new is who is asking, and that is the FacilityPolicy's business
 * rather than this controller's.
 */
class FacilityDefenceController extends Controller
{
    public function __construct(private readonly FacilityDefenceService $defence) {}

    /**
     * Drop a card from the Corporation's hand onto a Facility.
     *
     * It lands at position 1, the outermost slot, because that is what
     * installing means in 3.3.4. Putting it anywhere else is a reorder, and
     * costs like one.
     */
    public function install(Facility $facility, InstallProtectionCardRequest $request): RedirectResponse
    {
        /** @var ProtectionCardType $cardType */
        $cardType = ProtectionCardType::query()
            ->where('game_id', $facility->game_id)
            ->findOrFail($request->integer('protection_card_type_id'));

        $this->defence->install($facility, $cardType);

        return back()->with('status', sprintf(
            '%s installed at the outermost %s slot of %s.',
            $cardType->name,
            $cardType->kind->label(),
            $facility->name,
        ));
    }

    /**
     * What an arrangement would cost, so the board can say so before committing.
     *
     * Answered by the server rather than worked out in the browser: this is the
     * one place the reorder rule lives, and the commit below quotes itself from
     * the same method. A cost shown and a cost charged that disagree would be a
     * worse bug than showing nothing.
     *
     * A GET, and it writes nothing. The board asks this every time a card moves,
     * so it has to be cheap and it has to be safe to repeat.
     */
    public function quote(Facility $facility, ReorderProtectionCardsRequest $request): JsonResponse
    {
        return response()->json(
            $this->defence->quoteReorder($facility, $request->kind(), $request->order()),
        );
    }

    /**
     * Commit an arrangement, charging for the cards that had to move.
     */
    public function reorder(Facility $facility, ReorderProtectionCardsRequest $request): RedirectResponse
    {
        $cost = $this->defence->reorder(
            $facility,
            $request->kind(),
            $request->order(),
            $request->user(),
        );

        return back()->with('status', $cost === 0
            ? 'Order unchanged, so nothing was charged.'
            : sprintf('Reordered for %d Credit(s).', $cost));
    }

    /**
     * Take a card back out of a Facility and into the Corporation's hand.
     */
    public function remove(
        Request $request,
        Facility $facility,
        FacilityProtectionCard $card,
    ): RedirectResponse {
        Gate::authorize('defend', $facility);

        abort_if($card->facility_id !== $facility->id, 404);

        $name = $card->cardType->name;
        $cost = $this->defence->remove($card, $request->user());

        return back()->with('status', sprintf(
            '%s removed from %s%s.',
            $name,
            $facility->name,
            $cost === 0 ? ' for free' : ' for 1 Credit',
        ));
    }
}
