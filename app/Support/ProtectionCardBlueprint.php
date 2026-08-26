<?php

namespace App\Support;

use App\Enums\ProtectionCardAvailability;
use App\Enums\ProtectionKind;

/**
 * The game's Protection Card list (rulebook 3.3.2), applied to every new game
 * by App\Actions\SeedProtectionCards.
 *
 * This is the starting catalogue only. Every card lands in the game's own
 * protection_card_types table, which Control edits during play - the rulebook
 * says outright that other cards exist and become available once game
 * conditions Control judges have passed, so the catalogue is theirs once the
 * game exists.
 *
 * Each card carries the code printed on it, which is also how its artwork is
 * found (see App\Support\CardImage). A card Control invents mid-game has no
 * code and no artwork, and is shown as its text instead.
 *
 * Two things about this list that the schema had to be widened for:
 *
 * A card's challenge is the sentence printed on it, not a skill and a number.
 * Most of the eighty-three are a plain "Brute (6)", but a good many are not:
 * "Brute/Hack (2)" lets the Runners pick, "Hack (4+N) - where N is the number of
 * cards underneath this" is not known until the card is met, and "Hack (4),
 * followed by Brute (4)" is two challenges on one card. Nothing computes a dice
 * pool from this yet, so the application stores and shows the words and the
 * table converts them, exactly as it does with the card in hand.
 *
 * Card titles are not unique. Doppleganger is two cards - PX011 in the physical
 * stack and PX012 in the cyber one - so the code is what identifies a card and
 * the title is only what is printed on it.
 */
class ProtectionCardBlueprint
{
    /**
     * Codes are prefixed by which pool a card belongs to, and that is the only
     * thing in the game's own data that says how a card is obtained:
     *
     * - PS, the twenty cards with shop prices, are the ones the Corporations'
     *   briefings hand out and the ones Security can buy
     * - PE are produced by an Equipment Facility rather than researched
     * - PR and PX are each the target of some technology's "Unlock:" effect, so
     *   they cannot be bought until a Research player has unlocked them
     *
     * Reading availability off the prefix is therefore a reading of the card
     * list rather than a rule written down in it, which is why it is done here
     * once and openly instead of being derived at runtime. Control moves any
     * card between the three states, and three PR cards - PR001, PR011 and
     * PR013 - are unlocked by no technology in the list at all, so they need
     * Control either to price them or to add the research that unlocks them.
     */
    private const RESEARCH_UNLOCKED_PREFIXES = ['PR', 'PX'];

