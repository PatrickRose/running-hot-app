import { router } from '@inertiajs/react';
import { useState } from 'react';
import { GameIcon } from '@/components/game-icon';
import { SearchPicker } from '@/components/search-picker';
import type { PickerOption } from '@/components/search-picker';
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
                                    `${stack.cards.length}${stack.slots === null ? '' : `/${stack.slots}`} ${stack.kind}`,
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

    // A Plot Facility has no slot limit at all, so nothing is ever full.
    const full = stack.slots !== null && stack.cards.length >= stack.slots;

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
                    {stack.cards.length}
                    {stack.slots === null ? ' installed' : `/${stack.slots}`}
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

/**
 * The cards this stack could take, as the picker's options.
 *
 * Searchable by the printed code as well as the name, for the reason the
 * shop's picker is: a card is looked up by its code as often as by what it is
 * called. The challenge and the consequence go in too, because the question
 * Control is actually answering here is "what do I want the Runners to hit" -
 * so being able to type "end the run" and see which cards do it is the point
 * of having a search rather than a list.
 *
 * The kind is not in there: a stack only ever offers its own kind, so every
 * option would carry the same word and it would match everything.
 */
function installOptions(cards: ProtectionCardSummary[]): PickerOption[] {
    return cards.map((card) => ({
        value: card.id,
        label: card.name,
        hint: [card.code, card.challenge].filter(Boolean).join(' · ') || null,
        search: [
            card.code ?? '',
            card.name,
            card.challenge,
            card.consequence,
            card.availability === 'research_only' ? 'research only' : '',
        ].join(' '),
    }));
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
    const [selected, setSelected] = useState<number | null>(null);

    /**
     * Why the last install was refused, kept per stack.
     *
     * It has to be drawn somewhere, and it has to be drawn *here*: a Facility
     * has two stacks and a game has a page full of Facilities, so every one of
     * these posts reports against the same `protection_card_type_id` key. A
     * page-level `errors` would put one stack's refusal under every stack on
     * screen. Same answer the research table's ScoreForm and the run screen's
     * useRunAction already reach for.
     *
     * The refusal that actually happens is the Corporation having no copy of
     * the card left: installing costs a copy out of its hand (3.3.4), the
     * picker offers the whole catalogue rather than only what is held, and
     * without this the button simply did nothing.
     */
    const [refusal, setRefusal] = useState<string | null>(null);

    if (disabled) {
        return (
            <p className="text-sm text-muted-foreground">
                Every {kind.toLowerCase()} slot is full.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-1">
            <div className="flex flex-wrap items-center gap-2">
                {/* The picker's own trigger is w-full, so the wrapper is what
                    flexes. min-w keeps it from collapsing to the chevron when a
                    narrow panel wraps the Install button onto its line. */}
                <div className="min-w-56 flex-1">
                    <SearchPicker
                        options={installOptions(options)}
                        value={selected}
                        onChange={(value) => {
                            setSelected(value);
                            setRefusal(null);
                        }}
                        placeholder={`Install one of ${options.length} cards…`}
                        searchPlaceholder="Name, code or what it does…"
                        emptyMessage={`No ${kind.toLowerCase()} card matches that. A card already in this Facility is not offered — 3.3.4 allows one copy of each.`}
                    />
                </div>
                <Button
                    size="sm"
                    disabled={selected === null}
                    onClick={() => {
                        router.post(
                            installCard.url({
                                game: gameId,
                                facility: facilityId,
                            }),
                            { protection_card_type_id: selected },
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setSelected(null);
                                    setRefusal(null);
                                },
                                onError: (errors) =>
                                    setRefusal(
                                        errors.protection_card_type_id ??
                                            Object.values(errors)[0] ??
                                            'That card could not be installed.',
                                    ),
                            },
                        );
                    }}
                >
                    Install
                </Button>
            </div>

            {refusal !== null && (
                <p role="alert" className="text-sm text-destructive">
                    {refusal}
                </p>
            )}
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
