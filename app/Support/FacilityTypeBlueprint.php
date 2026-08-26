<?php

namespace App\Support;

use App\Enums\FacilityGrantScaling;

/**
 * The Facility types every game starts with, from the game's own type sheet.
 *
 * A game's types live in its own table rather than an enum, because the
 * rulebook's footnote to 3.3.1 says "More Facility types may be researched
 * during the game" — Control has to be able to add one mid-game without a
 * deployment. This class is only the starting catalogue.
 *
 * The mechanical effects are data on the type, not branches in the code, so a
 * type Control invents can carry them too:
 *
 * - physical_slots_granted / cyber_slots_granted: Protection Card slots each
 *   Facility of this type adds to every Facility the Corporation owns. Security
 *   grants 1 physical and 2 cyber, which is asymmetric: rulebook 3.3.4 says
 *   "1 more of each type", and the type sheet supersedes it.
 * - technology_capacity_granted: technology storage added to every Facility the
 *   Corporation owns. Corporate grants 2, which is where "2 x the number of
 *   Corporate Facilities" comes from.
 * - card_move_discount: Credits off reordering a stack. Factory grants 2.
 *
 * The rest of each type's effect is held as text for Control to read, because
 * it belongs to a sub-game this application has not built yet: research hand
 * size, the strength bonuses Directing Security gets from AI School and Arms,
 * how many cyber cards a Power Facility lets Security activate, what Equipment
 * can be produced, and every access effect. Storing the words without
 * computing them is deliberate — none of those numbers move on their own.
 *
 * Deliberately absent: anything that would derive Income or Political Will from
 * the Corporate Facility count. Income is the abstraction of a stock price and
 * is Control's to move, so more headquarters is a reason for Control to raise
 * it, not a formula.
 */
class FacilityTypeBlueprint
{
    public const RESEARCH = 'research';

    public const CORPORATE = 'corporate';

    public const SECURITY = 'security';

    public const AI_SCHOOL = 'ai-school';

    public const POWER = 'power';

    public const FACTORY = 'factory';

    public const MINI_FACTORY = 'mini-factory';

    public const ARMS = 'arms';

    public const EQUIPMENT = 'equipment';

    public const ID_FACILITY = 'id-facility';

    public const MISSILE_NETWORK = 'missile-network';

    /**
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     build_cost: int,
     *     description: string,
     *     access_effect: string|null,
     *     physical_slots_granted: int,
     *     cyber_slots_granted: int,
     *     technology_capacity_granted: int,
     *     card_move_discount: int,
     *     grant_scaling: FacilityGrantScaling,
     * }>
     */
    public static function defaults(): array
    {
        return [
            self::type(
                key: self::RESEARCH,
                name: 'Research',
                buildCost: 8,
                effect: 'During the research game, your hand size is increased by one. You '
                    .'receive an additional card at 2, 3, 5, 8 etc Facilities.',
                access: 'Secretly choose a Facility, be told which technologies are stored there.',
                scaling: FacilityGrantScaling::Thresholds,
            ),
            self::type(
                key: self::CORPORATE,
                name: 'Corporate',
                buildCost: 12,
                effect: 'Increase Political Will / Income. Store 2 more technologies at each Facility.',
                access: 'Steal a stock certificate / blackmail file.',
                technologyCapacity: 2,
            ),
            self::type(
                key: self::SECURITY,
                name: 'Security',
                buildCost: 5,
                effect: 'May install 1 more Physical and 2 more Cyber Protection Cards in each '
                    .'of your Facilities.',
                access: 'Secretly choose a Facility. You are told how many Protection Cards are '
                    .'installed and may look at up to 3.',
                physicalSlots: 1,
                cyberSlots: 2,
            ),
            self::type(
                key: self::AI_SCHOOL,
                name: 'AI School',
                buildCost: 12,
                effect: 'When Directing Security from a Facility, your Cyber Protection Cards '
                    .'get +1. You receive an additional +1 at 2, 3, 5, 8 etc AI School Facilities.',
                access: null,
                scaling: FacilityGrantScaling::Thresholds,
            ),
            self::type(
                key: self::POWER,
                name: 'Power',
                buildCost: 10,
                effect: 'Activate 1 more Cyber Protection Card per Run.',
                access: 'Receive a "shutdown" card.',
            ),
            self::type(
                key: self::FACTORY,
                name: 'Factory',
                buildCost: 10,
                effect: 'When moving Protection Cards, you receive a 2 Credit discount. You '
                    .'receive an additional 1 Credit discount at 2, 3, 5, 8 etc Factories.',
                access: 'Receive a "mechanical failure" card.',
                cardMoveDiscount: 2,
                scaling: FacilityGrantScaling::Thresholds,
            ),
            self::type(
                key: self::MINI_FACTORY,
                name: 'Mini-factory',
                buildCost: 7,
                effect: 'When moving Protection Cards, you receive a 1 Credit discount. You '
                    .'receive an additional 1 Credit discount at 2, 3, 5, 8 etc Factories.',
                access: 'Receive a "mechanical failure" card.',
                cardMoveDiscount: 1,
                scaling: FacilityGrantScaling::Thresholds,
            ),
            self::type(
                key: self::ARMS,
                name: 'Arms',
                buildCost: 10,
                effect: 'When Directing Security from a Facility, your Physical Protection Cards '
                    .'get +1. You receive an additional +1 at 2, 3, 5, 8 etc Arms Facilities.',
                access: 'Receive some military hardware as decided by Control.',
                scaling: FacilityGrantScaling::Thresholds,
            ),
            self::type(
                key: self::EQUIPMENT,
                name: 'Equipment',
                buildCost: 12,
                effect: 'You may produce Katana, Neural Interface and Boost Equipment Cards at a '
                    .'cost of 2 Credits each.',
                access: 'Receive a "Super boost" card.',
            ),
            self::type(
                key: self::ID_FACILITY,
                name: 'ID Facility',
                buildCost: 5,
                effect: 'When accessed, give each Runner in the group 2 Tags.',
                access: 'Receive a "Tag scrubber" card.',
            ),
            self::type(
                key: self::MISSILE_NETWORK,
                name: 'Missile Network',
                buildCost: 20,
                effect: 'You may fire missiles at any Runners who have Tags.',
                access: 'Receive a "Strike zone" card.',
            ),
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     build_cost: int,
     *     description: string,
     *     access_effect: string|null,
     *     physical_slots_granted: int,
     *     cyber_slots_granted: int,
     *     technology_capacity_granted: int,
     *     card_move_discount: int,
     *     grant_scaling: FacilityGrantScaling,
     * }
     */
    private static function type(
        string $key,
        string $name,
        int $buildCost,
        string $effect,
        ?string $access = null,
        int $physicalSlots = 0,
        int $cyberSlots = 0,
        int $technologyCapacity = 0,
        int $cardMoveDiscount = 0,
        FacilityGrantScaling $scaling = FacilityGrantScaling::PerFacility,
    ): array {
        return [
            'key' => $key,
            'name' => $name,
            'build_cost' => $buildCost,
            'description' => $effect,
            'access_effect' => $access,
            'physical_slots_granted' => $physicalSlots,
            'cyber_slots_granted' => $cyberSlots,
            'technology_capacity_granted' => $technologyCapacity,
            'card_move_discount' => $cardMoveDiscount,
            'grant_scaling' => $scaling,
        ];
    }
}
