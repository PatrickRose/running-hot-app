<?php

namespace App\Services;

use App\Models\Character;
use App\Models\EquipmentCardType;
use App\Models\EquipmentHolding;
use Illuminate\Validation\ValidationException;

/**
 * Who is carrying which Equipment card, and how many copies (rulebook 3.4.1).
 *
 * The one place `equipment_holdings` is written, which is the rule
 * App\Services\FacilityDefenceService keeps for a Corporation's Protection
 * Cards. It was RunEngine before this: a run spends a This-run or Single-use
 * card and hands a carried-out Runner's permanent items to Security, and those
 * were the only two ways a count could move. Control giving a card out is the
 * third, and it happens nowhere near a run - so the count moved out here and
 * the run loop asks for it, rather than a Control route growing its own copy
 * of the write.
 *
 * Nothing here is a Tracker. A Tracker is a number the game moves and argues
 * about afterwards, which is why every one of those goes through
 * App\Services\TrackerService and leaves a ledger row; how many Shivs somebody
 * is carrying is a holding, exactly as a Corporation's Protection Cards are,
 * and holdings record where a count ended up rather than replaying how it got
 * there.
 */
class EquipmentService
{
    /**
     * Set outright how many copies of a card a Runner is carrying.
     *
     * Control's, and the counterpart of
     * FacilityDefenceService::setCopiesInHand. Every way a card actually
     * changes hands in the rules - the market, a Runner selling to another, a
     * gang splitting a haul, Control handing one over for a job well done - is
     * a conversation at the table (2.2.1), so this records the answer rather
     * than modelling any of the routes to it.
     *
     * Zero is a real answer, and the row is kept rather than deleted: a Runner
     * who has spent their last Mini-hospital held one, and a list that forgets
     * it reads as though they never did.
     */
    public function setCopiesInHand(
        Character $runner,
        EquipmentCardType $card,
        int $copies,
    ): EquipmentHolding {
        if ($copies < 0) {
            throw ValidationException::withMessages([
                'copies' => 'A Runner cannot carry fewer than no copies of a card.',
            ]);
        }

        /** @var EquipmentHolding $holding */
        $holding = $runner->equipmentHoldings()->updateOrCreate(
            ['equipment_card_type_id' => $card->id],
            ['copies' => $copies],
        );

        return $holding;
    }

    /**
     * Spend one copy, because the card has been played or taken away.
     *
     * Both consumable categories are "returned to Control" once used, and a
     * Runner carried out of a Facility loses their permanent items to the
     * Security player - so both are one copy off this Runner, and the Security
     * player's gain is a separate give.
     */
    public function takeCopy(Character $runner, EquipmentCardType $card): void
    {
        EquipmentHolding::query()
            ->where('character_id', $runner->id)
            ->where('equipment_card_type_id', $card->id)
            ->decrement('copies');
    }

    /**
     * Hand one copy over, creating the row if this is their first.
     */
    public function giveCopy(Character $runner, EquipmentCardType $card): void
    {
        $runner->equipmentHoldings()->updateOrCreate(
            ['equipment_card_type_id' => $card->id],
            [],
        )->increment('copies');
    }
}
