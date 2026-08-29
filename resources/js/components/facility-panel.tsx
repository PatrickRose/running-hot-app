import { router } from '@inertiajs/react';
import { useState } from 'react';
import { GameIcon } from '@/components/game-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { channels, destroy, security } from '@/routes/control/facilities';
import {
    install as installCard,
    reorder as reorderCards,
    remove as removeCard,
} from '@/routes/control/facilities/cards';
import type {
    FacilitySummary,
    ProtectionCardSummary,
    ProtectionStack,
} from '@/types/game';

const SELECT_CLASS =
    'h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * One Facility: its two Protection Card stacks, and Security's orders for it.
 *
 * The stacks read top to bottom in the order Runners meet them, which is what
 * "outermost" means in the rulebook. Moving a card costs 1 Credit and the
 * server works out how many cards a reorder actually moves, so the buttons here
 * only ever say what the new order is.
 */
export function FacilityPanel({
    gameId,
    facility,
    catalogue,
    currentTurn,
    discordReady,
}: {
    gameId: number;
    facility: FacilitySummary;
    catalogue: ProtectionCardSummary[];
    currentTurn: number | null;
    /** False when the game has no Discord server, or no bot to build with. */
    discordReady: boolean;
}) {
    const channelState = facility.channels;
    const channelsReady =
        channelState !== null && channelState.text && channelState.voice;

    return (
        <div className="rounded-md border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-medium">
                        {facility.name}{' '}
                        <span className="text-muted-foreground">
                            &middot; {facility.facility_type}
                        </span>
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {facility.stacks
                            .map(
                                (stack) =>
                                    `${stack.cards.length}/${stack.slots} ${stack.kind}`,
                            )
                            .join(' · ')}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {facility.available ? (
                        <Badge variant="outline">Open</Badge>
                    ) : (
                        <Badge variant="secondary">
                            Building &middot; opens turn{' '}
                            {facility.available_from_turn}
                        </Badge>
                    )}
                    {facility.security.directed && (
                        <Badge>Security directed here</Badge>
                    )}
                    {discordReady && channelState !== null && (
                        <>
                            {channelsReady ? (
                                <Badge variant="outline">Channels ready</Badge>
                            ) : (
                                <>
                                    <Badge variant="destructive">
                                        No Discord channels
                                    </Badge>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                channels.url({
                                                    game: gameId,
                                                    facility: facility.id,
                                                }),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Create its channels
                                    </Button>
                                </>
                            )}
                        </>
                    )}
                    <Button
                        size="sm"
                        variant="ghost"
                        aria-label={`Remove ${facility.name}`}
                        onClick={() =>
                            router.delete(
                                destroy.url({
                                    game: gameId,
                                    facility: facility.id,
                                }),
                                { preserveScroll: true },
                            )
                        }
                    >
                        Remove
                    </Button>
                </div>
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                {facility.stacks.map((stack) => (
                    <Stack
                        key={stack.kind}
                        gameId={gameId}
                        facility={facility}
                        stack={stack}
                        catalogue={catalogue}
                    />
                ))}
            </div>

            <SecurityOrders
                gameId={gameId}
                facility={facility}
                currentTurn={currentTurn}
            />
        </div>
    );
}