    /**
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     kind: ProtectionKind,
     *     challenge: string,
     *     consequence: string,
     *     cost: int|null,
     *     charge_cost: int|null,
     *     charge_consequence: string|null,
     *     availability: ProtectionCardAvailability,
     * }>
     */
    public static function defaults(): array
    {
        return [
            self::card(
                code: 'PE001',
                name: 'Expander gun',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (Number of wounds + 2)',
                consequence: '2 tag, end the run',
                cost: 7,
            ),
            self::card(
                code: 'PE002',
                name: 'Security camera scanner',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (3)',
                consequence: '3 tag',
                cost: 6,
                chargeCost: 1,
                chargeConsequence: '4 tag',
            ),
            self::card(
                code: 'PE003',
                name: 'Security camera turret',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (5)',
                consequence: '1 wound, 1 tag',
                cost: 8,
            ),
            self::card(
                code: 'PE004',
                name: 'Giant',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (3)',
                consequence: '4 wound',
                cost: 7,
                chargeCost: 6,
                chargeConsequence: '1 wound, end the run',
            ),
            self::card(
                code: 'PR001',
                name: 'Coordinated security team',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (6)',
                consequence: '1 alert, 3 wound',
                chargeCost: 5,
                chargeConsequence: '2 alert, 5 wound',
            ),
            self::card(
                code: 'PR002',
                name: 'Nachtkrapp',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (4)',
                consequence: '2 wound',
                chargeCost: 4,
                chargeConsequence: '2 wounds, runners may not play cards for the rest of the run',
            ),
            self::card(
                code: 'PR003',
                name: 'Brazen bull',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (2)',
                consequence: '1 wound, retry. Runners may not escape during the breather step',
            ),
            self::card(
                code: 'PR004',
                name: 'Tigris',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (6)',
                consequence: '4 wounds',
            ),
            self::card(
                code: 'PR005',
                name: 'Keresh',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (6)',
                consequence: '2 alerts, 2 tags',
                chargeCost: 3,
                chargeConsequence: 'For the rest of the run, each alert also causes 1 tag',
            ),
            self::card(
                code: 'PR006',
                name: 'Bei Ilai',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (4), followed by Brute (4)',
                consequence: 'Tigris and Keresh get +x+1 for their challenge, where X is the '
                    .'difference between the security successes and runner successes',
            ),
            self::card(
                code: 'PR007',
                name: 'Peryton',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (5)',
                consequence: 'The runners return to the previous protection card',
            ),
            self::card(
                code: 'PR008',
                name: 'Ziz',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (6)',
                consequence: 'End the run, 2 wounds, 2 tags',
            ),
            self::card(
                code: 'PR009',
                name: 'Griffin',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (4)',
                consequence: 'End the run, 1 wound, 2 tags',
            ),
            self::card(
                code: 'PR010',
                name: 'Anzû',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (6)',
                consequence: 'End the run, 3 wounds, 4 tags',
            ),
            self::card(
                code: 'PR011',
                name: 'Simurgh',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (6)',
                consequence: 'End the run, 4 wounds, 3 tags',
            ),
            self::card(
                code: 'PR012',
                name: 'Turul',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (2)',
                consequence: '1 wound, retry',
            ),
            self::card(
                code: 'PR013',
                name: 'Phoenix',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (3)',
                consequence: '2 wound',
                chargeCost: 4,
                chargeConsequence: '4 wound',
            ),
            self::card(
                code: 'PR014',
                name: 'Minotaur',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (4)',
                consequence: 'As a group, the runners take 5 wounds (divided as they see fit)',
            ),
            self::card(
                code: 'PR015',
                name: 'Bonnacon',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (4)',
                consequence: '1 wound. The net protection card has +1 strength',
                chargeCost: 3,
                chargeConsequence: 'Additionally, any runners that leave the run in the next breather step '
                    .'take 1 tag',
            ),
            self::card(
                code: 'PR016',
                name: 'Chichevache',
                kind: ProtectionKind::Physical,
                challenge: 'The runner with the highest total of wounds and tags: Brute (5)',
                consequence: 'That runner must take 1 wound, 1 tag and an additional wound or tag '
                    .'(runners choice)',
                chargeCost: 7,
                chargeConsequence: 'That runner takes a wound, a tag and end the run',
            ),
            self::card(
                code: 'PR017',
                name: 'Bicorn',
                kind: ProtectionKind::Physical,
                challenge: 'The runner with the highest total stats: Brute (3)',
                consequence: 'That runner must take 1 wound, 1 tag and an additional wound or tag '
                    .'(runners choice)',
                chargeCost: 7,
                chargeConsequence: 'That runner takes a wound, a tag and end the run',
            ),
            self::card(
                code: 'PR018',
                name: 'Gallic rooster',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (3+number of runners)',
                consequence: '5 tags, 5 alerts. Return this card to Control at the end of the run',
            ),
            self::card(
                code: 'PR019',
                name: 'Sarangay',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (5)',
                consequence: 'The run leader chooses either: One runner takes End the Run (2) or All '
                    .'runners take 2 wound and 2 tags',
                chargeCost: 5,
                chargeConsequence: 'Trigger both consequences',
            ),
            self::card(
                code: 'PR020',
                name: 'Fjalar',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (3)',
                consequence: 'The runners do not see the consequence or challenge strength of the next '
                    .'protection card',
            ),
            self::card(
                code: 'PR021',
                name: 'Gullinkambi',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (3)',
                consequence: 'The next 2 protection cards have +2 strength',
            ),
            self::card(
                code: 'PR022',
                name: 'Rooster from Hel',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (3)',
                consequence: 'End the run, 3 tags',
            ),
            self::card(
                code: 'PR023',
                name: 'Basan',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (4)',
                consequence: '2 wounds',
            ),
            self::card(
                code: 'PR024',
                name: 'Hellhound',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (N+3) – where N is the number of times Hellhound has been '
                    .'encountered throughout the game',
                consequence: '1 wound, retry. If Hellhound has been encountered 3 or more times, '
                    .'replace with End the run and N wounds, where N is the number of times '
                    .'Hellhound has been encountered',
            ),
            self::card(
                code: 'PR025',
                name: 'Cú-síth',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (3)',
                consequence: '2 tags. If Cú-síth has been encountered 3 or more times, replace with '
                    .'End the Run and N tags, where N is the number of times Cú-síth has been '
                    .'encountered',
            ),
            self::card(
                code: 'PR026',
                name: 'Echidna',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (3)',
                consequence: 'Trigger a consequence for any card from this facility that has already '
                    .'been revealed',
            ),
            self::card(
                code: 'PR027',
                name: 'Typhon',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (6)',
                consequence: 'End the run, 3 tags, 2 wounds',
            ),
            self::card(
                code: 'PR028',
                name: 'Cerberus',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (8)',
                consequence: '4 wounds, 3 tags, End the run',
            ),
            self::card(
                code: 'PR029',
                name: 'Orthrus',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (4)',
                consequence: 'End the run, 3 tags',
            ),
            self::card(
                code: 'PR030',
                name: 'aos sí',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (4+N) - where N is the number of cards underneath this',
                consequence: 'Take an equipment card from a runner that is equipped and place it '
                    .'underneath this card. The Security player may remove any equipment cards '
                    .'during the Setup Phase',
            ),
            self::card(
                code: 'PR031',
                name: 'Cat síth',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (3+N) - where N is the number of empty squares',
                consequence: '3 alerts, 2 wounds, Cross out a box. When all the boxes are checked, '
                    .'return this card to Control □ □ □ □ □ □ □ □ □',
            ),
            self::card(
                code: 'PR032',
                name: 'Cŵn Annwn',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (2+N) - where N is the number of active protection cards',
                consequence: 'Each runner takes 2 wounds and 1 tag',
                chargeCost: 10,
                chargeConsequence: 'Each runner individually takes End the Run',
            ),
            self::card(
                code: 'PR033',
                name: 'Nattmara',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (5)',
                consequence: 'At the end of the run (after accessing, or after an unsuccessful run), 1 '
                    .'runner is reduced to 1 health',
            ),
            self::card(
                code: 'PR034',
                name: 'Nøkken',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (4)',
                consequence: 'N alerts, where N is equal to the number of runners in the group. Retry',
                chargeCost: 4,
                chargeConsequence: 'N alerts, where N is equal to the number of runners in the group. End '
                    .'the run',
            ),
            self::card(
                code: 'PR035',
                name: 'Fossegrim',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (7)',
                consequence: 'N tags, where N is equal to the number of runners in the group. Retry',
                chargeCost: 4,
                chargeConsequence: 'N tags, where N is equal to the number of runners in the group. End the '
                    .'run',
            ),
            self::card(
                code: 'PR036',
                name: 'Skogsrå',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (6)',
                consequence: '3 wounds, 3 alerts, 3 tags',
            ),
            self::card(
                code: 'PR037',
                name: 'Ykur',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (6)',
                consequence: 'The runners move back N protection cards (decided by the Security '
                    .'player). Cross out that many boxes. When all boxes are checked, return '
                    .'this card to control □ □ □ □ □',
            ),
            self::card(
                code: 'PR038',
                name: 'Lagarfljót worm',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (4+N) – where N is the number of cards underneath this',
                consequence: 'All runners either place an equipment card underneath this card, or '
                    .'takes End the Run. If there are 5 cards underneath this, return it to '
                    .'Control (Security keeps any cards underneath it)',
                chargeCost: 10,
                chargeConsequence: 'Each runner must both give up an equipment card and take End the Run',
            ),
            self::card(
                code: 'PR039',
                name: 'Bindy',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (2)',
                consequence: 'Retry',
                chargeCost: 1,
                chargeConsequence: '1 alert, retry',
            ),
            self::card(
                code: 'PR040',
                name: 'Tobius',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (3)',
                consequence: '2 tags',
            ),
            self::card(
                code: 'PR041',
                name: 'Huldra',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (4)',
                consequence: 'Move back a protection card',
                chargeCost: 3,
                chargeConsequence: 'Move back a protection card, 1 wound',
            ),
            self::card(
                code: 'PR042',
                name: 'SAIren',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (5)',
                consequence: '2 tags',
                cost: 2,
                chargeCost: 2,
                chargeConsequence: 'All tagged runners take 2 wound',
            ),
            self::card(
                code: 'PR043',
                name: 'Georgie',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (5)',
                consequence: 'If tagged, end the run.',
                cost: 1,
                chargeCost: 1,
                chargeConsequence: '1 tag',
            ),
            self::card(
                code: 'PR044',
                name: 'Beat Cop',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (4)',
                consequence: 'End the run.',
                chargeCost: 2,
                chargeConsequence: 'Do 1 wound.',
            ),
            self::card(
                code: 'PR045',
                name: 'Assault Drones',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (6)',
                consequence: 'End the run.',
                chargeCost: 2,
                chargeConsequence: 'Do 2 wounds.',
            ),
            self::card(
                code: 'PR046',
                name: 'SpecOp Drones',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (9)',
                consequence: 'End the run.',
                chargeCost: 4,
                chargeConsequence: 'Do 5 wounds.',
            ),
            self::card(
                code: 'PR047',
                name: 'Armoured Guards',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (6)',
                consequence: '3 Wounds, 1 tag',
                chargeCost: 3,
                chargeConsequence: '5 wounds, 1 tag',
            ),
            self::card(
                code: 'PS001',
                name: 'Old dog',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (2)',
                consequence: '1 alert',
                cost: 2,
            ),
            self::card(
                code: 'PS002',
                name: 'Snoozing guard',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (1)',
                consequence: '1 alert',
                cost: 1,
            ),
            self::card(
                code: 'PS003',
                name: 'Security team',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (2)',
                consequence: '1 alert, 1 wound',
                cost: 5,
                chargeCost: 1,
                chargeConsequence: '2 alert, 1 wound',
            ),
            self::card(
                code: 'PS004',
                name: 'Security intern',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (2)',
                consequence: '1 alert',
                cost: 3,
                chargeCost: 1,
                chargeConsequence: '2 alert',
            ),
            self::card(
                code: 'PS005',
                name: 'Keypad',
                kind: ProtectionKind::Physical,
                challenge: 'Brute/Hack (2)',
                consequence: '1 alert, retry',
                cost: 6,
                chargeCost: 1,
                chargeConsequence: 'End the run',
            ),
            self::card(
                code: 'PS006',
                name: 'Security shutter',
                kind: ProtectionKind::Physical,
                challenge: 'Brute or Hack (Number of alerts+2)',
                consequence: 'End the run',
                cost: 5,
            ),
            self::card(
                code: 'PS007',
                name: 'Security camera',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (4)',
                consequence: '1 tag, 1 alert',
                cost: 4,
                chargeCost: 1,
                chargeConsequence: '2 alert, 1 tag',
            ),
            self::card(
                code: 'PS008',
                name: 'Knockout gas',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (3)',
                consequence: '1 tag, End the run',
                cost: 9,
            ),
            self::card(
                code: 'PS009',
                name: 'Orc',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (2)',
                consequence: '1 wound',
                cost: 4,
            ),
            self::card(
                code: 'PS010',
                name: 'Mammoth',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (3)',
                consequence: '3 wound',
                cost: 8,
                chargeCost: 5,
                chargeConsequence: 'End the run',
            ),
            self::card(
                code: 'PS011',
                name: 'Dwarf chief',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (2)',
                consequence: '2 wound',
                cost: 6,
                chargeCost: 5,
                chargeConsequence: 'End the run',
            ),
            self::card(
                code: 'PS012',
                name: 'Ravens',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (2)',
                consequence: '2 tag, end the run',
                cost: 4,
                chargeCost: 1,
                chargeConsequence: '3 tag',
            ),
            self::card(
                code: 'PS013',
                name: 'Angel',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (3)',
                consequence: 'End the run, 1 tag',
                cost: 8,
            ),
            self::card(
                code: 'PS014',
                name: 'Valkyrie',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (3)',
                consequence: '2 wound, 1 tag',
                cost: 7,
            ),
            self::card(
                code: 'PS015',
                name: 'Öryggissveit',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (2)',
                consequence: '1 alert, 1 wound',
                cost: 5,
                chargeCost: 1,
                chargeConsequence: '2 alert, 1 wound',
            ),
            self::card(
                code: 'PS016',
                name: 'Takkaborðið',
                kind: ProtectionKind::Physical,
                challenge: 'Brute/Hack (2)',
                consequence: '1 alert, retry',
                cost: 6,
                chargeCost: 1,
                chargeConsequence: 'End the run',
            ),
            self::card(
                code: 'PS017',
                name: 'Öryggisluggari',
                kind: ProtectionKind::Physical,
                challenge: 'Brute or Hack (Number of alerts+2)',
                consequence: 'End the run',
                cost: 5,
            ),
            self::card(
                code: 'PS018',
                name: 'Vélfærafræði sporðdreka',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (3)',
                consequence: '2 wounds',
                cost: 7,
                chargeCost: 1,
                chargeConsequence: '2 wounds, end the run',
            ),
            self::card(
                code: 'PS019',
                name: 'Roboscorpion',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (3)',
                consequence: '2 wounds',
                cost: 7,
                chargeCost: 1,
                chargeConsequence: '2 wounds, end the run',
            ),
            self::card(
                code: 'PS020',
                name: 'Engill',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (3)',
                consequence: 'End the run, 1 tag',
                cost: 8,
            ),
            self::card(
                code: 'PX001',
                name: 'Dragon wight',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (8)',
                consequence: 'Each runner in the group takes 2 wounds',
            ),
            self::card(
                code: 'PX002',
                name: 'Grýla',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (4)',
                consequence: 'The player with the lowest total stats takes 3 wounds',
            ),
            self::card(
                code: 'PX003',
                name: 'leppalúði',
                kind: ProtectionKind::Physical,
                challenge: 'Brute (2)',
                consequence: 'The player with the lowest total stats takes 5 wounds',
            ),
            self::card(
                code: 'PX004',
                name: 'Yule Lads',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (10)',
                consequence: 'Take all equipment cards from one runner (including those that are '
                    .'equipped). Return this card to Control',
            ),
            self::card(
                code: 'PX005',
                name: 'Jólakötturinn',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (5)',
                consequence: 'Any runners with no equipment take 5 wounds. If this happens, return '
                    .'this card to Control',
            ),
            self::card(
                code: 'PX006',
                name: 'Heimskringla',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (N+5), where N is the number of runners in the group',
                consequence: 'N alerts, where N is equal to the number of runners in the group. 1 '
                    .'wound',
            ),
            self::card(
                code: 'PX007',
                name: 'Terrible whale',
                kind: ProtectionKind::Cyber,
                challenge: 'Brute (7)',
                consequence: 'Each runner in the group takes 1 wound and 2 tags',
            ),
            self::card(
                code: 'PX008',
                name: 'Eyjafjörður griffin',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (10)',
                consequence: 'N+1 wounds, where N is equal to the difference in successes from '
                    .'Security and the Runners. Split these wounds as you wish',
            ),
            self::card(
                code: 'PX009',
                name: 'Breiðafjörður bull',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (10)',
                consequence: 'N+1 tags, where N is equal to the differences in successes from Security '
                    .'and the Runners. Split these tags as you wish',
            ),
            self::card(
                code: 'PX010',
                name: 'Vikarsskeið giant',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (10)',
                consequence: '2N alerts, where N is equal to the differences in successes from '
                    .'Security and the Runners',
            ),
            self::card(
                code: 'PX011',
                name: 'Doppleganger',
                kind: ProtectionKind::Physical,
                challenge: 'Hack (8) or Brute (8)',
                consequence: '5 tags',
                cost: 4,
                chargeCost: 3,
                chargeConsequence: 'All tagged runners take 4 wounds',
            ),
            self::card(
                code: 'PX012',
                name: 'Doppleganger',
                kind: ProtectionKind::Cyber,
                challenge: 'Hack (8) or Brute (8)',
                consequence: '5 tags',
                cost: 4,
                chargeCost: 3,
                chargeConsequence: 'All tagged runners take 4 wounds',
            ),
        ];
    }

