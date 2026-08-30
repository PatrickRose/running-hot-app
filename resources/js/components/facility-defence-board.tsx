import {
    closestCenter,
    DndContext,
    pointerWithin,
    rectIntersection,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
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
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { RefObject } from 'react';
import { toast } from 'sonner';
import { CardFace } from '@/components/card-face';
import { GameIcon } from '@/components/game-icon';
import { StackEnd } from '@/components/stack-end';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    install as installRoute,
    quote as quoteRoute,
    remove as removeRoute,
    reorder as reorderRoute,
} from '@/routes/facilities/cards';
import type {
    CorporationFacilities,
    FacilitySummary,
    HandCard,
    InstalledProtectionCard,
    ProtectionKind,
    ProtectionStack,
    ReorderQuote,
} from '@/types/game';

/**
 * The hand is a drop target as well as a source: dragging a card off a Facility
 * and back here is how it is removed.
 */
const HAND = 'hand';

/**
 * Which stack a card is in. Facility and kind together, because a Facility has
 * two stacks and they are numbered independently (rulebook 3.3.4).
 */
type StackId = string;

function stackId(facilityId: number, kind: ProtectionKind): StackId {
    return `stack-${facilityId}-${kind}`;
}

/** What travels with a card while it is being dragged. */
type DragData =
    | { type: 'hand'; container: typeof HAND; card: HandCard }
    | {
          type: 'installed';
          container: StackId;
          facilityId: number;
          kind: ProtectionKind;
          card: InstalledProtectionCard;
      };

/**
 * Where in the stack a card sits, in words.
 *
 * Position 1 is the card Runners meet first (rulebook 3.3.4), so the stack reads
 * as an order of encounter rather than as a list of numbers.
 */
function ordinal(position: number): string {
    const suffixes = ['th', 'st', 'nd', 'rd'];
    const remainder = position % 100;

    return (
        position +
        (suffixes[(remainder - 20) % 10] ?? suffixes[remainder] ?? suffixes[0])
    );
}

/**
 * The lines a Protection Card prints, in the order it prints them.
 *
 * Shared by the hand and the installed stacks so a card reads the same wherever
 * it is: the card you are holding and the card you have installed are the same
 * card, and a player comparing them should not have to.
 */
function cardLines(card: HandCard | InstalledProtectionCard) {
    return [
        { label: 'Challenge', value: card.challenge },
        { label: '', value: card.consequence },
        {
            label: 'Charge',
            value: card.charge_consequence
                ? `${card.charge_cost}cr — ${card.charge_consequence}`
                : null,
        },
    ];
}

/**
 * Security arranging their own Corporation's defences by dragging (rulebook
 * 3.3.4).
 *
 * Three gestures, and they are not the same kind of act, which is why they do
 * not all commit the same way:
 *
 * - Hand to Facility installs, immediately. It costs no Credits; what it costs
 *   is a copy out of the Corporation's hand, and that is a thing you either did
 *   or did not do.
 * - Facility to hand removes, immediately. The first removal from a Facility
 *   each turn is free and the rest cost a Credit each, which the server reports
 *   back.
 * - Dragging within a stack only arranges. Nothing is charged until Confirm,
 *   because reordering costs 1 Credit per card that moves and at the table you
 *   lay the cards out and then pay once - not a Credit every time you change
 *   your mind.
 *
 * The Credit cost of an arrangement comes from the server as the cards move.
 * The rule is a longest-ascending-run over the old positions, and a second
 * implementation of that in the browser would drift from the one that charges.
 */
