import { router } from '@inertiajs/react';
import { useState } from 'react';
import { FactionBadge } from '@/components/faction-badge';
import { GameIcon } from '@/components/game-icon';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/control/equipment-holdings';
import type { EquipmentCardSummary, GangEquipmentHoldings } from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * Who is carrying which Equipment, and Control handing cards out (3.4.1).
 *
 * Per Runner rather than per gang, because that is what the rulebook caps and
 * what it takes away: three equipped permanent items are *yours*, and *your*
 * permanent Equipment goes to the Security player when you are carried out.
 *
 * Control sets a count outright. Buying from the market, selling to another
 * Runner, splitting a haul and being handed a card for a job that went well all
 * happen at the table, so this records where the count ended up rather than
 * replaying how it got there.
 */
export function EquipmentHoldings({
    gameId,
    holdings,
    cards,
}: {
    gameId: number;
    holdings: GangEquipmentHoldings[];
    cards: EquipmentCardSummary[];
}) {
    if (holdings.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No Runners yet, so nobody is carrying anything.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-10">
            {holdings.map((gang) => (
                <section
                    key={gang.gang_id ?? 'freelancers'}
                    className="flex flex-col gap-4"
                >
                    <h3 className="flex items-center gap-2 text-sm font-medium">
                        {gang.has_badge ? (
                            <FactionBadge faction={gang} size="small" />
                        ) : null}
                        {gang.name}
                    </h3>

                    <div className="flex flex-col gap-6 sm:pl-2">
                        {gang.runners.map((runner) => (
                            <RunnerHand
                                key={runner.character_id}
                                gameId={gameId}
                                runner={runner}
                                cards={cards}
                            />
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}

function RunnerHand({
    gameId,
    runner,
    cards,
}: {
    gameId: number;
    runner: GangEquipmentHoldings['runners'][number];
    cards: EquipmentCardSummary[];
}) {
    return (
        <div className="flex flex-col gap-2 border-l-2 pl-4">
            <h4 className="text-sm font-medium">
                {runner.name}
                {runner.role === 'freelancer' ? (
                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                        {runner.role_label}
                    </span>
                ) : null}
            </h4>

            {runner.cards.length === 0 ? (
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
                            {runner.cards.map((card) => (
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
                                            characterId={runner.character_id}
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

            <GiveACard
                gameId={gameId}
                characterId={runner.character_id}
                runnerName={runner.name}
                cards={cards}
                alreadyHeld={runner.cards.map((card) => card.card_type_id)}
            />
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

/**
 * Give a Runner a card they are carrying none of.
 *
 * Which is how a card bought from the market, taken off another Runner or
 * handed over by Control reaches somebody: Control is told, and writes it down.
 */
function GiveACard({
    gameId,
    characterId,
    runnerName,
    cards,
    alreadyHeld,
}: {
    gameId: number;
    characterId: number;
    runnerName: string;
    cards: EquipmentCardSummary[];
    alreadyHeld: number[];
}) {
    const [selected, setSelected] = useState('');
    const [copies, setCopies] = useState('1');

    const options = cards.filter((card) => !alreadyHeld.includes(card.id));

    if (options.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-end gap-2">
            <div className="grid gap-1">
                <Label
                    htmlFor={`give-equipment-${characterId}`}
                    className="text-xs text-muted-foreground"
                >
                    Give {runnerName} a card
                </Label>
                <select
                    id={`give-equipment-${characterId}`}
                    value={selected}
                    onChange={(event) => setSelected(event.target.value)}
                    className={SELECT_CLASS}
                >
                    <option value="">Choose a card…</option>
                    {options.map((card) => (
                        <option key={card.id} value={card.id}>
                            {card.code ? `${card.code} — ` : ''}
                            {card.name} ({card.category_label})
                        </option>
                    ))}
                </select>
            </div>

            <div className="grid gap-1">
                <Label
                    htmlFor={`give-equipment-copies-${characterId}`}
                    className="text-xs text-muted-foreground"
                >
                    Copies
                </Label>
                <Input
                    id={`give-equipment-copies-${characterId}`}
                    type="number"
                    min={0}
                    value={copies}
                    onChange={(event) => setCopies(event.target.value)}
                    className="w-20"
                />
            </div>

            <Button
                size="sm"
                disabled={selected === ''}
                onClick={() =>
                    router.patch(
                        update.url({ game: gameId }),
                        {
                            character_id: characterId,
                            equipment_card_type_id: Number(selected),
                            copies: Number(copies),
                        },
                        {
                            preserveScroll: true,
                            onSuccess: () => setSelected(''),
                        },
                    )
                }
            >
                Give
            </Button>
        </div>
    );
}