    /**
     * One card, as it is printed.
     *
     * A card with no cost is one nobody can buy yet, which is not the same as a
     * card that is free - so the column is nullable and stays null rather than
     * falling back to zero.
     *
     * A Charge is a cost and a consequence together (rulebook 3.3.5): a cost
     * with nothing to spend it on, or a consequence with no price, is neither
     * usable nor printable, so the two always travel as a pair.
     *
     * @return array{
     *     code: string,
     *     name: string,
     *     kind: ProtectionKind,
     *     challenge: string,
     *     consequence: string,
     *     cost: int|null,
     *     charge_cost: int|null,
     *     charge_consequence: string|null,
     *     availability: ProtectionCardAvailability,
     * }
     */
    private static function card(
        string $code,
        string $name,
        ProtectionKind $kind,
        string $challenge,
        string $consequence,
        ?int $cost = null,
        ?int $chargeCost = null,
        ?string $chargeConsequence = null,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'kind' => $kind,
            'challenge' => $challenge,
            'consequence' => $consequence,
            'cost' => $cost,
            'charge_cost' => $chargeCost,
            'charge_consequence' => $chargeConsequence,
            'availability' => self::availabilityFor($code),
        ];
    }

    /**
     * How a card is obtained when the game opens, read off its code prefix.
     */
    private static function availabilityFor(string $code): ProtectionCardAvailability
    {
        return in_array(substr($code, 0, 2), self::RESEARCH_UNLOCKED_PREFIXES, true)
            ? ProtectionCardAvailability::ResearchOnly
            : ProtectionCardAvailability::Available;
    }
}
