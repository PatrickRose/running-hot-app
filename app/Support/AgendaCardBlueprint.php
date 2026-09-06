<?php

namespace App\Support;

/**
 * The game's agenda deck (rulebook 3.1.1), applied to every new game by
 * App\Actions\SeedAgendaCards.
 *
 * These are the cards Control picks from at the start of each Setup phase.
 * Like the three card lists beside this one, it is a starting catalogue rather
 * than a fixed deck: every card lands in the game's own agenda_cards table,
 * Control writes more from the Control panel, and the Chair may amend the
 * resolutions on any of them with Council Control's sign-off (3.1.4).
 *
 * A card is identified by its title, because agenda cards carry no printed
 * code. That works only while the titles are distinct, so a test asserts they
 * are - and it is why App\Actions\SeedAgendaCards matches on the title of a
 * card nobody submitted: a player is perfectly likely to write their own
 * agenda called "Privacy" (3.1.3), and it must not stop the deck seeding.
 *
 * Two entries are worth reading twice before deciding they are duplicates or
 * typos, because both are the game's own joke:
 *
 * - Retirement offers a pension package and a "pension package". The quotation
 *   marks are the whole difference, and they are the point.
 * - Grants names the four Research Point suits - maths, cog, leaf and brain -
 *   which are App\Enums\ResearchSuit's four cases. It is the only card in the
 *   deck that names a mechanic rather than a policy, and nothing reads it:
 *   what a resolution *does* is Control's to apply, here as everywhere else.
 */
class AgendaCardBlueprint
{
    /**
     * The bounds every one of these respects, and 3.1.4 imposes: an agenda card
     * has at least two resolutions and at most five.
     *
     * @return array<int, array{title: string, resolutions: array<int, string>}>
     */
    public static function defaults(): array
    {
        return [
            [
                'title' => 'Privacy',
                'resolutions' => [
                    'Increase privacy protections',
                    'Keep mostly the same',
                    'Decrease privacy protections',
                ],
            ],
            [
                'title' => 'Agenda decision',
                'resolutions' => [
                    'Keep current process',
                    'Replace chair on a rotation',
                    'Randomly decide chair',
                    'Remove role of chair. All players vote on the three agenda items, all votes are now public',
                ],
            ],
            [
                'title' => 'Voting record',
                'resolutions' => [
                    'All votes must be public, and the voting record will be made public',
                    'All votes must be public. The total amounts are not announced',
                    'All players must vote as to whether future votes are secret/public before voting',
                    'Keep current process',
                ],
            ],
            [
                'title' => 'Genetic engineering',
                'resolutions' => [
                    'Outlaw all genetic engineering',
                    'Genetic engineering must be approved by the council',
                    'Genetic engineering on animals/humans must be approved by the council',
                    'Genetic engineering on humans must be approved by the council',
                    'No restrictions',
                ],
            ],
            [
                'title' => 'Taxation',
                'resolutions' => [
                    'No taxation',
                    'Honour system – taxes may be paid as Corps see fit',
                    '5% of income, or 4 credits (whichever is higher)',
                    '10% of income or 8 credits',
                ],
            ],
            [
                'title' => 'Runners',
                'resolutions' => [
                    'Freelance security must be signed up to a code of conduct',
                    'No restrictions',
                ],
            ],
            [
                'title' => 'AI',
                'resolutions' => [
                    'Outlaw all Artificial Intelligence',
                    'Must be approved by council. AI research must be shared with all council members',
                    'All research must be approved by council',
                    'No restrictions',
                ],
            ],
            [
                'title' => 'Housing',
                'resolutions' => [
                    'Build free council housing for all inhabitants (council must also pay 30 credits)',
                    'Build paid for housing for all inhabitants (council must also pay 15 credits)',
                    'No building',
                ],
            ],
            [
                'title' => 'Independence',
                'resolutions' => [
                    'Remain part of the UK',
                    'Declare independence',
                ],
            ],
            [
                'title' => 'Sponsorship',
                'resolutions' => [
                    'Allow corporations to sponsor events',
                    'Do not allow corporations to sponsor events',
                ],
            ],
            [
                'title' => 'Advertising',
                'resolutions' => [
                    'Allow corporations to pay for advertisements in media',
                    'Allow corporations to place advertisements in media for free',
                    'Do not allow corporations to place advertisements',
                ],
            ],
            [
                'title' => 'City ID',
                'resolutions' => [
                    'Approve the creation of a Citizen City ID system',
                    'Do not approve the creation of a Citizen City ID system',
                ],
            ],
            [
                'title' => 'Vehicle-Free Roads',
                'resolutions' => [
                    'Implement annual vehicular tax for all diesel car owners',
                    'Ban all diesel cars in Procatarion',
                    'No change in policy',
                ],
            ],
            [
                'title' => 'Protests',
                'resolutions' => [
                    'Allow peaceful protests',
                    'Allow all protests',
                    'Ban all protests and riot action',
                ],
            ],
            [
                'title' => 'Retirement',
                'resolutions' => [
                    'Provide retirees with a pension package',
                    'Provide retirees with a "pension package"',
                    'Ban all retirement',
                ],
            ],
            [
                'title' => 'Gun Regulations',
                'resolutions' => [
                    'Open Carry',
                    'Licensed and holstered',
                    'Banned',
                ],
            ],
            [
                'title' => 'Ms Procatorion',
                'resolutions' => [
                    'Host pageant',
                    'Don\'t host pageant',
                ],
            ],
            [
                'title' => 'Grants',
                'resolutions' => [
                    'Spend 5 credits to boost all future maths research',
                    'Spend 5 credits to boost all future cog research',
                    'Spend 5 credits to boost all future leaf research',
                    'Spend 5 credits to boost all future brain research',
                    'Do nothing',
                ],
            ],
            [
                'title' => 'Runner Representative',
                'resolutions' => [
                    'Bring a runner to council on a recurring basis',
                    'Bring a runner before council for the next session',
                ],
            ],
            [
                'title' => 'Immigration',
                'resolutions' => [
                    'No immigration checks',
                    'Free passage to British citizens',
                    'Thorough background check',
                ],
            ],
            [
                'title' => 'Food Aid',
                'resolutions' => [
                    'Do nothing',
                    'Supply corporation controlled food aid to supplement citizens\' purchases',
                    'Supply full food aid for all citizens',
                ],
            ],
        ];
    }
}
