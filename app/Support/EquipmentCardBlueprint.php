<?php

namespace App\Support;

use App\Enums\EquipmentCategory;

/**
 * The game's Equipment card list (rulebook 3.4.1), applied to every new game by
 * App\Actions\SeedEquipmentCards.
 *
 * These are the items Runners carry into a Run: the seventy-four cards the game
 * prints, in the three categories the rulebook gives them. What a card does is
 * held as the text printed on it, because every one of these effects belongs to
 * the Run loop, which this application has not built yet - so the catalogue is
 * what Control and the players read, and nothing here moves a number on its own.
 *
 * Each card carries the code printed on it, which is also how its artwork is
 * found (see App\Support\CardImage).
 *
 * A card with no cost is one the market does not sell: eight of the "Bypass"
 * cards and the Reconnaissance items are granted by a technology or a Facility's
 * access effect rather than bought, so the column stays null rather than
 * falling back to a free price.
 *
 * Deliberately absent: who owns which copies. Runners buy equipment from the
 * market or from each other during the Setup phase (rulebook 2.2.1), which is a
 * conversation at the table, and the shop is not modelled yet.
 */
class EquipmentCardBlueprint
{
    /**
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     category: EquipmentCategory,
     *     effect: string,
     *     cost: int|null,
     * }>
     */
    public static function defaults(): array
    {
        return [
            self::card(
                code: 'EEP001',
                name: 'Mind jack',
                category: EquipmentCategory::Permanent,
                effect: 'Retry any failed rolls once. For each time you do this take a wound at '
                    .'the end of the run',
                cost: 5,
            ),
            self::card(
                code: 'EEP002',
                name: 'Katana',
                category: EquipmentCategory::Permanent,
                effect: '+2 Brute',
                cost: 5,
            ),
            self::card(
                code: 'EEP003',
                name: 'Neural interface',
                category: EquipmentCategory::Permanent,
                effect: '+2 Hack',
                cost: 5,
            ),
            self::card(
                code: 'EEP009',
                name: 'Foresight',
                category: EquipmentCategory::Permanent,
                effect: 'If you leave a run, remove two wounds or one tag.',
                cost: 5,
            ),
            self::card(
                code: 'EEP010',
                name: 'Hindsight',
                category: EquipmentCategory::Permanent,
                effect: 'If you leave a run, see the Challenge and consequences of one '
                    .'unencountered protection on that run.',
                cost: 5,
            ),
            self::card(
                code: 'EEP011',
                name: 'Leviathan',
                category: EquipmentCategory::Permanent,
                effect: 'If you beat the Challenge of a protection, you may pay 5 credits to '
                    .'destroy that protection and return this card to Control',
                cost: 8,
            ),
            self::card(
                code: 'EEP012',
                name: 'Magnum',
                category: EquipmentCategory::Permanent,
                effect: 'Optionally add +3 Brute to your next roll. If you do, add 3 alert',
                cost: 4,
            ),
            self::card(
                code: 'EEP013',
                name: 'AK-47',
                category: EquipmentCategory::Permanent,
                effect: 'Optionally add +4 Brute to your next roll. If you do, add 4 alert',
                cost: 7,
            ),
            self::card(
                code: 'EEP014',
                name: 'Reconnaissance Facility Protection',
                category: EquipmentCategory::Permanent,
                effect: 'Pick a facility and roll your charisma. For each success you roll, look '
                    .'at a protection card in that facility.',
            ),
            self::card(
                code: 'EEP015',
                name: 'Reconnaissance Research',
                category: EquipmentCategory::Permanent,
                effect: 'Choose a corporation and technology. Roll your charisma. If you roll '
                    .'equal or more successes than you do failures, you are informed which '
                    .'facility the technology is stored in and receive a Technology Location '
                    .'card. For each failure, your are told another facility that might '
                    .'contain the card.',
            ),
            self::card(
                code: 'EEP016',
                name: 'Reconnaissance Facility contents',
                category: EquipmentCategory::Permanent,
                effect: 'Choose a facility. Roll your charisma. If you roll equal or more '
                    .'successes than you do failures, you may look at a technology stored in '
                    .'that facility and receive a Technology Location card.',
            ),
            self::card(
                code: 'EEP017',
                name: 'Technology transfer kit',
                category: EquipmentCategory::Permanent,
                effect: 'You have the Steal ability. The steal cost of any technology is the sum '
                    .'of its copy and destroy strength.',
            ),
            self::card(
                code: 'EEP018',
                name: 'Security specialist',
                category: EquipmentCategory::Permanent,
                effect: 'You may direct security from a facility. While doing so, you may use '
                    .'your own money to activate / boost / charge cards as well as the '
                    .'facility’s budget.',
            ),
            self::card(
                code: 'EEP019',
                name: 'Combat manoeuvres',
                category: EquipmentCategory::Permanent,
                effect: 'Whenever a runner takes a wound as part of a consequence when you '
                    .'aredirecting security, you may pay 3 credits to cause them to take '
                    .'another wound as well. This ability can only be used once per '
                    .'Consequence step.',
            ),
            self::card(
                code: 'EEP020',
                name: 'Nice try',
                category: EquipmentCategory::Permanent,
                effect: 'When you direct security from a facility, when a runner takes a '
                    .'consequence you can add “Retry” as an additional consequence. Use this '
                    .'ability only once per protection card. You cannot use this ability if '
                    .'you used it on the immediately previous protection card.',
            ),
            self::card(
                code: 'EEP021',
                name: '5LOW-R0LL.0.1',
                category: EquipmentCategory::Permanent,
                effect: 'During team time, you may steal a credit from one corporation where you '
                    .'have 5LOW-R0LL installed in one of their facilities. Upgrades after 3 '
                    .'installations',
            ),
            self::card(
                code: 'EEP022',
                name: '5LOW-R0LL.1.0',
                category: EquipmentCategory::Permanent,
                effect: 'During team time, you may steal 2 credits from one corporation where you '
                    .'have 5LOW-R0LL installed in 2 of their facilities. Upgrades after 5 '
                    .'installations',
            ),
            self::card(
                code: 'EEP023',
                name: '5LOW-R0LL.2.0',
                category: EquipmentCategory::Permanent,
                effect: 'Immediately defeat a protection card where 5LOW-R0LL is installed. Must '
                    .'be used before the Consequence step and only once a run',
            ),
            self::card(
                code: 'EEP024',
                name: 'Portable computer',
                category: EquipmentCategory::Permanent,
                effect: 'Gain +2 accesses',
                cost: 5,
            ),
            self::card(
                code: 'EES004',
                name: 'Mini-hospital',
                category: EquipmentCategory::SingleUse,
                effect: '-1 die for next roll. Heal 1 wound',
                cost: 5,
            ),
            self::card(
                code: 'EES005',
                name: 'Smoke Bomb',
                category: EquipmentCategory::SingleUse,
                effect: 'Upon encountering an Active protection, you may discard this to '
                    .'immediately leave your run without consequences.',
                cost: 2,
            ),
            self::card(
                code: 'EES025',
                name: 'Explosives',
                category: EquipmentCategory::SingleUse,
                effect: 'Destroy a protection card. Each runner in the group rolls their Brute. '
                    .'For each failure, take 1 wound.',
                cost: 10,
            ),
            self::card(
                code: 'EES026',
                name: 'Network scan',
                category: EquipmentCategory::SingleUse,
                effect: 'When accessing a facility, forgo an access to instead reveal all '
                    .'research cards in this facility',
                cost: 3,
            ),
            self::card(
                code: 'EES027',
                name: 'Compromise toolkit',
                category: EquipmentCategory::SingleUse,
                effect: 'Forgo all accesses to instead access a card in another facility of the '
                    .'same type owned by this corporation. You may use a Technology access '
                    .'card for this access if you have one. Must be used as first access.',
                cost: 8,
            ),
            self::card(
                code: 'EET006',
                name: 'Pacify.exe',
                category: EquipmentCategory::ThisRun,
                effect: 'Every protection encountered requires one lower level on its challenge '
                    .'for the duration of this run.',
                cost: 10,
            ),
            self::card(
                code: 'EET007',
                name: 'Muscle Stims',
                category: EquipmentCategory::ThisRun,
                effect: 'Gain +4 Brute for the duration of this run. Take 2 wounds when the run '
                    .'concludes.',
                cost: 4,
            ),
            self::card(
                code: 'EET008',
                name: 'Neural Stims',
                category: EquipmentCategory::ThisRun,
                effect: 'Gain +4 Hack for the duration of this run. Take 2 wounds when the run '
                    .'concludes.',
                cost: 4,
            ),
            self::card(
                code: 'ERP001',
                name: 'Baì Zè',
                category: EquipmentCategory::Permanent,
                effect: 'When you take any consequence, you may instead place cross out that many '
                    .'boxes. When all the boxes are checked, return this card to Control ☐ ☐ ☐ '
                    .'☐ ☐ ☐ ☐ ☐ ☐ ☐',
            ),
            self::card(
                code: 'ERP002',
                name: 'Tachash',
                category: EquipmentCategory::Permanent,
                effect: 'Prevent any 1 consequence, then return this card to Control',
            ),
            self::card(
                code: 'ERP004',
                name: 'Amdumbla',
                category: EquipmentCategory::Permanent,
                effect: 'Once per run, you may heal up to 5 wounds from all the participants of '
                    .'the run. You may split this as you wish',
            ),
            self::card(
                code: 'ERP005',
                name: 'Hulder',
                category: EquipmentCategory::Permanent,
                effect: 'Once per run you may pay 5 credits. You may ignore a consequence',
            ),
            self::card(
                code: 'ERP011',
                name: 'Cai Shen',
                category: EquipmentCategory::Permanent,
                effect: 'On a successful run, gain four credits.',
                cost: 10,
            ),
            self::card(
                code: 'ERP012',
                name: 'Samson',
                category: EquipmentCategory::Permanent,
                effect: 'If you would have to End the Run, pay three credits to ignore that '
                    .'consequence.',
                cost: 14,
            ),
            self::card(
                code: 'ERP013',
                name: 'Beam Blade',
                category: EquipmentCategory::Permanent,
                effect: '+3 Brute',
            ),
            self::card(
                code: 'ERP014',
                name: 'Cyber-Brain Intergrator',
                category: EquipmentCategory::Permanent,
                effect: '+3 Hack',
            ),
            self::card(
                code: 'ERP027',
                name: 'Decrypt-o-matic',
                category: EquipmentCategory::Permanent,
                effect: 'Up to 3 times a turn, you attempt to remove tags from any runner '
                    .'(including yourself). Roll your combined brute and hack - for each '
                    .'success remove a tag. If you roll more failures than success, take a '
                    .'wound',
            ),
            self::card(
                code: 'ERS003',
                name: 'Dobhar-chú',
                category: EquipmentCategory::SingleUse,
                effect: 'Ignore all wounds from this consequence',
                cost: 4,
            ),
            self::card(
                code: 'ERS006',
                name: 'Vittra',
                category: EquipmentCategory::SingleUse,
                effect: 'You may access one more card at the end of a run',
            ),
            self::card(
                code: 'ERS007',
                name: 'Shutdown',
                category: EquipmentCategory::SingleUse,
                effect: '+2 successes against a Cyber protection card',
            ),
            self::card(
                code: 'ERS008',
                name: 'MechanicalFailure',
                category: EquipmentCategory::SingleUse,
                effect: '+2 successes against a Physical protection card',
            ),
            self::card(
                code: 'ERS009',
                name: 'Data card',
                category: EquipmentCategory::SingleUse,
                effect: 'You may attempt to steal a technology. The steal strength is the same as '
                    .'the Copy+Destroy strength',
            ),
            self::card(
                code: 'ERS015',
                name: 'Ultra Boost',
                category: EquipmentCategory::SingleUse,
                effect: '+3 for next roll',
            ),
            self::card(
                code: 'ERS016',
                name: 'Beat Cop Bypass',
                category: EquipmentCategory::SingleUse,
                effect: 'Automatically Succeed against a Beat Cop protection card',
            ),
            self::card(
                code: 'ERS017',
                name: 'Assault Drone Bypass',
                category: EquipmentCategory::SingleUse,
                effect: 'Automatically Succeed against an Assault Drones protection card',
            ),
            self::card(
                code: 'ERS018',
                name: 'SpecOps Drones Bypass',
                category: EquipmentCategory::SingleUse,
                effect: 'Automatically Succeed against a SpecOps Drones protection card',
            ),
            self::card(
                code: 'ERS019',
                name: 'Honey Bomb Bypass',
                category: EquipmentCategory::SingleUse,
                effect: 'Automatically Succeed against a Honey Bomb card',
            ),
            self::card(
                code: 'ERS020',
                name: 'Honey Pot Bypass',
                category: EquipmentCategory::SingleUse,
                effect: 'Automatically Succeed against a Honey Pot card',
            ),
            self::card(
                code: 'ERS021',
                name: 'Armoured Guards Bypass',
                category: EquipmentCategory::SingleUse,
                effect: 'Automatically Succeed against an Armoured Guards protection card',
            ),
            self::card(
                code: 'ERS022',
                name: 'Tag scrubber',
                category: EquipmentCategory::SingleUse,
                effect: 'Remove 2 tags',
            ),
            self::card(
                code: 'ERS023',
                name: 'Strike zone',
                category: EquipmentCategory::SingleUse,
                effect: 'A missile hits your location. Contact Control',
            ),
            self::card(
                code: 'ERS024',
                name: '5LOW-R0LL',
                category: EquipmentCategory::SingleUse,
                effect: 'The current 5LOW-R0LL effect is applied',
                cost: 3,
            ),
            self::card(
                code: 'ERS025',
                name: 'Brute Data trap',
                category: EquipmentCategory::SingleUse,
                effect: 'The Run Leader must pass Brute (4). If they fail, they take a wound and '
                    .'a tag and the access is lost',
                cost: 5,
            ),
            self::card(
                code: 'ERS026',
                name: 'Hack Data trap',
                category: EquipmentCategory::SingleUse,
                effect: 'The Run Leader must pass Hack (4). If they fail, they take a wound and a '
                    .'tag and the access is lost',
                cost: 5,
            ),
            self::card(
                code: 'ERS028',
                name: 'Technology access card',
                category: EquipmentCategory::SingleUse,
                effect: 'When received, mark the technology. When accessing a facility, you may '
                    .'use this card as your access – if the technology is installed in this '
                    .'facility you access it',
            ),
            self::card(
                code: 'ERT010',
                name: 'Jigsaw',
                category: EquipmentCategory::ThisRun,
                effect: 'Before encountering any Active protection, you may temporarily swap its '
                    .'position with any other installed protection, including inactive '
                    .'protections.',
                cost: 7,
            ),
            self::card(
                code: 'ESP001',
                name: 'Armour',
                category: EquipmentCategory::Permanent,
                effect: 'Add +1 to one of your dice',
                cost: 6,
            ),
            self::card(
                code: 'ESP003',
                name: 'Shiv',
                category: EquipmentCategory::Permanent,
                effect: '+1 Brute',
                cost: 3,
            ),
            self::card(
                code: 'ESP004',
                name: 'PDA',
                category: EquipmentCategory::Permanent,
                effect: '+1 Hack',
                cost: 3,
            ),
            self::card(
                code: 'ESP013',
                name: 'Influencer',
                category: EquipmentCategory::Permanent,
                effect: 'If you are on a successful run, gain credits equal to the number of tags '
                    .'you have. Increase the number of tags you have by half that number '
                    .'(rounding up)',
                cost: 5,
            ),
            self::card(
                code: 'ESS002',
                name: 'Blood tinge',
                category: EquipmentCategory::SingleUse,
                effect: 'Roll d8 for Brute',
                cost: 5,
            ),
            self::card(
                code: 'ESS006',
                name: 'Boost',
                category: EquipmentCategory::SingleUse,
                effect: '+1 die for next roll',
                cost: 3,
            ),
            self::card(
                code: 'ESS007',
                name: 'Super boost',
                category: EquipmentCategory::SingleUse,
                effect: '+2 die for next roll',
                cost: 5,
            ),
            self::card(
                code: 'ESS008',
                name: 'Flare',
                category: EquipmentCategory::SingleUse,
                effect: 'Prevent a tag',
                cost: 3,
            ),
            self::card(
                code: 'ESS009',
                name: 'H4ck1ng 4 Dummi3s',
                category: EquipmentCategory::SingleUse,
                effect: '+2 Hack for the duration of this run',
                cost: 3,
            ),
            self::card(
                code: 'ESS010',
                name: 'Punching for dummies',
                category: EquipmentCategory::SingleUse,
                effect: '+2 Brute for the duration of this run',
                cost: 3,
            ),
            self::card(
                code: 'ESS011',
                name: 'Surge',
                category: EquipmentCategory::SingleUse,
                effect: 'The next time you roll dice, roll d8s',
                cost: 5,
            ),
            self::card(
                code: 'ESS014',
                name: 'Adrenaline Shot',
                category: EquipmentCategory::SingleUse,
                effect: 'Gain +3 dice for this roll but -1 dice for the next one',
                cost: 6,
            ),
            self::card(
                code: 'ESS015',
                name: 'High powered USB',
                category: EquipmentCategory::SingleUse,
                effect: 'Gain +1 access',
                cost: 1,
            ),
            self::card(
                code: 'ESS016',
                name: 'Data transfer window',
                category: EquipmentCategory::SingleUse,
                effect: 'Gain +2 accesses',
                cost: 2,
            ),
            self::card(
                code: 'EST005',
                name: 'First aid',
                category: EquipmentCategory::ThisRun,
                effect: '-1 wound for the duration of this run',
                cost: 3,
            ),
            self::card(
                code: 'EST012',
                name: 'Shake It Off',
                category: EquipmentCategory::ThisRun,
                effect: 'Prevent two tags this run',
                cost: 4,
            ),
            self::card(
                code: 'EXP003',
                name: 'AIdol',
                category: EquipmentCategory::Permanent,
                effect: 'If a runner accesses this card, they receive 2 tags. Return to control.',
                cost: 3,
            ),
            self::card(
                code: 'EXS001',
                name: 'Honey bomb',
                category: EquipmentCategory::SingleUse,
                effect: 'When a runner accesses this card, they instead make a hack (8) check. On '
                    .'failure, deal 3 wounds to the Run Leader. Return to control',
            ),
            self::card(
                code: 'EXS002',
                name: 'Honey pot',
                category: EquipmentCategory::SingleUse,
                effect: 'When a runner accesses this card, they instead make a hack (4) check. On '
                    .'failure, deal 2 tags to all members of the run. Return to control',
            ),
        ];
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     category: EquipmentCategory,
     *     effect: string,
     *     cost: int|null,
     * }
     */
    private static function card(
        string $code,
        string $name,
        EquipmentCategory $category,
        string $effect,
        ?int $cost = null,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'effect' => $effect,
            'cost' => $cost,
        ];
    }
}
