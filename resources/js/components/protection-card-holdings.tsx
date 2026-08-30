import { router } from '@inertiajs/react';
import { useState } from 'react';
import { GameIcon } from '@/components/game-icon';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/control/protection-card-holdings';
import type {
    CorporationCardHoldings,
    ProtectionCardSummary,
} from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * How many copies of each Protection Card a Corporation owns (rulebook 3.3.4).
 *
 * The count is what caps how many Facilities a card can defend: one copy per
 * Facility, so four copies of Security Team cover four Facilities and no more.
 *
 * Control sets it outright. Everything that moves it - the shop, an auction, a
 * research grant, two Security players trading - happens at the table, so this
 * records where it ended up rather than replaying it.
 */
export function ProtectionCardHoldings({
    gameId,
    holdings,
    cards,
}: {
    gameId: number;
    holdings: CorporationCardHoldings[];
    cards: ProtectionCardSummary[];
}) {
    if (holdings.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No Corporations yet, so nobody holds any cards.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-8">
            {holdings.map((corporation) => (
                <CorporationHoldings
                    key={corporation.corporation_id}
                    gameId={gameId}
                    corporation={corporation}
                    cards={cards}
                />
            ))}
        </div>
    );
}

function CorporationHoldings({
    gameId,
    corporation,
    cards,
}: {
    gameId: number;
    corporation: CorporationCardHoldings;
    cards: ProtectionCardSummary[];
}) {
    return (
        <section className="flex flex-col gap-3">
            <h3 className="text-sm font-medium">{corporation.corporation}</h3>

            {corporation.cards.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Holds no cards. Its briefing named none, or Control has not
                    given it any yet.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2 pr-4 font-medium">Card</th>
                                <th className="py-2 pr-4 font-medium">Kind</th>
                                <th className="py-2 pr-4 text-right font-medium">
                                    In hand
                                </th>
                                <th className="py-2 pr-4 text-right font-medium">
                                    Installed
                                </th>
                                <th className="py-2 pr-4 text-right font-medium">
                                    Total
                                </th>
                                <th className="py-2 font-medium">Set to</th>
                            </tr>
                        </thead>
                        <tbody>
                            {corporation.cards.map((card) => (
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
                                                glyph={card.kind_glyph}
                                                label={card.kind_label}
                                            />
                                            <span aria-hidden="true">
                                                {card.kind_label}
                                            </span>
                                        </span>
                                    </td>
                                    <td className="py-2 pr-4 text-right font-mono tabular-nums">
                                        {card.copies_in_hand}
                                    </td>
                                    <td className="py-2 pr-4 text-right font-mono text-muted-foreground tabular-nums">
                                        {card.installed}
                                    </td>
                                    <td className="py-2 pr-4 text-right font-mono text-muted-foreground tabular-nums">
                                        {card.copies_in_hand + card.installed}
                                    </td>
                                    <td className="py-2">
                                        <SetCopies
                                            gameId={gameId}
                                            corporationId={
                                                corporation.corporation_id
                                            }
                                            cardTypeId={card.card_type_id}
                                            current={card.copies_in_hand}
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
                corporationId={corporation.corporation_id}
                cards={cards}
                alreadyHeld={corporation.cards.map((card) => card.card_type_id)}
            />
        </section>
    );
}

function SetCopies({
    gameId,
    corporationId,
    cardTypeId,
    current,
}: {
    gameId: number;
    corporationId: number;
    cardTypeId: number;
    current: number;
}) {
    const [copies, setCopies] = useState(String(current));
    const changed = copies !== String(current);

    return (
        <div className="flex items-center justify-end gap-2">
            <Input
                aria-label="Copies in hand"
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
                            corporation_id: corporationId,
                            protection_card_type_id: cardTypeId,
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
 * Give a Corporation a card it holds none of.
 *
 * Which is how a card bought from the shop, won at auction or unlocked by
 * research reaches a Corporation: Control is told, and writes it down.
 */
function GiveACard({
    gameId,
    corporationId,
    cards,
    alreadyHeld,
}: {
    gameId: number;
    corporationId: number;
    cards: ProtectionCardSummary[];
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
                    htmlFor={`give-card-${corporationId}`}
                    className="text-xs text-muted-foreground"
                >
                    Give a card
                </Label>
                <select
                    id={`give-card-${corporationId}`}
                    value={selected}
                    onChange={(event) => setSelected(event.target.value)}
                    className={SELECT_CLASS}
                >
                    <option value="">Choose a card…</option>
                    {options.map((card) => (
                        <option key={card.id} value={card.id}>
                            {card.code ? `${card.code} — ` : ''}
                            {card.name} ({card.kind_label})
                        </option>
                    ))}
                </select>
            </div>

            <div className="grid gap-1">
                <Label
                    htmlFor={`give-copies-${corporationId}`}
                    className="text-xs text-muted-foreground"
                >
                    Copies
                </Label>
                <Input
                    id={`give-copies-${corporationId}`}
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
                            corporation_id: corporationId,
                            protection_card_type_id: Number(selected),
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
