import {
    DndContext,
    DragOverlay,
    MouseSensor,
    pointerWithin,
    rectIntersection,
    TouchSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type {
    Announcements,
    CollisionDetection,
    DragEndEvent,
    DragStartEvent,
} from '@dnd-kit/core';
import { router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import { FactionBadge } from '@/components/faction-badge';
import {
    ResearchCardButton,
    ResearchCardFace,
} from '@/components/research-card-face';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { leave, rejoin } from '@/routes/research';
import { play } from '@/routes/research/equations';
import type {
    OwnResearch,
    ResearchCardSummary,
    ResearchSessionSummary,
} from '@/types/game';

type Side = 'left' | 'right';

/** The two trays, as drop targets. */
const TRAY: Record<Side, string> = { left: 'set-left', right: 'set-right' };

/**
 * Where a card comes from, and where it goes back to.
 *
 * Both are drop targets meaning the same thing — take this card out of the
 * equation — because a card dragged out of a tray goes back where it came from
 * and a player should not have to remember which of the two rows that was.
 */
const POOL = 'pool';
const HAND = 'hand';

/** What travels with a card while it is in the air. */
type DragData = { card: ResearchCardSummary };

/**
 * The research table (rulebook 3.2.1): the turn order, the six public cards,
 * your five, and the equation you are building out of them.
 *
 * An equation is two sets of cards, so the builder is two trays, and a card
 * reaches one of them either way round. Clicking cycles it — into the first
 * tray, into the second, then back out — which is one control for every answer
 * and works with a thumb or a keyboard. Dragging says the same thing by
 * pointing at it, which is the gesture people reach for when the two sets are
 * laid out in front of them like cards on a table.
 *
 * Both are here because neither covers the other. Clicking is the only path for
 * a keyboard, and it is why there is no `KeyboardSensor` below: a keyboard drag
 * would capture Enter and Space from the buttons and hand back a slower way of
 * doing what the click already does. Dragging is the one that reads as moving a
 * card rather than toggling a checkbox, and it is the only way to move a card
 * straight from the first set to the second without cycling it past a state you
 * did not want.
 *
 * Nothing is validated in the browser beyond what the two trays already show.
 * Whether two sets are an equation is a rule with wild cards and set sizes in
 * it, it lives in App\Support\Equation on the server, and a second
 * implementation here would be a second thing to keep in step. What the page
 * does show is the arithmetic a player would do anyway — each side's total, and
 * whether they match.
 */
export function ResearchTable({
    session,
    own,
}: {
    session: ResearchSessionSummary | null;
    own: OwnResearch | null;
}) {
    const [assigned, setAssigned] = useState<Record<number, Side>>({});
    const [dragging, setDragging] = useState<ResearchCardSummary | null>(null);

    const cards = useMemo(() => {
        const byId = new Map<number, ResearchCardSummary>();

        for (const card of session?.pool ?? []) {
            byId.set(card.id, card);
        }

        for (const card of own?.hand ?? []) {
            byId.set(card.id, card);
        }

        return byId;
    }, [session, own]);

    // A card has to be dropped on something. dnd-kit's closestCenter would
    // always find a nearest tray however far away the pointer was, so a card
    // let go over empty space would silently join a set; requiring the pointer
    // to be inside a target means a drop that missed simply misses.
    const collisionDetection = useCallback<CollisionDetection>((args) => {
        const underPointer = pointerWithin(args);

        return underPointer.length > 0 ? underPointer : rectIntersection(args);
    }, []);

    const sensors = useSensors(
        // A little travel first, so a click on a card is still a click.
        useSensor(MouseSensor, { activationConstraint: { distance: 6 } }),
        // Touch holds instead of measuring distance: the cards sit in a page
        // that scrolls, and a swipe starting on one has to scroll it rather
        // than pick the card up.
        useSensor(TouchSensor, {
            activationConstraint: { delay: 200, tolerance: 8 },
        }),
    );

    if (session === null) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>The research table</CardTitle>
                    <CardDescription>
                        No sitting is open. Research Control deals the cards as
                        the Action phase begins.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    const canPlay = own?.can_play === true && own.playing && session.open;
    const chosen = (side: Side) =>
        Object.entries(assigned)
            .filter(([, value]) => value === side)
            .map(([id]) => cards.get(Number(id)))
            .filter((card): card is ResearchCardSummary => card !== undefined);

    const left = chosen('left');
    const right = chosen('right');
    const total = (side: ResearchCardSummary[]) =>
        side.reduce((sum, card) => sum + card.value, 0);

    // The next tray a card goes to. Left, then right, then back out of the
    // equation entirely, so one control cycles through every answer.
    const cycle = (id: number) =>
        setAssigned((current) => {
            const next = { ...current };

            if (next[id] === undefined) {
                next[id] = 'left';
            } else if (next[id] === 'left') {
                next[id] = 'right';
            } else {
                delete next[id];
            }

            return next;
        });

    const putIn = (id: number, side: Side) =>
        setAssigned((current) => ({ ...current, [id]: side }));

    const takeOut = (id: number) =>
        setAssigned((current) => {
            const next = { ...current };
            delete next[id];

            return next;
        });

    const handleDragStart = (event: DragStartEvent) =>
        setDragging(
            (event.active.data.current as DragData | undefined)?.card ?? null,
        );

    const handleDragEnd = (event: DragEndEvent) => {
        setDragging(null);

        const card = (event.active.data.current as DragData | undefined)?.card;

        if (card === undefined || event.over === null) {
            return;
        }

        if (event.over.id === TRAY.left) {
            putIn(card.id, 'left');
        } else if (event.over.id === TRAY.right) {
            putIn(card.id, 'right');
        } else {
            takeOut(card.id);
        }
    };

    const submit = () => {
        router.post(
            play.url(),
            {
                left: left.map((card) => card.id),
                right: right.map((card) => card.id),
            },
            {
                preserveScroll: true,
                onSuccess: () => setAssigned({}),
            },
        );
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={collisionDetection}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={() => setDragging(null)}
            accessibility={{ announcements }}
        >
            <Card>
                <CardHeader>
                    <CardTitle>The research table</CardTitle>
                    <CardDescription>
                        {session.open
                            ? 'Two sets of cards, the same number in each, every card in a set the same suit — and at least one out of your own hand. A card marked “No single” cannot be the only one on its side.'
                            : 'This sitting has closed.'}
                        {' · '}
                        {session.public_deck_remaining} card
                        {session.public_deck_remaining === 1 ? '' : 's'} left in
                        the public deck
                    </CardDescription>
                </CardHeader>

                <CardContent className="flex flex-col gap-6">
                    <div>
                        <h3 className="text-sm font-medium">Turn order</h3>
                        <ol className="mt-2 flex flex-wrap gap-2">
                            {session.seats.map((seat) => (
                                <li
                                    key={seat.corporation_id}
                                    className={
                                        seat.is_turn
                                            ? 'flex items-center gap-2 rounded-md border border-primary bg-primary/5 px-2 py-1 text-sm'
                                            : 'flex items-center gap-2 rounded-md border px-2 py-1 text-sm'
                                    }
                                >
                                    <span className="font-mono text-muted-foreground tabular-nums">
                                        {seat.order}
                                    </span>
                                    <FactionBadge faction={seat} size="small" />
                                    <span
                                        className={
                                            seat.playing
                                                ? undefined
                                                : 'text-muted-foreground line-through'
                                        }
                                    >
                                        {seat.name}
                                    </span>
                                    {seat.is_yours && (
                                        <Badge variant="outline">You</Badge>
                                    )}
                                    {seat.is_turn && <Badge>Playing</Badge>}
                                    {!seat.playing && seat.left_reason && (
                                        <span className="text-xs text-muted-foreground">
                                            {seat.left_reason}
                                        </span>
                                    )}
                                    <span className="text-xs text-muted-foreground">
                                        {seat.deck_remaining} in deck
                                    </span>
                                </li>
                            ))}
                            {session.seats.length === 0 && (
                                <li className="text-sm text-muted-foreground">
                                    Nobody is seated.
                                </li>
                            )}
                        </ol>
                    </div>

                    <Source id={POOL} active={canPlay}>
                        <h3 className="text-sm font-medium">
                            The public pool
                            <span className="ml-2 font-normal text-muted-foreground">
                                anybody may use these
                            </span>
                        </h3>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {session.pool.map((card) => (
                                <DraggableCard
                                    key={card.id}
                                    from="source"
                                    card={card}
                                    disabled={!canPlay}
                                    selected={assigned[card.id] !== undefined}
                                    onClick={() => cycle(card.id)}
                                    hint={trayHint(assigned[card.id])}
                                />
                            ))}
                            {session.pool.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    The pool is empty.
                                </p>
                            )}
                        </div>
                    </Source>

                    {own && (
                        <Source id={HAND} active={canPlay}>
                            <h3 className="text-sm font-medium">
                                Your hand
                                <span className="ml-2 font-normal text-muted-foreground">
                                    {own.deck_remaining} left in your deck
                                </span>
                            </h3>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {own.hand.map((card) => (
                                    <DraggableCard
                                        key={card.id}
                                        from="source"
                                        card={card}
                                        disabled={!canPlay}
                                        selected={
                                            assigned[card.id] !== undefined
                                        }
                                        onClick={() => cycle(card.id)}
                                        hint={trayHint(assigned[card.id])}
                                    />
                                ))}
                                {own.hand.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        You are holding nothing.
                                    </p>
                                )}
                            </div>
                        </Source>
                    )}

                    {canPlay && (
                        <div className="rounded-md border p-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Tray
                                    side="left"
                                    title="First set"
                                    cards={left}
                                    onRemove={(id) => cycle(id)}
                                />
                                <Tray
                                    side="right"
                                    title="Second set"
                                    cards={right}
                                    onRemove={(id) => cycle(id)}
                                />
                            </div>

                            <p className="mt-3 text-sm text-muted-foreground">
                                {total(left)} against {total(right)}
                                {left.length > 0 &&
                                    left.length === right.length &&
                                    total(left) === total(right) &&
                                    ' — balanced, so this pays a bonus as well'}
                            </p>

                            <div className="mt-3 flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    onClick={submit}
                                    disabled={
                                        !own?.is_your_turn ||
                                        left.length === 0 ||
                                        right.length === 0
                                    }
                                >
                                    Play this equation
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setAssigned({})}
                                    disabled={
                                        Object.keys(assigned).length === 0
                                    }
                                >
                                    Clear
                                </Button>
                                {!own?.is_your_turn && (
                                    <p className="self-center text-sm text-muted-foreground">
                                        Wait for your turn.
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {own?.can_play && session.open && (
                        <div className="flex flex-wrap items-center gap-2 border-t pt-4">
                            {own.playing ? (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                leave.url(),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Leave the table
                                    </Button>
                                    <p className="text-sm text-muted-foreground">
                                        You may leave and come back — 3.2.1
                                        makes it a choice, not a forfeit.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            router.post(
                                                rejoin.url(),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Sit back down
                                    </Button>
                                    <p className="text-sm text-muted-foreground">
                                        {own.left_reason ??
                                            'You are not playing.'}
                                    </p>
                                </>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* The card follows the pointer at its own size, so what is being
                dragged is the card rather than a ghost of one. */}
            <DragOverlay>
                {dragging ? (
                    <ResearchCardFace
                        card={dragging}
                        selected
                        className="rotate-2 opacity-95 shadow-lg"
                    />
                ) : null}
            </DragOverlay>
        </DndContext>
    );
}

function trayHint(side: Side | undefined): string {
    if (side === undefined) {
        return 'put in the first set';
    }

    return side === 'left'
        ? 'move to the second set'
        : 'take out of the equation';
}

/**
 * A row of cards a drag can start from and come back to.
 *
 * The whole row is the target rather than each card in it, because dropping a
 * card "back in the pool" means putting it down among the others, not landing
 * it on a particular one.
 */
function Source({
    id,
    active,
    children,
}: {
    id: string;
    active: boolean;
    children: React.ReactNode;
}) {
    const { setNodeRef, isOver } = useDroppable({ id, disabled: !active });

    return (
        <div
            ref={setNodeRef}
            className={cn(
                '-m-2 rounded-md border border-transparent p-2 transition-colors',
                isOver && 'border-dashed border-primary bg-primary/5',
            )}
        >
            {children}
        </div>
    );
}

/**
 * A card you can pick up, which is still the button you can click.
 *
 * Only dnd-kit's listeners go on the wrapper, never its `attributes`: those
 * announce a draggable that answers to the keyboard, and this one does not —
 * the button inside is the keyboard path, and it already reaches both sets and
 * back out. The wrapper is what carries the listeners so the button keeps its
 * own accessible name and its `aria-pressed`.
 */
function DraggableCard({
    from,
    card,
    disabled = false,
    selected = false,
    onClick,
    hint,
}: {
    /** Where this copy of the card is drawn — one card may be in both rows. */
    from: 'source' | 'tray';
    card: ResearchCardSummary;
    disabled?: boolean;
    selected?: boolean;
    onClick: () => void;
    hint?: string;
}) {
    const { setNodeRef, listeners, isDragging } = useDraggable({
        id: `${from}-${card.id}`,
        disabled,
        data: { card } satisfies DragData,
    });

    return (
        <span
            ref={setNodeRef}
            className={cn(
                'inline-flex shrink-0 rounded-md',
                !disabled && 'cursor-grab active:cursor-grabbing',
                isDragging && 'opacity-40',
            )}
            {...listeners}
        >
            <ResearchCardButton
                card={card}
                disabled={disabled}
                selected={selected}
                onClick={onClick}
                hint={hint}
            />
        </span>
    );
}

function Tray({
    side,
    title,
    cards,
    onRemove,
}: {
    side: Side;
    title: string;
    cards: ResearchCardSummary[];
    onRemove: (id: number) => void;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: TRAY[side] });

    return (
        <div>
            <h4 className="text-sm font-medium">{title}</h4>
            <div
                ref={setNodeRef}
                className={cn(
                    'mt-2 flex min-h-20 flex-wrap gap-2 rounded-md border border-dashed p-2 transition-colors',
                    isOver && 'border-primary bg-primary/5',
                )}
            >
                {cards.map((card) => (
                    <DraggableCard
                        key={card.id}
                        from="tray"
                        card={card}
                        selected
                        onClick={() => onRemove(card.id)}
                        hint="take out of this set"
                    />
                ))}
                {cards.length === 0 && (
                    <p className="self-center text-sm text-muted-foreground">
                        Click or drag cards in here.
                    </p>
                )}
            </div>
        </div>
    );
}

/**
 * What a screen reader is told while a card is being dragged.
 *
 * dnd-kit's defaults talk about sortable positions, and nothing here is
 * ordered: a set is a set, so what matters is which of the two a card landed
 * in — or that it went back out of the equation.
 */
const announcements: Announcements = {
    onDragStart: ({ active }) =>
        `Picked up ${(active.data.current as DragData | undefined)?.card.label ?? 'a card'}.`,
    onDragOver: ({ over }) =>
        over ? `Over ${placeName(over.id)}.` : 'Over nothing.',
    onDragEnd: ({ over }) =>
        over
            ? `Dropped into ${placeName(over.id)}.`
            : 'Dropped outside the table, so nothing changed.',
    onDragCancel: () => 'Cancelled. The card is back where it was.',
};

function placeName(id: string | number): string {
    if (id === TRAY.left) {
        return 'the first set';
    }

    if (id === TRAY.right) {
        return 'the second set';
    }

    return id === HAND ? 'your hand' : 'the public pool';
}
