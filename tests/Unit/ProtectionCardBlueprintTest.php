<?php

namespace Tests\Unit;

use App\Support\ProtectionCardBlueprint;
use Tests\TestCase;

/**
 * The game's own Protection Card list (rulebook 3.3.2).
 *
 * These are data assertions rather than rule assertions, for the reason
 * Tests\Unit\AgendaCardBlueprintTest's are: the list is transcribed from the
 * game's card sheet, every game seeds from it, and a row that says something
 * the sheet does not is wrong in every game already created. Nothing else in
 * the application can catch that, because a Charge nobody else can see printed
 * is a perfectly valid Charge as far as the schema is concerned.
 *
 * The card sheet is the source, and where a card's committed artwork disagrees
 * with it the artwork is the older of the two. That is not a guess: reading a
 * card face as the authority is what took the Charges off PS015 and PS018, and
 * the sheet prints both. A face that disagrees is deleted rather than left to
 * be read again.
 *
 * The cards pinned below are the ones a pass over the whole sheet found the
 * list had wrong. They are here card by card rather than as a whole-list
 * fixture, so that a failure names the card and says what it should print.
 */
class ProtectionCardBlueprintTest extends TestCase
{
    /**
     * @return array<string, array{
     *     code: string,
     *     name: string,
     *     challenge: string,
     *     consequence: string,
     *     charge_cost: int|null,
     *     charge_consequence: string|null,
     * }>
     */
    private function catalogue(): array
    {
        return array_column(ProtectionCardBlueprint::defaults(), null, 'code');
    }

    /**
     * The sheet gives Anzû one line where the list had three, which had made it
     * by some way the harshest card in General Research.
     */
    public function test_anzu_ends_the_run_and_nothing_else(): void
    {
        $this->assertSame('End the Run', $this->catalogue()['PR010']['consequence']);
    }

    /**
     * Giant's Charge is End the Run on its own. The list added a Wound to it.
     */
    public function test_giants_charge_is_end_the_run_alone(): void
    {
        $card = $this->catalogue()['PE004'];

        $this->assertSame(6, $card['charge_cost']);
        $this->assertSame('End the Run', $card['charge_consequence']);
    }

    /**
     * Three cards whose transcription narrowed a consequence to a step of the
     * Run that the sheet does not name. "During the breather step" and "for the
     * rest of the run" are both readings; the sheet prints neither.
     */
    public function test_consequences_are_not_narrowed_to_a_step_of_the_run(): void
    {
        $catalogue = $this->catalogue();

        $this->assertSame(
            '1 Wound, Retry, Runners may not escape',
            $catalogue['PR003']['consequence'],
        );

        $this->assertSame(
            '2 Wounds, runners may not play additional cards',
            $catalogue['PR002']['charge_consequence'],
        );

        $this->assertSame(
            'Also, any runners leaving before the end take 1 Tag',
            $catalogue['PR015']['charge_consequence'],
        );
    }

    /**
     * Öryggissveit, Takkaborðið and Vélfærafræði sporðdreka are ANT's own cards
     * and they carry the Charges their counterparts carry - the sheet prints
     * all three. Pinned because the opposite was believed and acted on: the
     * artwork for PS015 and PS018 draws no CHARGE panel, the Charges came off
     * the blueprint on the strength of it, and ANT's Security lost a rule the
     * card list gives them.
     */
    public function test_ants_own_cards_carry_the_charges_the_sheet_prints(): void
    {
        $catalogue = $this->catalogue();

        $expected = [
            'PS015' => [1, '2 Alerts, 1 Wound'],
            'PS016' => [1, 'End the Run'],
            'PS018' => [1, '2 Wounds, End the Run'],
        ];

        foreach ($expected as $code => [$cost, $consequence]) {
            $this->assertSame($cost, $catalogue[$code]['charge_cost'], $code.' has lost its Charge cost.');
            $this->assertSame($consequence, $catalogue[$code]['charge_consequence'], $code.' has lost its Charge.');
        }
    }

    /**
     * A Charge is a cost and a consequence together (rulebook 3.3.5). Half of
     * one is neither usable nor printable, and the catalogue routes refuse it -
     * so the seeded list must not carry one either.
     */
    public function test_every_charge_carries_both_a_cost_and_a_consequence(): void
    {
        foreach (ProtectionCardBlueprint::defaults() as $card) {
            $this->assertSame(
                $card['charge_cost'] === null,
                $card['charge_consequence'] === null,
                $card['code'].' carries half a Charge.',
            );
        }
    }

    /**
     * Titles repeat - Doppleganger is PX011 and PX012 - so the code is the only
     * thing identifying a card, and protection_card_types is unique on it.
     */
    public function test_codes_are_distinct(): void
    {
        $codes = array_column(ProtectionCardBlueprint::defaults(), 'code');

        $this->assertSame(array_values(array_unique($codes)), $codes);
    }
}
