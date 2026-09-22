import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AgendaCardPanel } from '@/components/agenda-card-panel';
import { CouncilItem } from '@/components/council-item';
import { FactionBadge } from '@/components/faction-badge';
import Heading from '@/components/heading';
import { RecessClock } from '@/components/recess-clock';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import {
    attendance,
    chair,
    hand as handToChair,
    recess,
    rotation,
} from '@/routes/control/council';
import {
    annotate,
    destroy as destroyCard,
    store as storeCard,
} from '@/routes/control/council/agenda-cards';
import { update as ruleOnAmendment } from '@/routes/control/council/amendments';
import { store as applyPenalty } from '@/routes/control/council/penalties';
import { store as seatSomebody } from '@/routes/control/council/seats';
import { index, show } from '@/routes/control/games';
import type {
    AgendaCardView,
    CouncilBoard,
    CouncilControlBoard,
    CouncilOwnSeat,
    CouncilSeatCandidate,
    CouncilSeatView,
    CouncilSessionView,
    GameSummary,
} from '@/types/game';

/**
 * The shadcn Select is a listbox with its own state; these are plain selects in
 * forms, styled to match the Input beside them — the same class the amendment
 * form and the card holdings use.
 */
const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

type Props = {
    game: GameSummary;
    council: CouncilBoard;
    control: CouncilControlBoard;
};

/**
 * Control's side of the Council (rulebook 3.1).
 *
 * The smaller half on purpose. The Council is the CEOs' sub-game and the Chair
 * runs it from /council, so what is here is the four things the rulebook gives
 * Control — the deck and the draw, remarks on a custom agenda, sign-off on an
 * amendment, and the cost of an empty seat — plus the overrides Control has
 * over anything else in this application.
 *
 * The sitting itself is the same component players see, because Control's view
 * of a vote is the Chair's view: every breakdown, secret or not.
 */
