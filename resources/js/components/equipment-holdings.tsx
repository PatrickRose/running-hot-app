import { router } from '@inertiajs/react';
import { useState } from 'react';
import { FactionBadge } from '@/components/faction-badge';
import { GameIcon } from '@/components/game-icon';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/control/equipment-holdings';
import type { CharacterEquipment, EquipmentHoldingGroup } from '@/types/game';

/**
 * Who is carrying which Equipment, and Control handing cards out (3.4.1).
 *
 * Per Character rather than per team, because that is what the rulebook caps
 * and what it takes away: three equipped permanent items are *yours*, and
 * *your* permanent Equipment goes to the Security player when you are carried
 * out.
 *
 * Everybody on the roster is here, not only the side that runs. 2.1 has Runners
 * buying equipment "from other players", so a card reaches the Facility by way
 * of whoever was holding it - which may well be a CEO who bought it to hand
 * over.
 *
 * Two controls, and they answer different questions. Giving *adds* copies and
 * is the one Control reaches for at the table: it knows what it is handing over
 * and not what the player already has - so it happens by clicking the card in
 * the Equipment list above, where Control is already looking and the list's own
 * search has already found it. Setting a count replaces it, and that is here,
 * against the hand it corrects: a card spent, a haul split, a number typed
 * wrong.
 */
export function EquipmentHoldings({
    gameId,
    holdings,
}: {
    gameId: number;
    holdings: EquipmentHoldingGroup[];
}) {
    return (
        <div className="flex flex-col gap-10">
            {holdings.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No characters yet, so nobody is carrying anything.
                </p>
            ) : (
                holdings.map((group) => (
                    <section key={group.key} className="flex flex-col gap-4">
                        <h3 className="flex items-center gap-2 text-sm font-medium">
                            {group.has_badge ? (
                                <FactionBadge faction={group} size="small" />
                            ) : null}
                            {group.name}
                        </h3>

                        <div className="flex flex-col gap-6 sm:pl-2">
                            {group.members.map((member) => (
                                <Hand
                                    key={member.character_id}
                                    gameId={gameId}
                                    character={member}
                                />
                            ))}
                        </div>
                    </section>
                ))
            )}
        </div>
    );
}

function Hand({
    gameId,
    character,
}: {
    gameId: number;
    character: CharacterEquipment;
}) {
    return (
        <div className="flex flex-col gap-2 border-l-2 pl-4">
            <h4 className="text-sm font-medium">
                {character.name}
                <span className="ml-2 text-xs font-normal text-muted-foreground">
                    {character.role_label}
                </span>
            </h4>

            {character.cards.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Carrying nothing. Their briefing named no Equipment, or
                    Control has not given them any yet.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2 pr-4 font-medium">Card</th>
                                <th className="py-2 pr-4 font-medium">
                                    Category
                                </th>
                                <th className="py-2 pr-4 text-right font-medium">
                                    Carrying
                                </th>
                                <th className="py-2 font-medium">Set to</th>
                            </tr>
                        </thead>
                        <tbody>
                            {character.cards.map((card) => (
                                <tr
                                    key={card.card_type_id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-2 pr-4 font-medium">
                                        {card.name}
                                        {card.code ? (
                                            <span className="ml-2 font-mono text-xs font-normal text-muted-foreground">
                                                {card.code}
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="py-2 pr-4 text-muted-foreground">
                                        <span className="flex items-center gap-1.5">
                                            <GameIcon
                                                glyph={card.category_glyph}
                                                label={card.category_label}
                                            />
                                            <span aria-hidden="true">
                                                {card.category_label}
                                            </span>
                                        </span>
                                    </td>
                                    <td className="py-2 pr-4 text-right font-mono tabular-nums">
                                        {card.copies}
                                    </td>
                                    <td className="py-2">
                                        <SetCopies
                                            gameId={gameId}
                                            characterId={character.character_id}
                                            cardTypeId={card.card_type_id}
                                            current={card.copies}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function SetCopies({
    gameId,
    characterId,
    cardTypeId,
    current,
}: {
    gameId: number;
    characterId: number;
    cardTypeId: number;
    current: number;
}) {
    const [copies, setCopies] = useState(String(current));
    const changed = copies !== String(current);

    return (
        <div className="flex items-center justify-end gap-2">
            <Input
                aria-label="Copies carried"
                type="number"
                min={0}
                value={copies}
                onChange={(event) => setCopies(event.target.value)}
                className="w-20"
            />
            <Button
                size="sm"
                variant="outline"
                disabled={!changed}
                onClick={() =>
                    router.patch(
                        update.url({ game: gameId }),
                        {
                            character_id: characterId,
                            equipment_card_type_id: cardTypeId,
                            copies: Number(copies),
                        },
                        { preserveScroll: true },
                    )
                }
            >
                Set
            </Button>
        </div>
    );
}