export function FacilityDefenceBoard({ own }: { own: CorporationFacilities }) {
    // Arrangements that have been dragged but not yet paid for, keyed by stack.
    const [pending, setPending] = useState<Record<StackId, number[]>>({});
    const [quotes, setQuotes] = useState<Record<StackId, ReorderQuote | null>>(
        {},
    );
    const [dragging, setDragging] = useState<DragData | null>(null);

    const handNode = useRef<HTMLDivElement | null>(null);

    /**
     * Which drop target a card is over.
     *
     * The hand is measured here rather than taken from dnd-kit, and both halves
     * of that matter.
     *
     * dnd-kit measures every target when a drag begins and then shifts those
     * rectangles by however far the page has scrolled since. That is right for
     * everything on this page except the hand, which is pinned and so does not
     * move when the page does - so the moment a drag auto-scrolls, the hand's
     * believed position walks off the screen it is still sitting on, and cards
     * dropped squarely into it land in whatever Facility is scrolled underneath.
     * Its own rectangle, read at the moment of the question, is never wrong.
     *
     * And the hand wins outright when the pointer is inside it, rather than
     * competing on distance: it floats over the page, so a drop that looks like
     * it landed in the hand did land in the hand. closestCenter would not agree
     * - it measures to the centre of each target, and the hand is a wide bar
     * whose centre is a long way from its own left-hand end.
     *
     * The passes below are the keyboard's, which has no pointer. Keyboard users
     * take a card off with the Remove button rather than by aiming at the hand.
     */
    const collisionDetection = useCallback<CollisionDetection>((args) => {
        const pointer = args.pointerCoordinates;
        const hand = handNode.current?.getBoundingClientRect();

        if (
            pointer &&
            hand &&
            pointer.x >= hand.left &&
            pointer.x <= hand.right &&
            pointer.y >= hand.top &&
            pointer.y <= hand.bottom
        ) {
            return [{ id: HAND }];
        }

        const underPointer = pointerWithin(args);

        if (underPointer.length > 0) {
            return underPointer;
        }

        const overlapping = rectIntersection(args);

        return overlapping.length > 0 ? overlapping : closestCenter(args);
    }, []);

    const sensors = useSensors(
        // A small distance so that a tap to open a card's tooltip is not read
        // as the start of a drag.
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const facilitiesById = useMemo(() => {
        const map = new Map<number, FacilitySummary>();
        own.facilities.forEach((facility) => map.set(facility.id, facility));

        return map;
    }, [own.facilities]);

    /** The order a stack is in on screen: the arrangement if there is one. */
    const orderFor = useCallback(
        (facility: FacilitySummary, stack: ProtectionStack): number[] =>
            pending[stackId(facility.id, stack.kind)] ??
            stack.cards.map((card) => card.id),
        [pending],
    );

    // Ask the server what each pending arrangement costs. Debounced, because a
    // drag across a stack of five settles through several orders on the way.
    useEffect(() => {
        const entries = Object.entries(pending);

        if (entries.length === 0) {
            return;
        }

        const controller = new AbortController();

        const timer = setTimeout(() => {
            entries.forEach(([key, order]) => {
                const [, facilityId, kind] = key.split('-');
                const params = new URLSearchParams({ kind });
                order.forEach((id) => params.append('order[]', String(id)));

                fetch(
                    `${quoteRoute.url({ facility: Number(facilityId) })}?${params}`,
                    {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    },
                )
                    .then((response) =>
                        response.ok ? response.json() : Promise.reject(),
                    )
                    .then((quote: ReorderQuote) =>
                        setQuotes((was) => ({ ...was, [key]: quote })),
                    )
                    // A quote that cannot be fetched leaves the cost unknown
                    // rather than wrong. Confirm still works: the server
                    // charges what the rules say either way.
                    .catch(() => setQuotes((was) => ({ ...was, [key]: null })));
            });
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [pending]);

    const discardArrangement = useCallback((key: StackId) => {
        setPending((was) => {
            const rest = { ...was };
            delete rest[key];

            return rest;
        });
        setQuotes((was) => {
            const rest = { ...was };
            delete rest[key];

            return rest;
        });
    }, []);

    function handleDragStart(event: DragStartEvent) {
        setDragging(
            (event.active.data.current as DragData | undefined) ?? null,
        );
    }

    function handleDragEnd(event: DragEndEvent) {
        setDragging(null);

        const { active, over } = event;
        const source = active.data.current as DragData | undefined;

        if (!over || !source) {
            return;
        }

        const overData = over.data.current as
            | { container?: string; sortable?: { containerId?: string } }
            | undefined;
        const target =
            overData?.container ??
            overData?.sortable?.containerId ??
            String(over.id);

        if (source.type === 'hand') {
            if (target !== HAND) {
                installInto(target, source.card);
            }

            return;
        }

        if (target === HAND) {
            removeCard(source);

            return;
        }

        if (target !== source.container) {
            toast.error(
                'Cards do not move straight from one Facility to another. Drag it back to your hand first, then into the other Facility.',
            );

            return;
        }

        arrange(source, over.id);
    }

    /** Drop a card from the hand onto a Facility. */
    function installInto(target: StackId, card: HandCard) {
        const facility = facilitiesById.get(Number(target.split('-')[1]));
        const stack = facility?.stacks.find(
            (candidate) => stackId(facility.id, candidate.kind) === target,
        );

        if (!facility || !stack) {
            return;
        }

        if (stack.kind !== card.kind) {
            toast.error(
                `${card.name} is a ${card.kind_label} card, so it cannot go in the ${stack.kind_label} stack.`,
            );

            return;
        }

        if (pending[target]) {
            toast.error(
                'Confirm or undo the arrangement in this stack before installing into it.',
            );

            return;
        }

        // Checked here as well as on the server so the refusal is instant and
        // says which Facility. The server is still the one that decides.
        if (stack.cards.length >= stack.slots) {
            toast.error(
                `${facility.name} already holds its ${stack.slots} ${stack.kind_label} cards.`,
            );

            return;
        }

        if (
            facility.stacks.some((candidate) =>
                candidate.cards.some(
                    (installed) => installed.card_type_id === card.card_type_id,
                ),
            )
        ) {
            toast.error(
                `${facility.name} already has a copy of ${card.name} installed.`,
            );

            return;
        }

        router.post(
            installRoute.url({ facility: facility.id }),
            { protection_card_type_id: card.card_type_id },
            { preserveScroll: true },
        );
    }

    function removeCard(source: Extract<DragData, { type: 'installed' }>) {
        const key = source.container;

        if (pending[key]) {
            toast.error(
                'Confirm or undo the arrangement in this stack before taking a card out of it.',
            );

            return;
        }

        router.delete(
            removeRoute.url({
                facility: source.facilityId,
                card: source.card.id,
            }),
            { preserveScroll: true },
        );
    }

    /** Move a card within its own stack. Arranged now, charged on Confirm. */
    function arrange(
        source: Extract<DragData, { type: 'installed' }>,
        overId: string | number,
    ) {
        const facility = facilitiesById.get(source.facilityId);
        const stack = facility?.stacks.find(
            (candidate) => candidate.kind === source.kind,
        );

        if (!facility || !stack) {
            return;
        }

        const current = orderFor(facility, stack);
        const from = current.indexOf(source.card.id);
        const to = current.indexOf(Number(String(overId).replace('card-', '')));

        if (from < 0 || to < 0 || from === to) {
            return;
        }

        const next = arrayMove(current, from, to);
        const original = stack.cards.map((card) => card.id);
        const key = source.container;

        // Dragged back to where it started, so there is nothing to confirm.
        if (next.join() === original.join()) {
            discardArrangement(key);

            return;
        }

        setPending((was) => ({ ...was, [key]: next }));
    }

    function confirmArrangement(
        facility: FacilitySummary,
        kind: ProtectionKind,
    ) {
        const key = stackId(facility.id, kind);
        const order = pending[key];

        if (!order) {
            return;
        }

        router.post(
            reorderRoute.url({ facility: facility.id }),
            { kind, order },
            {
                preserveScroll: true,
                onSuccess: () => discardArrangement(key),
            },
        );
    }

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={collisionDetection}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={() => setDragging(null)}
            accessibility={{ announcements }}
        >
            <Hand cards={own.hand} nodeRef={handNode} />

            <Card>
                <CardHeader>
                    <CardTitle>{own.name} — your Facilities</CardTitle>
                    <CardDescription>
                        {own.credits} Credits &middot; {own.physical_slots}{' '}
                        physical and {own.cyber_slots} cyber slots per Facility
                        &middot; {own.technology_capacity_per_facility}{' '}
                        technologies storable per Facility
                        {own.card_move_discount > 0 &&
                            ` · ${own.card_move_discount} Credit discount on moving cards`}
                        <br />
                        Runners come in at the top of a stack and work down.
                        Dragging within a stack costs 1 Credit for every card
                        that has to move, and nothing is charged until you
                        confirm it.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {own.facilities.map((facility) => (
                        <FacilityPanel
                            key={facility.id}
                            facility={facility}
                            orderFor={orderFor}
                            pending={pending}
                            quotes={quotes}
                            credits={own.credits}
                            onConfirm={confirmArrangement}
                            onDiscard={discardArrangement}
                        />
                    ))}
                    {own.facilities.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            You have no Facilities.
                        </p>
                    )}
                </CardContent>
            </Card>

            {/* The card follows the pointer at full size, so what is being
                dragged is the card itself rather than a ghost of a row. */}
            <DragOverlay>
                {dragging ? (
                    <CardFace
                        shape="landscape"
                        name={dragging.card.name}
                        code={dragging.card.code}
                        imagePath={dragging.card.image_path}
                        lines={cardLines(dragging.card)}
                        className="rotate-2 opacity-95 shadow-lg"
                    />
                ) : null}
            </DragOverlay>
        </DndContext>
    );
}

/**
 * The copies the Corporation is holding.
 *
 * Also the place a card goes to be removed, which is why it is a drop target
 * and why it says so: dragging a card out of a Facility and back into your hand
 * is exactly what taking it off the wall looks like.
 */
function Hand({
    cards,
    nodeRef,
}: {
    cards: HandCard[];
    nodeRef: RefObject<HTMLDivElement | null>;
}) {
    const [open, setOpen] = useState(true);
    const { setNodeRef, isOver } = useDroppable({
        id: HAND,
        data: { container: HAND },
    });

    // Held for the board as well as for dnd-kit: the board measures this
    // element itself while a card is in the air, for the reason in
    // collisionDetection below.
    const ref = (node: HTMLDivElement | null) => {
        setNodeRef(node);
        nodeRef.current = node;
    };

    // A kind with nothing in it is simply absent, rather than an empty row.
    const kinds = (['physical', 'cyber'] as const)
        .map((kind) => ({
            kind,
            held: cards.filter((card) => card.kind === kind),
        }))
        .filter(({ held }) => held.length > 0);

    return (
        // Pinned, because the hand is one end of every gesture on this page:
        // a Facility you have scrolled to is no use if the cards are three
        // screens up, and neither is a hand you have to scroll back to in
        // order to take a card off. Both ends have to be on screen at once.
        <Card
            ref={ref}
            className={`sticky top-0 z-20 gap-3 py-3 shadow-md transition-colors ${
                isOver ? 'border-primary bg-primary/10' : ''
            }`}
        >
            <CardHeader className="flex flex-row flex-wrap items-baseline gap-x-3 gap-y-1">
                <CardTitle>Your cards</CardTitle>
                <CardDescription className="flex-1">
                    Drag onto a Facility to install at the outermost slot; drag
                    back here to take a card off.
                </CardDescription>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => setOpen((was) => !was)}
                    aria-expanded={open}
                >
                    {open ? 'Hide' : `Show (${cards.length})`}
                </Button>
            </CardHeader>

            {/* Collapsed, the hand is still a drop target: it becomes a slim
                bar to drop a card onto when what you want is the room to see
                your Facilities rather than your hand. */}
            {open ? (
                <CardContent className="grid grid-cols-2 gap-4">
                    {/* Grouped by kind rather than shown as one row. A physical
                        card cannot go in a cyber stack, and at this size the
                        artwork does not say which it is - so the heading says
                        it once for every card underneath it. Side by side
                        rather than stacked, so the whole hand is one card tall
                        and pinning it costs the Facilities little room. */}
                    {kinds.map(({ kind, held }) => (
                        <div key={kind} className="flex min-w-0 flex-col gap-1">
                            <p className="flex items-center gap-1.5 text-sm font-medium">
                                <GameIcon
                                    glyph={held[0].kind_glyph}
                                    label={held[0].kind_label}
                                />
                                <span aria-hidden="true">
                                    {held[0].kind_label}
                                </span>
                            </p>
                            <ul
                                aria-label={`${held[0].kind_label} cards in your hand`}
                                className="flex gap-3 overflow-x-auto pb-1"
                            >
                                {held.map((card) => (
                                    <HandCardItem
                                        key={card.card_type_id}
                                        card={card}
                                    />
                                ))}
                            </ul>
                        </div>
                    ))}
                    {cards.length === 0 && (
                        <p className="col-span-2 text-sm text-muted-foreground">
                            Every copy you hold is installed. Control sets what
                            your Corporation owns.
                        </p>
                    )}
                </CardContent>
            ) : (
                <CardContent className="text-sm text-muted-foreground">
                    {cards.length === 0
                        ? 'Every copy you hold is installed.'
                        : `${cards.length} card(s) hidden. Drop a card here to take it off a Facility.`}
                </CardContent>
            )}
        </Card>
    );
}

function HandCardItem({ card }: { card: HandCard }) {
    // Dragged rather than sorted: a hand has no order to keep, and useSortable
    // needs a SortableContext around it that a hand has no reason to have.
    const { attributes, listeners, setNodeRef, transform, isDragging } =
        useDraggable({
            id: `hand-${card.card_type_id}`,
            data: { type: 'hand', container: HAND, card } satisfies DragData,
        });

    return (
        <li
            ref={setNodeRef}
            style={{
                transform: CSS.Translate.toString(transform),
                opacity: isDragging ? 0.4 : undefined,
            }}
            className="flex shrink-0 cursor-grab touch-none flex-col items-start gap-1 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
            {...attributes}
            {...listeners}
        >
            <CardFace
                shape="landscape"
                name={card.name}
                code={card.code}
                imagePath={card.image_path}
                lines={cardLines(card)}
                footer={`${card.copies_in_hand} in hand`}
            />
        </li>
    );
}

function FacilityPanel({
    facility,
    orderFor,
    pending,
    quotes,
    credits,
    onConfirm,
    onDiscard,
}: {
    facility: FacilitySummary;
    orderFor: (facility: FacilitySummary, stack: ProtectionStack) => number[];
    pending: Record<StackId, number[]>;
    quotes: Record<StackId, ReorderQuote | null>;
    credits: number;
    onConfirm: (facility: FacilitySummary, kind: ProtectionKind) => void;
    onDiscard: (key: StackId) => void;
}) {
    return (
        <div className="rounded-md border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium">
                    {facility.name}{' '}
                    <span className="text-muted-foreground">
                        &middot; {facility.facility_type}
                    </span>
                </p>
                <div className="flex flex-wrap gap-2">
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
                    {facility.security.budget > 0 && (
                        <Badge variant="outline">
                            {facility.security.budget -
                                facility.security.budget_spent}{' '}
                            of {facility.security.budget}cr left
                        </Badge>
                    )}
                </div>
            </div>

            <div className="mt-3 grid gap-4 lg:grid-cols-2">
                {facility.stacks.map((stack) => (
                    <StackPanel
                        key={stack.kind}
                        facility={facility}
                        stack={stack}
                        order={orderFor(facility, stack)}
                        arranged={Boolean(
                            pending[stackId(facility.id, stack.kind)],
                        )}
                        quote={quotes[stackId(facility.id, stack.kind)]}
                        credits={credits}
                        onConfirm={onConfirm}
                        onDiscard={onDiscard}
                    />
                ))}
            </div>
        </div>
    );
}

function StackPanel({
    facility,
    stack,
    order,
    arranged,
    quote,
    credits,
    onConfirm,
    onDiscard,
}: {
    facility: FacilitySummary;
    stack: ProtectionStack;
    order: number[];
    arranged: boolean;
    quote: ReorderQuote | null | undefined;
    credits: number;
    onConfirm: (facility: FacilitySummary, kind: ProtectionKind) => void;
    onDiscard: (key: StackId) => void;
}) {
    const key = stackId(facility.id, stack.kind);
    const { setNodeRef, isOver } = useDroppable({
        id: key,
        data: { container: key },
    });

    const byId = new Map(stack.cards.map((card) => [card.id, card]));
    const cards = order
        .map((id) => byId.get(id))
        .filter((card): card is InstalledProtectionCard => card !== undefined);

    return (
        <div className="flex flex-col gap-1">
            <p className="flex items-center gap-1.5 text-sm font-medium">
                <GameIcon glyph={stack.kind_glyph} label={stack.kind_label} />
                <span aria-hidden="true">{stack.kind_label}</span>
                <span className="font-normal text-muted-foreground">
                    {stack.cards.length}/{stack.slots}
                </span>
            </p>

            {/* Which end the Runners come in at. The stack is drawn in the
                order they are met - a stack is a stack, and reading down it is
                reading the order the cards are met in - so naming both ends
                turns a column of cards into the corridor it represents, which
                is the one thing "Met 1st" on a card cannot say by itself. */}
            <StackEnd label="Runners arrive" />

            <SortableContext
                id={key}
                items={cards.map((card) => `card-${card.id}`)}
                strategy={verticalListSortingStrategy}
            >
                <ol
                    ref={setNodeRef}
                    aria-label={`${stack.kind_label} stack of ${facility.name}`}
                    className={`flex min-h-12 flex-col gap-3 rounded-md border border-dashed p-2 transition-colors ${
                        isOver
                            ? 'border-primary bg-primary/5'
                            : 'border-transparent'
                    }`}
                >
                    {cards.map((card, index) => (
                        <InstalledCardItem
                            key={card.id}
                            card={card}
                            facility={facility}
                            kind={stack.kind}
                            container={key}
                            // The position it would take once confirmed, not
                            // the one it holds: a stack mid-arrangement should
                            // read as what you are making it.
                            position={index + 1}
                            removable={!arranged}
                        />
                    ))}
                    {cards.length === 0 && (
                        <li className="self-center px-1 text-sm text-muted-foreground">
                            Undefended. Drag a {stack.kind_label} card here.
                        </li>
                    )}
                </ol>
            </SortableContext>

            <StackEnd label={`into ${facility.name}`} />

            {arranged && (
                <ArrangementBar
                    quote={quote}
                    credits={credits}
                    onConfirm={() => onConfirm(facility, stack.kind)}
                    onDiscard={() => onDiscard(key)}
                />
            )}
        </div>
    );
}

/**
 * What the arrangement on screen will cost, and the two ways out of it.
 *
 * The cost is the server's answer rather than this component's guess, so while
 * it is in flight the bar says so instead of showing a number that might be
 * about to change.
 */
function ArrangementBar({
    quote,
    credits,
    onConfirm,
    onDiscard,
}: {
    quote: ReorderQuote | null | undefined;
    credits: number;
    onConfirm: () => void;
    onDiscard: () => void;
}) {
    const unaffordable = quote ? !quote.affordable : false;

    return (
        <div
            className="flex flex-wrap items-center gap-2 rounded-md border border-primary/40 bg-primary/5 p-2 text-sm"
            role="status"
        >
            <span className={unaffordable ? 'text-destructive' : undefined}>
                {quote === undefined && 'Working out what this costs…'}
                {quote === null && 'Not yet arranged.'}
                {quote &&
                    (quote.cost === 0
                        ? quote.discount > 0
                            ? `${quote.moved} card(s) move, covered by your Factory discount.`
                            : 'Nothing moves, so nothing is charged.'
                        : `${quote.moved} card(s) move — ${quote.cost} Credit(s) of your ${credits}.`)}
                {unaffordable && ' You cannot afford this.'}
            </span>

            <div className="ml-auto flex gap-2">
                <Button size="sm" variant="ghost" onClick={onDiscard}>
                    Undo
                </Button>
                <Button
                    size="sm"
                    onClick={onConfirm}
                    disabled={quote === undefined || unaffordable}
                >
                    Confirm
                </Button>
            </div>
        </div>
    );
}

function InstalledCardItem({
    card,
    facility,
    kind,
    container,
    position,
    removable,
}: {
    card: InstalledProtectionCard;
    facility: FacilitySummary;
    kind: ProtectionKind;
    container: StackId;
    position: number;
    removable: boolean;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({
        id: `card-${card.id}`,
        data: {
            type: 'installed',
            container,
            facilityId: facility.id,
            kind,
            card,
        } satisfies DragData,
    });

    return (
        <li
            ref={setNodeRef}
            style={{
                transform: CSS.Translate.toString(transform),
                transition,
                opacity: isDragging ? 0.4 : undefined,
            }}
            className="flex shrink-0 items-center gap-2"
        >
            <div
                className="cursor-grab touch-none rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
                {...attributes}
                {...listeners}
            >
                <CardFace
                    shape="landscape"
                    name={card.name}
                    code={card.code}
                    imagePath={card.image_path}
                    lines={cardLines(card)}
                    footer={`Met ${ordinal(position)}`}
                />
            </div>

            {/* A real button as well as the drag, because the hand can be
                scrolled off screen and "drag it somewhere else to delete it" is
                a poor way to ask for the one gesture that costs Credits. */}
            <Button
                size="sm"
                variant="ghost"
                className="text-destructive"
                disabled={!removable}
                onClick={() =>
                    router.delete(
                        removeRoute.url({
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
    );
}

/**
 * What a screen reader is told while a card is being dragged.
 *
 * dnd-kit's defaults talk about sortable positions, which say nothing about
 * what is happening here: the same gesture installs, arranges or removes
 * depending on where the card lands.
 */
const announcements: Announcements = {
    onDragStart: ({ active }) =>
        `Picked up ${(active.data.current as DragData | undefined)?.card.name ?? 'a card'}.`,
    onDragOver: ({ over }) =>
        over
            ? `Over ${over.id === HAND ? 'your hand' : 'a Facility stack'}.`
            : 'Not over a drop target.',
    onDragEnd: ({ over }) =>
        over
            ? over.id === HAND
                ? 'Dropped into your hand.'
                : 'Dropped into a Facility stack.'
            : 'Dropped outside any stack, so nothing changed.',
    onDragCancel: () => 'Cancelled. The card is back where it was.',
};