function Stack({
    gameId,
    facility,
    stack,
    catalogue,
}: {
    gameId: number;
    facility: FacilitySummary;
    stack: ProtectionStack;
    catalogue: ProtectionCardSummary[];
}) {
    const installable = catalogue.filter(
        (card) =>
            card.kind === stack.kind &&
            !facility.stacks.some((other) =>
                other.cards.some(
                    (installed) => installed.card_type_id === card.id,
                ),
            ),
    );

    const full = stack.cards.length >= stack.slots;

    /**
     * Swap two neighbours and send the whole stack. The server charges 1 Credit
     * for the one card that moved.
     */
    const swap = (index: number, offset: number) => {
        const order = stack.cards.map((card) => card.id);
        const target = index + offset;

        if (target < 0 || target >= order.length) {
            return;
        }

        [order[index], order[target]] = [order[target], order[index]];

        router.post(
            reorderCards.url({ game: gameId, facility: facility.id }),
            { kind: stack.kind, order },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-col gap-2">
            <p className="flex items-center gap-1.5 text-sm font-medium">
                <GameIcon glyph={stack.kind_glyph} label={stack.kind_label} />
                <span aria-hidden="true">{stack.kind_label}</span>
                <span className="font-normal text-muted-foreground">
                    {stack.cards.length}/{stack.slots}
                </span>
            </p>

            <ol className="flex flex-col gap-1">
                {stack.cards.map((card, index) => (
                    <li
                        key={card.id}
                        className="flex flex-wrap items-center gap-2 rounded border px-2 py-1 text-sm"
                    >
                        <span className="font-mono text-xs text-muted-foreground">
                            {card.position}
                        </span>
                        <span className="flex-1">
                            {card.name}
                            <span className="ml-2 text-muted-foreground">
                                {card.challenge}
                            </span>
                        </span>
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={index === 0}
                            aria-label={`Move ${card.name} outwards`}
                            onClick={() => swap(index, -1)}
                        >
                            ↑
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={index === stack.cards.length - 1}
                            aria-label={`Move ${card.name} inwards`}
                            onClick={() => swap(index, 1)}
                        >
                            ↓
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            aria-label={`Remove ${card.name}`}
                            onClick={() =>
                                router.delete(
                                    removeCard.url({
                                        game: gameId,
                                        facility: facility.id,
                                        card: card.id,
                                    }),
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Remove
                        </Button>
                    </li>
                ))}
                {stack.cards.length === 0 && (
                    <li className="text-sm text-muted-foreground">
                        Undefended.
                    </li>
                )}
            </ol>

            <InstallCard
                gameId={gameId}
                facilityId={facility.id}
                kind={stack.kind_label}
                options={installable}
                disabled={full}
            />
        </div>
    );
}

function InstallCard({
    gameId,
    facilityId,
    kind,
    options,
    disabled,
}: {
    gameId: number;
    facilityId: number;
    kind: string;
    options: ProtectionCardSummary[];
    disabled: boolean;
}) {
    const [selected, setSelected] = useState('');

    if (disabled) {
        return (
            <p className="text-sm text-muted-foreground">
                Every {kind.toLowerCase()} slot is full.
            </p>
        );
    }

    return (
        <div className="flex flex-wrap gap-2">
            <select
                aria-label={`Install a ${kind} card`}
                value={selected}
                onChange={(event) => setSelected(event.target.value)}
                className={SELECT_CLASS}
            >
                <option value="">Install a card…</option>
                {options.map((card) => (
                    <option key={card.id} value={card.id}>
                        {card.name} — {card.challenge}
                    </option>
                ))}
            </select>
            <Button
                size="sm"
                disabled={selected === ''}
                onClick={() => {
                    router.post(
                        installCard.url({
                            game: gameId,
                            facility: facilityId,
                        }),
                        { protection_card_type_id: Number(selected) },
                        {
                            preserveScroll: true,
                            onSuccess: () => setSelected(''),
                        },
                    );
                }}
            >
                Install
            </Button>
        </div>
    );
}

/**
 * Directing Security and the budget on this Facility (rulebook 3.3.5).
 *
 * Raising the budget takes the Credits off the Corporation now; whatever is
 * unspent comes back when the Action phase ends.
 */
function SecurityOrders({
    gameId,
    facility,
    currentTurn,
}: {
    gameId: number;
    facility: FacilitySummary;
    currentTurn: number | null;
}) {
    const [budget, setBudget] = useState(String(facility.security.budget));
    const [spent, setSpent] = useState(String(facility.security.budget_spent));

    if (currentTurn === null) {
        return (
            <p className="mt-4 text-sm text-muted-foreground">
                Security orders open once the clock starts.
            </p>
        );
    }

    const post = (data: Record<string, number | boolean>) =>
        router.post(
            security.url({ game: gameId, facility: facility.id }),
            data,
            {
                preserveScroll: true,
            },
        );

    return (
        <div className="mt-4 flex flex-wrap items-end gap-3 border-t pt-3">
            <Button
                size="sm"
                variant={facility.security.directed ? 'default' : 'outline'}
                onClick={() =>
                    post({ security_directed: !facility.security.directed })
                }
            >
                {facility.security.directed
                    ? 'Lift the meeple'
                    : 'Direct Security here'}
            </Button>

            <div className="grid gap-1">
                <Label
                    htmlFor={`budget-${facility.id}`}
                    className="text-xs text-muted-foreground"
                >
                    Budget
                </Label>
                <div className="flex gap-2">
                    <Input
                        id={`budget-${facility.id}`}
                        type="number"
                        min={0}
                        className="w-24"
                        value={budget}
                        onChange={(event) => setBudget(event.target.value)}
                    />
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            post({ security_budget: Number(budget) })
                        }
                    >
                        Place
                    </Button>
                </div>
            </div>

            <div className="grid gap-1">
                <Label
                    htmlFor={`spent-${facility.id}`}
                    className="text-xs text-muted-foreground"
                >
                    Spent this turn
                </Label>
                <div className="flex gap-2">
                    <Input
                        id={`spent-${facility.id}`}
                        type="number"
                        min={0}
                        className="w-24"
                        value={spent}
                        onChange={(event) => setSpent(event.target.value)}
                    />
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            post({ security_budget_spent: Number(spent) })
                        }
                    >
                        Record
                    </Button>
                </div>
            </div>

            <p className="text-sm text-muted-foreground">
                {facility.security.budget_returned
                    ? 'Returned at the end of the Action phase.'
                    : `${Math.max(
                          0,
                          facility.security.budget -
                              facility.security.budget_spent,
                      )} unspent, returning at the end of the Action phase.`}
            </p>
        </div>
    );
}