export default function ControlCouncil({ game, council, control }: Props) {
    const session = council.session;

    return (
        <>
            <Head title={`Council — ${game.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Council"
                        description={
                            council.turn === null
                                ? 'The game has not started.'
                                : `Turn ${council.turn}`
                        }
                    />
                    <div className="flex items-center gap-4">
                        {session && <RecessClock session={session} />}
                        <Button
                            variant="ghost"
                            onClick={() =>
                                router.get(show.url({ game: game.id }))
                            }
                        >
                            Back to the game
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>The sitting</CardTitle>
                        <CardDescription>
                            You pick the cards the Council is asked about and
                            hand them to the Chair, who keeps two. The Chair
                            rotates in the order below, which Council Control
                            announces on the day.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {/* Who is chairing, said outright rather than left to
                            be found. It was only ever a badge inside the
                            rotation list, and a seat can chair without being in
                            the rotation at all - which is how every game opens,
                            with HM Government in the Chair and nowhere on this
                            panel saying so. */}
                        {session && (
                            <div className="flex flex-wrap items-center gap-2 rounded-md bg-primary/10 px-3 py-2 text-sm ring-1 ring-primary/30">
                                {session.chair ? (
                                    <>
                                        <FactionBadge
                                            faction={session.chair}
                                            size="small"
                                        />
                                        <span className="font-medium">
                                            {session.chair.name}
                                        </span>
                                        is in the Chair this turn
                                    </>
                                ) : (
                                    <span className="font-medium">
                                        The Chair is vacant
                                    </span>
                                )}
                            </div>
                        )}

                        {session && (
                            <p className="text-sm text-muted-foreground">
                                {session.tabled_count} of{' '}
                                {session.maximum_items} agenda items this turn
                                {session.has_handed &&
                                    !session.chair_has_chosen &&
                                    ' · the Chair is choosing'}
                            </p>
                        )}

                        <Rotation gameId={game.id} control={control} />

                        {session && <Recess gameId={game.id} />}
                    </CardContent>
                </Card>

                <section className="flex flex-col gap-4">
                    <h2 className="text-lg font-medium">Up for vote</h2>
                    {council.items.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nothing on the agenda yet.
                        </p>
                    ) : (
                        council.items.map((item) => (
                            <CouncilItem
                                key={item.id}
                                item={item}
                                viewer={council.viewer}
                                sessionId={session?.id ?? 0}
                            />
                        ))
                    )}
                </section>

                {control.with_control.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Waiting on your remarks</CardTitle>
                            <CardDescription>
                                A player&rsquo;s custom agenda. Add anything you
                                want said about it and give it back to them;
                                they decide whether to take it to the Chair.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {control.with_control.map((card) => (
                                <Annotate
                                    key={card.id}
                                    gameId={game.id}
                                    card={card}
                                />
                            ))}
                        </CardContent>
                    </Card>
                )}

                {control.amendments.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Amendments to sign off</CardTitle>
                            <CardDescription>
                                The Chair may add, remove or reword a
                                resolution, and none of it takes effect until
                                Council Control agrees (3.1.4).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {control.amendments.map((amendment) => (
                                <div
                                    key={amendment.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {amendment.card_title}
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                {
                                                    amendment.pending_amendment_label
                                                }
                                            </Badge>
                                        </p>
                                        <p className="text-muted-foreground">
                                            {amendment.pending_text
                                                ? `“${amendment.text}” → “${amendment.pending_text}”`
                                                : amendment.text}
                                            {amendment.proposed_by &&
                                                ` — proposed by ${amendment.proposed_by}`}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    ruleOnAmendment.url({
                                                        game: game.id,
                                                        resolution:
                                                            amendment.id,
                                                    }),
                                                    { approve: true },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Sign off
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    ruleOnAmendment.url({
                                                        game: game.id,
                                                        resolution:
                                                            amendment.id,
                                                    }),
                                                    { approve: false },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Refuse
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>The register</CardTitle>
                        <CardDescription>
                            Attendance is not mandatory, and missing either
                            phase costs Political Will (3.1.2). The rulebook
                            names no figure, so the amount is yours — nothing is
                            charged until you charge it, and every penalty lands
                            in the tracker ledger.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {control.seats.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No Corporations, so no seats.
                            </p>
                        ) : (
                            control.seats.map((seat) => (
                                <Seat
                                    key={seat.corporation_id}
                                    gameId={game.id}
                                    seat={seat}
                                    defaultPenalty={control.absence_penalty}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Seats at the Council</CardTitle>
                        <CardDescription>
                            Rulebook 3.1 seats the five CEOs, who vote with
                            their Corporation&rsquo;s Political Will. Anybody
                            else is here because you put them here — HM
                            Government and its bloc of six, or whoever a Runner
                            Representative turns out to be. A seat may take the
                            Chair as readily as a Corporation: the rotation is
                            an order you announce on the day, and the game opens
                            with the Government chairing.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {control.own_seats.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Only the Corporations are at the Council.
                            </p>
                        ) : (
                            control.own_seats.map((seat) => (
                                <OwnSeat
                                    key={seat.id}
                                    gameId={game.id}
                                    seat={seat}
                                />
                            ))
                        )}

                        <SeatSomebody
                            gameId={game.id}
                            seatable={control.seatable}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>The agenda deck</CardTitle>
                        <CardDescription>
                            The game&rsquo;s own deck, seeded with every new
                            game. Pick what this turn&rsquo;s Council is asked
                            about, and write more for the game you are running.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {session && (
                            <DeckPicker
                                gameId={game.id}
                                session={session}
                                deck={control.deck}
                                hand={council.hand}
                            />
                        )}

                        <DeckComposer gameId={game.id} />

                        {control.deck.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing is left in the deck. Everything has been
                                handed over, voted on, or taken out.
                            </p>
                        ) : (
                            control.deck.map((card) => (
                                <div
                                    key={card.id}
                                    className="rounded-md border p-3"
                                >
                                    <AgendaCardPanel card={card}>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="self-start"
                                            onClick={() =>
                                                router.delete(
                                                    destroyCard.url({
                                                        game: game.id,
                                                        card: card.id,
                                                    }),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Take it out of the deck
                                        </Button>
                                    </AgendaCardPanel>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/**
 * Picking what the Council is asked about (rulebook 3.1.1).
 *
 * Control holds the deck and reads it, so which cards go up is a judgement
 * about the game in front of you rather than a shuffle. Three is what the
 * rulebook has Control hand over and what this offers; the count is not
 * enforced, and the Chair keeps two of whatever arrives.
 *
 * The whole hand is sent every time, so unticking a card picked by mistake is
 * the same act as picking one and it goes back to the deck. That stops the
 * moment the Chair keeps two, because by then the discard has happened and the
 * agenda is theirs.
 */
function DeckPicker({
    gameId,
    session,
    deck,
    hand,
}: {
    gameId: number;
    session: CouncilSessionView;
    deck: AgendaCardView[];
    hand: AgendaCardView[];
}) {
    const [picked, setPicked] = useState<number[]>(() =>
        hand.map((card) => card.id),
    );

    if (session.chair_has_chosen) {
        return (
            <p className="text-sm text-muted-foreground">
                The Chair has chosen from this turn&rsquo;s cards. Anything else
                reaches the agenda as an urgent item, or by being promoted.
            </p>
        );
    }

    const choices = [...hand, ...deck];

    if (choices.length === 0) {
        return null;
    }

    const toggle = (id: number) =>
        setPicked((current) =>
            current.includes(id)
                ? current.filter((existing) => existing !== id)
                : [...current, id],
        );

    return (
        <div className="flex flex-col gap-3 rounded-md border border-dashed p-3">
            <p className="text-sm font-medium">
                {session.has_handed
                    ? 'In the Chair\u2019s hand'
                    : `Pick ${session.cards_handed} for the Chair`}
            </p>

            <ul className="flex flex-col gap-1">
                {choices.map((card) => (
                    <li key={card.id}>
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={picked.includes(card.id)}
                                onChange={() => toggle(card.id)}
                            />
                            <span>
                                {card.title}
                                <span className="ml-2 text-muted-foreground">
                                    {card.resolutions.length} resolutions
                                </span>
                            </span>
                        </label>
                    </li>
                ))}
            </ul>

            <div className="flex flex-wrap items-center gap-3">
                <Button
                    size="sm"
                    disabled={picked.length === 0}
                    onClick={() =>
                        router.post(
                            handToChair.url({ game: gameId }),
                            { cards: picked },
                            { preserveScroll: true },
                        )
                    }
                >
                    {session.has_handed
                        ? 'Change what the Chair holds'
                        : `Hand ${picked.length} to the Chair`}
                </Button>

                {picked.length !== session.cards_handed && (
                    <span className="text-xs text-muted-foreground">
                        The rulebook has Control hand over{' '}
                        {session.cards_handed}. The Chair keeps two of whatever
                        arrives.
                    </span>
                )}
            </div>
        </div>
    );
}

/**
 * One seat that is not a Corporation: how many votes it carries, and the way
 * back out.
 *
 * The number is editable in place rather than behind an edit mode, because
 * changing it is the likely thing to want: a bloc is a judgement Control makes
 * and may revise mid-game.
 */
function OwnSeat({ gameId, seat }: { gameId: number; seat: CouncilOwnSeat }) {
    const [votes, setVotes] = useState(String(seat.votes ?? ''));
    const changed = votes !== String(seat.votes ?? '');

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm">
            <div>
                <p className="font-medium">
                    {seat.name}
                    {seat.is_chair && (
                        <Badge variant="outline" className="ml-2">
                            In the Chair this turn
                        </Badge>
                    )}
                </p>
                <p className="text-muted-foreground">
                    {seat.role_label}
                    {seat.team && ` · ${seat.team}`}
                    {seat.chair_order === null
                        ? ' · not in the Chair rotation'
                        : ` · ${seat.chair_order} in the Chair rotation`}
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <Label
                    htmlFor={`votes-${seat.id}`}
                    className="text-muted-foreground"
                >
                    Votes
                </Label>
                <Input
                    id={`votes-${seat.id}`}
                    type="number"
                    min={1}
                    className="w-20"
                    value={votes}
                    onChange={(event) => setVotes(event.target.value)}
                />
                <Button
                    size="sm"
                    variant="outline"
                    disabled={!changed || votes === ''}
                    onClick={() =>
                        router.post(
                            seatSomebody.url({ game: gameId }),
                            { character_id: seat.id, votes: Number(votes) },
                            { preserveScroll: true },
                        )
                    }
                >
                    Set
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    disabled={seat.is_chair}
                    onClick={() =>
                        router.post(
                            chair.url({ game: gameId }),
                            { chair_type: 'character', chair_id: seat.id },
                            { preserveScroll: true },
                        )
                    }
                >
                    Give them the Chair now
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                        router.post(
                            seatSomebody.url({ game: gameId }),
                            { character_id: seat.id, votes: null },
                            { preserveScroll: true },
                        )
                    }
                >
                    Take the seat away
                </Button>
            </div>
        </div>
    );
}

/**
 * Seating somebody new.
 *
 * The list is everybody in the game bar the CEOs, who vote as their
 * Corporation already — offering one here would be offering a second vote, and
 * the server refuses it rather than storing a number that does nothing.
 */
function SeatSomebody({
    gameId,
    seatable,
}: {
    gameId: number;
    seatable: CouncilSeatCandidate[];
}) {
    const [characterId, setCharacterId] = useState('');
    const [votes, setVotes] = useState('6');

    if (seatable.length === 0) {
        return null;
    }

    return (
        <form
            className="flex flex-wrap items-end gap-2 rounded-md border border-dashed p-3"
            onSubmit={(event) => {
                event.preventDefault();

                router.post(
                    seatSomebody.url({ game: gameId }),
                    {
                        character_id: Number(characterId),
                        votes: Number(votes),
                    },
                    {
                        preserveScroll: true,
                        onSuccess: () => setCharacterId(''),
                    },
                );
            }}
        >
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="seat-character">Give somebody a seat</Label>
                <select
                    id="seat-character"
                    className={SELECT_CLASS}
                    value={characterId}
                    onChange={(event) => setCharacterId(event.target.value)}
                >
                    <option value="">Choose a character…</option>
                    {seatable.map((character) => (
                        <option key={character.id} value={character.id}>
                            {character.name} — {character.role_label}
                            {character.team && ` (${character.team})`}
                        </option>
                    ))}
                </select>
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="seat-votes">Votes</Label>
                <Input
                    id="seat-votes"
                    type="number"
                    min={1}
                    className="w-20"
                    value={votes}
                    onChange={(event) => setVotes(event.target.value)}
                />
            </div>

            <Button
                type="submit"
                size="sm"
                disabled={characterId === '' || votes === ''}
            >
                Seat them
            </Button>
        </form>
    );
}

/**
 * The Chair rotation, which Council Control announces on the day (3.1.1).
 *
 * Every Corporation is in it because it is a Corporation. A seat Control has
 * given somebody is in it only when Control has put it there — so the list
 * below is the rotation, and the seats under it are the ones waiting to join.
 * The game opens with HM Government at the front of it.
 *
 * Rearranging is local until it is saved, because that is what laying a list
 * out is. Giving somebody the Chair now is immediate, because it is a ruling
 * about the sitting in front of you rather than an order for the turns after.
 */
function Rotation({
    gameId,
    control,
}: {
    gameId: number;
    control: CouncilControlBoard;
}) {
    const announced = control.rotation.map((row) => row.key);
    const [order, setOrder] = useState(announced);
    const [drawn, setDrawn] = useState(announced.join('|'));

    // Rearranging is local; *who is in the list* is the server's. Seating
    // somebody, taking their seat away, adding a seat to the rotation and
    // taking one out all post and come back with a new rotation, and without
    // this the list would go on drawing the one it was first handed - so the
    // Add and Take out buttons looked like they did nothing at all.
    //
    // Adjusted during render rather than in an effect, which is React's own
    // answer for state derived from a prop: an effect would draw the stale
    // list once and correct it afterwards. An order Control has rearranged but
    // not saved survives a poll, because the server's list is unchanged.
    if (drawn !== announced.join('|')) {
        setDrawn(announced.join('|'));
        setOrder(announced);
    }

    // Keyed the way the server keys them: a Corporation and a character can
    // share a row id, and the bare number would put one in the other's place.
    const seats = new Map(control.rotation.map((row) => [row.key, row]));

    const waiting = control.own_seats.filter(
        (seat) => !order.includes(`character:${seat.id}`),
    );

    const move = (key: string, direction: -1 | 1) => {
        setOrder((current) => {
            const index = current.indexOf(key);
            const target = index + direction;

            if (index === -1 || target < 0 || target >= current.length) {
                return current;
            }

            const next = [...current];
            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });
    };

    const save = (next: string[]) =>
        router.post(
            rotation.url({ game: gameId }),
            {
                order: next.map((key) => {
                    const [type, id] = key.split(':');

                    return { type, id: Number(id) };
                }),
            },
            { preserveScroll: true },
        );

    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm font-medium">Chair rotation</p>

            {/* One row shape for every seat, so the columns line up down the
                list: the name takes the slack and everything after it starts at
                the same place on every row. Anything that varies per row - the
                In the Chair badge, a seat's Remove - sits inside a column
                rather than between two of them, which is what made the list
                ragged when it was one flex-wrap row of controls. */}
            <ol className="flex flex-col gap-1">
                {order.map((key, index) => {
                    const seat = seats.get(key);

                    if (!seat) {
                        return null;
                    }

                    return (
                        <li
                            key={key}
                            className={cn(
                                'flex items-center gap-2 rounded-md px-2 py-1 text-sm',
                                seat.is_chair &&
                                    'bg-primary/10 ring-1 ring-primary/30',
                            )}
                        >
                            <span className="w-6 shrink-0 font-mono text-muted-foreground tabular-nums">
                                {index + 1}.
                            </span>
                            <FactionBadge faction={seat} size="small" />
                            <span className="flex min-w-0 flex-1 items-center gap-2">
                                <span className="truncate">{seat.name}</span>
                                {seat.is_chair && (
                                    <Badge
                                        variant="outline"
                                        className="shrink-0"
                                    >
                                        In the Chair
                                    </Badge>
                                )}
                            </span>

                            <span className="flex shrink-0 items-center">
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label={`Move ${seat.name} earlier`}
                                    onClick={() => move(key, -1)}
                                >
                                    ↑
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label={`Move ${seat.name} later`}
                                    onClick={() => move(key, 1)}
                                >
                                    ↓
                                </Button>
                            </span>

                            <span className="flex shrink-0 items-center gap-1">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    disabled={seat.is_chair}
                                    aria-label={`Give ${seat.name} the Chair now`}
                                    onClick={() =>
                                        router.post(
                                            chair.url({ game: gameId }),
                                            {
                                                chair_type: seat.type,
                                                chair_id: seat.id,
                                            },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Chair now
                                </Button>
                                {/* A Corporation cannot leave the rotation; a
                                    seat Control put in it can. */}
                                {seat.type === 'character' && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={`Take ${seat.name} out of the rotation`}
                                        onClick={() =>
                                            save(
                                                order.filter(
                                                    (other) => other !== key,
                                                ),
                                            )
                                        }
                                    >
                                        Remove
                                    </Button>
                                )}
                            </span>
                        </li>
                    );
                })}
            </ol>

            <Button
                size="sm"
                variant="outline"
                className="self-start"
                onClick={() => save(order)}
            >
                Save the rotation
            </Button>

            {waiting.length > 0 && (
                <div className="mt-2 flex flex-col gap-1">
                    <p className="text-xs text-muted-foreground">
                        Seats that vote but never come round to chair. Adding
                        one puts it at the end of the rotation.
                    </p>
                    {/* Marked here too, because a seat can be in the Chair
                        without being in the rotation at all - which is exactly
                        how a game opens, with HM Government chairing. Left
                        unmarked, the one thing the panel most needs to say was
                        the one place it did not say it. */}
                    {waiting.map((seat) => (
                        <div
                            key={seat.id}
                            className={cn(
                                'flex items-center gap-2 rounded-md px-2 py-1 text-sm',
                                seat.is_chair &&
                                    'bg-primary/10 ring-1 ring-primary/30',
                            )}
                        >
                            <span className="w-6 shrink-0" />
                            <FactionBadge faction={seat} size="small" />
                            <span className="flex min-w-0 flex-1 items-center gap-2">
                                <span className="truncate">{seat.name}</span>
                                {seat.is_chair && (
                                    <Badge
                                        variant="outline"
                                        className="shrink-0"
                                    >
                                        In the Chair
                                    </Badge>
                                )}
                            </span>
                            <Button
                                size="sm"
                                variant="ghost"
                                className="shrink-0"
                                onClick={() =>
                                    save([...order, `character:${seat.id}`])
                                }
                            >
                                Add to the rotation
                            </Button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * The recess is a clock, so Control moves it the way it moves the phase clock:
 * by an amount, rather than by naming a moment.
 */
function Recess({ gameId }: { gameId: number }) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="text-sm font-medium">Recess</span>
            {[-60, 60, 300].map((seconds) => (
                <Button
                    key={seconds}
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.post(
                            recess.url({ game: gameId }),
                            { seconds },
                            { preserveScroll: true },
                        )
                    }
                >
                    {seconds > 0 ? `+${seconds / 60}` : seconds / 60} min
                </Button>
            ))}
        </div>
    );
}

function Annotate({ gameId, card }: { gameId: number; card: AgendaCardView }) {
    const [note, setNote] = useState(card.control_note ?? '');

    return (
        <div className="rounded-md border p-3">
            <AgendaCardPanel card={card}>
                <div className="flex flex-col gap-2">
                    <Label htmlFor={`note-${card.id}`}>Your remarks</Label>
                    <textarea
                        id={`note-${card.id}`}
                        className="min-h-16 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                    />
                    <Button
                        size="sm"
                        className="self-start"
                        onClick={() =>
                            router.post(
                                annotate.url({
                                    game: gameId,
                                    card: card.id,
                                }),
                                { control_note: note === '' ? null : note },
                                { preserveScroll: true },
                            )
                        }
                    >
                        Give it back to its author
                    </Button>
                </div>
            </AgendaCardPanel>
        </div>
    );
}

function Seat({
    gameId,
    seat,
    defaultPenalty,
}: {
    gameId: number;
    seat: CouncilSeatView;
    defaultPenalty: number;
}) {
    const [amount, setAmount] = useState(String(defaultPenalty));

    return (
        <div className="flex flex-col gap-2 rounded-md border p-3">
            <p className="flex flex-wrap items-center gap-2 text-sm font-medium">
                <FactionBadge faction={seat} size="small" />
                {seat.name}
                <span className="font-normal text-muted-foreground">
                    {seat.political_will} Political Will
                </span>
            </p>

            {(['setup', 'action'] as const).map((phase) => {
                const marked =
                    phase === 'setup'
                        ? seat.setup_attendance
                        : seat.action_attendance;
                const charged =
                    phase === 'setup'
                        ? seat.setup_penalty_applied
                        : seat.action_penalty_applied;

                return (
                    <div
                        key={phase}
                        className="flex flex-wrap items-center gap-2 text-sm"
                    >
                        <span className="w-16 text-muted-foreground capitalize">
                            {phase}
                        </span>

                        {(['present', 'absent'] as const).map((value) => (
                            <Button
                                key={value}
                                size="sm"
                                variant={
                                    marked === value ? 'default' : 'outline'
                                }
                                onClick={() =>
                                    router.post(
                                        attendance.url({ game: gameId }),
                                        {
                                            corporation_id: seat.corporation_id,
                                            phase,
                                            attendance: value,
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {value === 'present' ? 'Present' : 'Absent'}
                            </Button>
                        ))}

                        {marked === 'absent' && !charged && (
                            <>
                                <Input
                                    aria-label={`Political Will to take for the ${phase} phase`}
                                    type="number"
                                    min={1}
                                    className="w-20"
                                    value={amount}
                                    onChange={(event) =>
                                        setAmount(event.target.value)
                                    }
                                />
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            applyPenalty.url({ game: gameId }),
                                            {
                                                corporation_id:
                                                    seat.corporation_id,
                                                phase,
                                                amount: Number(amount),
                                            },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Take it
                                </Button>
                            </>
                        )}

                        {charged && <Badge variant="secondary">Charged</Badge>}
                    </div>
                );
            })}
        </div>
    );
}

/** Writing a card straight into the deck, which is Control's alone. */
function DeckComposer({ gameId }: { gameId: number }) {
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const [resolutions, setResolutions] = useState<string[]>(['', '']);

    const filled = resolutions.filter((text) => text.trim() !== '');
    const ready = title.trim() !== '' && filled.length >= 2;

    return (
        <form
            className="flex flex-col gap-3 rounded-md border border-dashed p-3"
            onSubmit={(event) => {
                event.preventDefault();

                router.post(
                    storeCard.url({ game: gameId }),
                    {
                        title,
                        body: body === '' ? null : body,
                        resolutions: filled,
                    },
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            setTitle('');
                            setBody('');
                            setResolutions(['', '']);
                        },
                    },
                );
            }}
        >
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="deck-title">A new agenda card</Label>
                <Input
                    id="deck-title"
                    value={title}
                    placeholder="What the Council is being asked"
                    onChange={(event) => setTitle(event.target.value)}
                />
            </div>

            <textarea
                aria-label="Background"
                className="min-h-16 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                value={body}
                placeholder="Background (optional)"
                onChange={(event) => setBody(event.target.value)}
            />

            {resolutions.map((text, index) => (
                <Input
                    key={index}
                    aria-label={`Resolution ${index + 1}`}
                    value={text}
                    placeholder={`Resolution ${index + 1}`}
                    onChange={(event) =>
                        setResolutions((current) =>
                            current.map((existing, position) =>
                                position === index
                                    ? event.target.value
                                    : existing,
                            ),
                        )
                    }
                />
            ))}

            <div className="flex gap-2">
                {resolutions.length < 5 && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            setResolutions((current) => [...current, ''])
                        }
                    >
                        Another resolution
                    </Button>
                )}
                <Button type="submit" size="sm" disabled={!ready}>
                    Into the deck
                </Button>
            </div>
        </form>
    );
}

ControlCouncil.layout = {
    breadcrumbs: [
        { title: 'Control', href: index() },
        { title: 'Council', href: index() },
    ],
};
