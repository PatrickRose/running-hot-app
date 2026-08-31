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
import {
    attendance,
    chair,
    draw,
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
import { index, show } from '@/routes/control/games';
import type {
    AgendaCardView,
    CouncilBoard,
    CouncilControlBoard,
    CouncilSeatView,
    GameSummary,
} from '@/types/game';

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
                            Control draws three cards and hands them to the
                            Chair, who keeps two. The Chair rotates in the order
                            below, which Council Control announces on the day.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                size="sm"
                                disabled={session?.has_drawn}
                                onClick={() =>
                                    router.post(
                                        draw.url({ game: game.id }),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {session?.has_drawn
                                    ? 'Drawn for this turn'
                                    : `Draw ${session?.cards_drawn ?? 3} for the Chair`}
                            </Button>

                            {session && (
                                <span className="text-sm text-muted-foreground">
                                    {session.tabled_count} of{' '}
                                    {session.maximum_items} agenda items this
                                    turn
                                </span>
                            )}
                        </div>

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
                        <CardTitle>The agenda deck</CardTitle>
                        <CardDescription>
                            Nothing is seeded here. The rulebook prints no
                            agenda cards, so the deck is whatever you write for
                            the game you are running.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <DeckComposer gameId={game.id} />

                        {control.deck.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                The deck is empty.
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

function Rotation({
    gameId,
    control,
}: {
    gameId: number;
    control: CouncilControlBoard;
}) {
    const [order, setOrder] = useState(control.rotation.map((row) => row.id));

    const move = (id: number, direction: -1 | 1) => {
        setOrder((current) => {
            const index = current.indexOf(id);
            const target = index + direction;

            if (index === -1 || target < 0 || target >= current.length) {
                return current;
            }

            const next = [...current];
            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });
    };

    const byId = new Map(control.rotation.map((row) => [row.id, row]));

    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm font-medium">Chair rotation</p>
            <ol className="flex flex-col gap-1">
                {order.map((id, index) => {
                    const corporation = byId.get(id);

                    if (!corporation) {
                        return null;
                    }

                    return (
                        <li
                            key={id}
                            className="flex flex-wrap items-center gap-2 text-sm"
                        >
                            <span className="w-6 font-mono text-muted-foreground tabular-nums">
                                {index + 1}.
                            </span>
                            <FactionBadge faction={corporation} size="small" />
                            {corporation.name}
                            {corporation.is_chair && (
                                <Badge variant="outline">
                                    In the Chair this turn
                                </Badge>
                            )}
                            <Button
                                size="sm"
                                variant="ghost"
                                aria-label={`Move ${corporation.name} earlier`}
                                onClick={() => move(id, -1)}
                            >
                                ↑
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                aria-label={`Move ${corporation.name} later`}
                                onClick={() => move(id, 1)}
                            >
                                ↓
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    router.post(
                                        chair.url({ game: gameId }),
                                        { corporation_id: id },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Give them the Chair now
                            </Button>
                        </li>
                    );
                })}
            </ol>

            <Button
                size="sm"
                variant="outline"
                className="self-start"
                onClick={() =>
                    router.post(
                        rotation.url({ game: gameId }),
                        { order },
                        { preserveScroll: true },
                    )
                }
            >
                Save the rotation
            </Button>
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
