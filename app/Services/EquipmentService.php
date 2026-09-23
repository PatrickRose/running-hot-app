<?php

namespace App\Services;

use App\Models\Character;
use App\Models\EquipmentCardType;
use App\Models\EquipmentHolding;
use Illuminate\Support\Facades\DB;
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
        Character $character,
        EquipmentCardType $card,
        int $copies,
    ): EquipmentHolding {
        if ($copies < 0) {
            throw ValidationException::withMessages([
                'copies' => 'Nobody can carry fewer than no copies of a card.',
            ]);
        }

        /** @var EquipmentHolding $holding */
        $holding = $character->equipmentHoldings()->updateOrCreate(
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
    public function giveCopy(Character $character, EquipmentCardType $card): void
    {
        $this->giveCopies($character, $card, 1);
    }

    /**
     * Hand several copies over at once.
     *
     * Adding rather than setting, which is the difference between this and
     * setCopiesInHand and the reason both exist: Control handing a card over
     * knows what it is giving and not what the player already has, so a give
     * that set the count would quietly take away the two Shivs they were
     * carrying. The shop hands copies over through here for the same reason.
     */
    public function giveCopies(Character $character, EquipmentCardType $card, int $copies = 1): EquipmentHolding
    {
        if ($copies < 1) {
            throw ValidationException::withMessages([
                'copies' => 'Giving somebody no copies of a card is not giving them anything.',
            ]);
        }

        /** @var EquipmentHolding $holding */
        $holding = $character->equipmentHoldings()->updateOrCreate(
            ['equipment_card_type_id' => $card->id],
            [],
        );

        $holding->increment('copies', $copies);

        return $holding->refresh();
    }

    /**
     * Pass copies from one hand to another (rulebook 2.1).
     *
     * "You may buy equipment, either from the market or from other players" is
     * the whole of what the rulebook says about this, and the word that shapes
     * it is *buy*: what comes back the other way is Credits, a favour, or a
     * share of the next job. None of that moves here, for the reason a research
     * point trade moves nothing back either (3.2.5) - a transfer is one-way and
     * one-sided, one hand goes down and the other goes up, and what was agreed
     * in exchange is settled at the table.
     *
     * That one-sidedness is also what makes it safe to give a player. Handing a
     * card away spends only what is yours; a transfer that also took the other
     * player's Credits would be one player reaching into another's purse on the
     * strength of a price only the giver had typed in.
     *
     * Locked for the length of it, because two copies given away at once from
     * the same hand is exactly what a double-clicked button is.
     */
    public function transfer(
        Character $from,
        Character $to,
        EquipmentCardType $card,
        int $copies = 1,
    ): void {
        if ($copies < 1) {
            throw ValidationException::withMessages([
                'copies' => 'Handing somebody no copies of a card is not handing them anything.',
            ]);
        }

        if ($from->is($to)) {
            throw ValidationException::withMessages([
                'to_character_id' => 'That card is already in their hand.',
            ]);
        }

        if ($from->game_id !== $to->game_id || $card->game_id !== $from->game_id) {
            throw ValidationException::withMessages([
                'to_character_id' => sprintf('%s is playing a different game.', $to->name),
            ]);
        }

        DB::transaction(function () use ($from, $to, $card, $copies): void {
            // The same query twice, which is what lets the count be read
            // without a model to be null: somebody who has never held this card
            // has no row at all, and that is nought copies rather than an
            // error.
            $hand = fn () => EquipmentHolding::query()
                ->where('character_id', $from->id)
                ->where('equipment_card_type_id', $card->id);

            $held = (int) $hand()->lockForUpdate()->value('copies');

            if ($held < $copies) {
                throw ValidationException::withMessages([
                    'copies' => sprintf(
                        '%s is carrying %d %s of %s, not %d.',
                        $from->name,
                        $held,
                        $held === 1 ? 'copy' : 'copies',
                        $card->name,
                        $copies,
                    ),
                ]);
            }

            // The row stays at nought rather than being deleted, for the reason
            // setCopiesInHand keeps it: somebody who has given their last
            // Mini-hospital away held one, and a list that forgets it reads as
            // though they never did.
            $hand()->decrement('copies', $copies);

            $this->giveCopies($to, $card, $copies);
        });
    }
}
