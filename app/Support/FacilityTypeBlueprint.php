<?php

namespace App\Support;

/**
 * The Facility types every game starts with (rulebook 3.3.1).
 *
 * A game's types live in its own table rather than an enum, because the
 * rulebook's own footnote to 3.3.1 says "More Facility types may be researched
 * during the game" — Control has to be able to add one mid-game without a
 * deployment. This class is only the starting catalogue.
 *
 * The mechanical effects are data on the type, not branches in the code, so a
 * type Control invents can carry them too:
 *
 * - protection_slots_granted: each Facility of this type adds this many
 *   physical and cyber Protection Card slots to every Facility the Corporation
 *   owns. Security grants 1 (rulebook 3.3.4).
 * - technology_capacity_granted: each Facility of this type adds this much
 *   technology storage to every Facility the Corporation owns. Corporate grants
 *   2, which is where "2 x the number of Corporate Facilities" comes from.
 *
 * Deliberately absent: anything that would derive Income or Political Will from
 * the Corporate Facility count. Income is the abstraction of a stock price and
 * is Control's to move, so more headquarters is a reason for Control to raise
 * it, not a formula.
 */
class FacilityTypeBlueprint
{
    public const RESEARCH = 'research';

    public const SECURITY = 'security';

    public const CORPORATE = 'corporate';

    /**
     * @return array<int, array{key: string, name: string, description: string, protection_slots_granted: int, technology_capacity_granted: int}>
     */
    public static function defaults(): array
    {
        return [
            [
                'key' => self::RESEARCH,
                'name' => 'Research',
                'description' => 'The more of these you have, the easier your research colleagues '
                    .'find it to research technologies. Technologies needing precise conditions '
                    .'must be housed here.',
                'protection_slots_granted' => 0,
                'technology_capacity_granted' => 0,
            ],
            [
                'key' => self::SECURITY,
                'name' => 'Security',
                'description' => 'Each one lets you install one more physical and one more cyber '
                    .'Protection Card in every Facility you own. Military technologies will '
                    .'likely need to be housed here.',
                'protection_slots_granted' => 1,
                'technology_capacity_granted' => 0,
            ],
            [
                'key' => self::CORPORATE,
                'name' => 'Corporate',
                'description' => 'Your headquarters. Each one raises how many technologies every '
                    .'Facility you own can store, and gives Control reason to raise your Income '
                    .'and Political Will.',
                'protection_slots_granted' => 0,
                'technology_capacity_granted' => 2,
            ],
        ];
    }
}
