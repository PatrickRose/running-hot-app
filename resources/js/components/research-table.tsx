import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { FactionBadge } from '@/components/faction-badge';
import { ResearchCardButton } from '@/components/research-card-face';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { leave, rejoin } from '@/routes/research';
import { play } from '@/routes/research/equations';
import type {
    OwnResearch,
    ResearchCardSummary,
    ResearchSessionSummary,
} from '@/types/game';

type Side = 'left' | 'right';

/**
 * The research table (rulebook 3.2.1): the turn order, the six public cards,
 * your five, and the equation you are building out of them.
 *
 * An equation is two sets of cards, so the builder is two trays. A card is
 * clicked into the left tray, clicked again to move it to the right, and a
 * third time to put it back — which is the whole gesture, and it works the same
 * with a mouse, a thumb or a keyboard. The alternative, dragging, would be a
 * second way to say the same thing: unlike the Facility board, where the order
 * of a stack is the point, nothing here is ordered.
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
        <Card>
            <CardHeader>
                <CardTitle>The research table</CardTitle>
                <CardDescription>
                    {session.open
                        ? 'Two sets of cards, the same number in each, every card in a set the same suit — and at least one out of your own hand.'
                        : 'This sitting has closed.'}
                    {' · '}
                    {session.public_deck_remaining} card
                    {session.public_deck_remaining === 1 ? '' : 's'} left in the
                    public deck
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

                <div>
                    <h3 className="text-sm font-medium">
                        The public pool
                        <span className="ml-2 font-normal text-muted-foreground">
                            anybody may use these
                        </span>
                    </h3>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {session.pool.map((card) => (
                            <ResearchCardButton
                                key={card.id}
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
                </div>

                {own && (
                    <div>
                        <h3 className="text-sm font-medium">
                            Your hand
                            <span className="ml-2 font-normal text-muted-foreground">
                                {own.deck_remaining} left in your deck
                            </span>
                        </h3>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {own.hand.map((card) => (
                                <ResearchCardButton
                                    key={card.id}
                                    card={card}
                                    disabled={!canPlay}
                                    selected={assigned[card.id] !== undefined}
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
                    </div>
                )}

                {canPlay && (
                    <div className="rounded-md border p-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Tray
                                title="First set"
                                cards={left}
                                onRemove={(id) => cycle(id)}
                            />
                            <Tray
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
                                disabled={Object.keys(assigned).length === 0}
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
                                    You may leave and come back — 3.2.1 makes it
                                    a choice, not a forfeit.
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
                                    {own.left_reason ?? 'You are not playing.'}
                                </p>
                            </>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
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

function Tray({
    title,
    cards,
    onRemove,
}: {
    title: string;
    cards: ResearchCardSummary[];
    onRemove: (id: number) => void;
}) {
    return (
        <div>
            <h4 className="text-sm font-medium">{title}</h4>
            <div className="mt-2 flex min-h-20 flex-wrap gap-2 rounded-md border border-dashed p-2">
                {cards.map((card) => (
                    <ResearchCardButton
                        key={card.id}
                        card={card}
                        selected
                        onClick={() => onRemove(card.id)}
                        hint="take out of this set"
                    />
                ))}
                {cards.length === 0 && (
                    <p className="self-center text-sm text-muted-foreground">
                        Click cards above to add them.
                    </p>
                )}
            </div>
        </div>
    );
}
