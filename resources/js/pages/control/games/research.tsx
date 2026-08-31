import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FactionBadge } from '@/components/faction-badge';
import { GameIcon } from '@/components/game-icon';
import Heading from '@/components/heading';
import { ResearchCardFace } from '@/components/research-card-face';
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
import { index } from '@/routes/control/games';
import { show } from '@/routes/control/games';
import researchRoutes from '@/routes/control/research';
import { store as grantTechnology } from '@/routes/control/technology-holdings';
import { destroy as destroyHolding } from '@/routes/control/technology-holdings';
import type {
    GameSummary,
    ResearchControlState,
    ResearchEquationSummary,
} from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

type Props = {
    game: GameSummary;
    research: ResearchControlState;
};

/**
 * Research Control's panel (rulebook 3.2).
 *
 * Everything at once and nothing hidden, because Research Control is the person
 * holding the tokens: they deal, they draw the turn order, they price the custom
 * proposals, they make the copy when two Corporations agree to share one, and
 * they score for anybody who is away from a screen.
 *
 * The clock deals a fresh sitting as each Action phase opens, so on a normal
 * evening none of the table controls are touched. They exist for the evening
 * that is not normal — a misdeal, a turn order redrawn because somebody has an
 * effect that changes it, a player who has gone off to talk to a Runner.
 *
 * Research Points themselves are not edited here. They are Trackers, so they sit
 * on the game's main Control panel beside Credits and Political Will, and every
 * movement lands in the same ledger. Overriding a score is done by handing the
 * points back and scoring the equation again, which keeps both halves visible.
 */
export default function ControlResearch({ game, research }: Props) {
    const post = (
        url: string,
        data: Record<string, string | number | boolean> = {},
    ) => router.post(url, data, { preserveScroll: true });

    return (
        <>
            <Head title={`Research — ${game.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Research"
                        description={
                            research.turn === null
                                ? 'The game has not started.'
                                : `Turn ${research.turn} · ${research.public_deck_remaining} cards left in the public deck`
                        }
                    />
                    <div className="flex flex-wrap gap-2">
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
                        <CardTitle>The research table</CardTitle>
                        <CardDescription>
                            Dealt automatically as each Action phase opens.
                            Re-deal to gather every card back and shuffle.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-wrap gap-2">
                            <Button
                                onClick={() =>
                                    post(
                                        researchRoutes.session.store.url({
                                            game: game.id,
                                        }),
                                    )
                                }
                            >
                                {research.session === null ? 'Deal' : 'Re-deal'}
                            </Button>
                            <Button
                                variant="outline"
                                disabled={research.session === null}
                                onClick={() =>
                                    post(
                                        researchRoutes.session.order.url({
                                            game: game.id,
                                        }),
                                    )
                                }
                            >
                                Redraw turn order
                            </Button>
                            <Button
                                variant="outline"
                                disabled={research.session === null}
                                onClick={() =>
                                    post(
                                        researchRoutes.session.advance.url({
                                            game: game.id,
                                        }),
                                    )
                                }
                            >
                                Pass the turn on
                            </Button>
                            <Button
                                variant="outline"
                                disabled={research.session === null}
                                onClick={() =>
                                    post(
                                        researchRoutes.session.close.url({
                                            game: game.id,
                                        }),
                                    )
                                }
                            >
                                Close the table
                            </Button>
                        </div>

                        {research.session === null ? (
                            <p className="text-sm text-muted-foreground">
                                No sitting is open.
                            </p>
                        ) : (
                            <>
                                <div>
                                    <h3 className="text-sm font-medium">
                                        The public pool
                                    </h3>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {research.session.pool.map((card) => (
                                            <ResearchCardFace
                                                key={card.id}
                                                card={card}
                                            />
                                        ))}
                                    </div>
                                </div>

                                <ol className="flex flex-wrap gap-2">
                                    {research.session.seats.map((seat) => (
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
                                            <FactionBadge
                                                faction={seat}
                                                size="small"
                                            />
                                            {seat.name}
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    post(
                                                        researchRoutes.session.seat.url(
                                                            {
                                                                game: game.id,
                                                                corporation:
                                                                    seat.corporation_id,
                                                            },
                                                        ),
                                                        {
                                                            playing:
                                                                !seat.playing,
                                                        },
                                                    )
                                                }
                                            >
                                                {seat.playing
                                                    ? 'Take out'
                                                    : 'Sit down'}
                                            </Button>
                                        </li>
                                    ))}
                                </ol>
                            </>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Research Points and decks</CardTitle>
                        <CardDescription>
                            The four suits are Trackers, so they are edited on
                            the game panel beside Credits — every change lands
                            in the same ledger.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">
                                        Corporation
                                    </th>
                                    {research.suits.map((suit) => (
                                        <th
                                            key={suit.value}
                                            className="py-2 pr-4 text-right font-medium"
                                        >
                                            <span className="inline-flex items-center gap-1">
                                                <GameIcon
                                                    glyph={suit.glyph}
                                                    label={suit.label}
                                                />
                                                <span aria-hidden="true">
                                                    {suit.label}
                                                </span>
                                            </span>
                                        </th>
                                    ))}
                                    <th className="py-2 pr-4 text-right font-medium">
                                        Deck
                                    </th>
                                    <th className="py-2 pr-4 text-right font-medium">
                                        Hand
                                    </th>
                                    <th className="py-2 font-medium">
                                        Research player
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {research.corporations.map((corporation) => (
                                    <tr
                                        key={corporation.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-2 pr-4">
                                            <span className="flex items-center gap-2">
                                                <FactionBadge
                                                    faction={corporation}
                                                    size="small"
                                                />
                                                {corporation.name}
                                            </span>
                                        </td>
                                        {research.suits.map((suit) => (
                                            <td
                                                key={suit.value}
                                                className="py-2 pr-4 text-right font-mono tabular-nums"
                                            >
                                                {corporation.points[
                                                    suit.value
                                                ] ?? 0}
                                            </td>
                                        ))}
                                        <td className="py-2 pr-4 text-right font-mono tabular-nums">
                                            {corporation.deck_remaining}
                                        </td>
                                        <td className="py-2 pr-4 text-right">
                                            <span className="inline-flex flex-wrap justify-end gap-1">
                                                {corporation.hand.map(
                                                    (card) => (
                                                        <span
                                                            key={card.id}
                                                            className="rounded border px-1 font-mono text-xs"
                                                        >
                                                            {card.label}
                                                        </span>
                                                    ),
                                                )}
                                            </span>
                                        </td>
                                        <td className="py-2 text-muted-foreground">
                                            {corporation.researchers.join(
                                                ', ',
                                            ) || '—'}
                                        </td>
                                    </tr>
                                ))}
                                {research.corporations.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={research.suits.length + 4}
                                            className="py-4 text-muted-foreground"
                                        >
                                            No corporations yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Equations</CardTitle>
                        <CardDescription>
                            Scoring is the player's, in their own time. Hand the
                            points back to let an equation be scored again —
                            that is how a score is overridden, and both
                            movements stay in the ledger.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {research.equations.map((equation) => (
                            <EquationRow
                                key={equation.id}
                                gameId={game.id}
                                equation={equation}
                            />
                        ))}
                        {research.equations.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Nothing played yet.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Technology cards</CardTitle>
                        <CardDescription>
                            A copy made for a partner (3.2.5), or whatever a Run
                            came back with (3.2.6). What a card is worth off the
                            research cost defaults to its kind and can be named
                            outright.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <GrantForm gameId={game.id} research={research} />

                        {research.corporations.map((corporation) => (
                            <div
                                key={corporation.id}
                                className="rounded-md border p-3"
                            >
                                <p className="flex items-center gap-2 font-medium">
                                    <FactionBadge
                                        faction={corporation}
                                        size="small"
                                    />
                                    {corporation.name}
                                </p>
                                <ul className="mt-2 flex flex-col gap-1 text-sm">
                                    {corporation.holdings.map((holding) => (
                                        <li
                                            key={holding.id}
                                            className="flex flex-wrap items-center gap-2"
                                        >
                                            <span>{holding.name}</span>
                                            <Badge variant="outline">
                                                {holding.status_label}
                                            </Badge>
                                            <Badge variant="secondary">
                                                {holding.origin_label}
                                                {holding.discount_percent > 0 &&
                                                    ` · ${holding.discount_percent}% off`}
                                            </Badge>
                                            <span className="text-muted-foreground">
                                                {holding.facility ??
                                                    'not housed'}
                                            </span>
                                            {!holding.usable && (
                                                <Badge variant="secondary">
                                                    Not working
                                                </Badge>
                                            )}
                                            {holding.status !== 'destroyed' && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        router.delete(
                                                            destroyHolding.url({
                                                                game: game.id,
                                                                holding:
                                                                    holding.id,
                                                            }),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Destroy
                                                </Button>
                                            )}
                                        </li>
                                    ))}
                                    {corporation.holdings.length === 0 && (
                                        <li className="text-muted-foreground">
                                            Nothing researched.
                                        </li>
                                    )}
                                </ul>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function EquationRow({
    gameId,
    equation,
}: {
    gameId: number;
    equation: ResearchEquationSummary;
}) {
    return (
        <div className="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-sm">
            <span className="font-medium">{equation.corporation}</span>
            <span className="font-mono">
                {equation.left_label} / {equation.right_label}
            </span>
            <Badge variant={equation.balanced ? 'default' : 'secondary'}>
                {equation.left_sum} against {equation.right_sum}
                {equation.balanced && ` · ${equation.bonus} bonus`}
            </Badge>
            <Badge variant="outline">{equation.status_label}</Badge>
            {equation.awards && (
                <span className="text-muted-foreground">
                    {Object.entries(equation.awards)
                        .filter(([, points]) => points > 0)
                        .map(([suit, points]) => `${points} ${suit}`)
                        .join(', ')}
                </span>
            )}
            <span className="ml-auto flex gap-1">
                {equation.status === 'scored' && (
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            router.post(
                                researchRoutes.equations.unscore.url({
                                    game: gameId,
                                    equation: equation.id,
                                }),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        Hand the points back
                    </Button>
                )}
                {equation.status !== 'voided' && (
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            router.post(
                                researchRoutes.equations.void.url({
                                    game: gameId,
                                    equation: equation.id,
                                }),
                                { reason: 'Voided by Control' },
                                { preserveScroll: true },
                            )
                        }
                    >
                        Void
                    </Button>
                )}
            </span>
        </div>
    );
}

/**
 * Putting a technology card into a Corporation's hands.
 *
 * The Facility is optional here, unlike researching: a card a Run has just come
 * back with may be handed over before anybody has decided where it goes, and
 * Control moves it afterwards.
 */
function GrantForm({
    gameId,
    research,
}: {
    gameId: number;
    research: ResearchControlState;
}) {
    const [corporationId, setCorporationId] = useState<number | null>(
        research.corporations[0]?.id ?? null,
    );

    const corporation = research.corporations.find(
        (entry) => entry.id === corporationId,
    );

    if (corporation === undefined) {
        return null;
    }

    return (
        <form
            className="grid gap-3 border-b pb-4 lg:grid-cols-5"
            onSubmit={(event) => {
                event.preventDefault();

                const form = new FormData(event.currentTarget);

                router.post(grantTechnology.url({ game: gameId }), form, {
                    preserveScroll: true,
                });
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor="grant-corporation">Corporation</Label>
                <select
                    id="grant-corporation"
                    name="corporation_id"
                    className={SELECT_CLASS}
                    value={corporation.id}
                    onChange={(event) =>
                        setCorporationId(Number(event.target.value))
                    }
                >
                    {research.corporations.map((entry) => (
                        <option key={entry.id} value={entry.id}>
                            {entry.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="grid gap-2 lg:col-span-2">
                <Label htmlFor="grant-technology">Technology</Label>
                <select
                    id="grant-technology"
                    name="technology_type_id"
                    className={SELECT_CLASS}
                >
                    {research.technologies.map((technology) => (
                        <option key={technology.id} value={technology.id}>
                            {technology.name}
                            {technology.code ? ` (${technology.code})` : ''}
                        </option>
                    ))}
                </select>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="grant-origin">How they got it</Label>
                <select
                    id="grant-origin"
                    name="origin"
                    className={SELECT_CLASS}
                    defaultValue="good_copy"
                >
                    {research.origins.map((origin) => (
                        <option key={origin.value} value={origin.value}>
                            {origin.label} · {origin.default_discount_percent}%
                            off
                        </option>
                    ))}
                </select>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="grant-facility">Stored in</Label>
                <select
                    id="grant-facility"
                    name="facility_id"
                    className={SELECT_CLASS}
                >
                    <option value="">Not yet placed</option>
                    {corporation.facilities.map((facility) => (
                        <option key={facility.id} value={facility.id}>
                            {facility.name} ({facility.stored}/
                            {facility.capacity})
                        </option>
                    ))}
                </select>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="grant-discount">Discount %</Label>
                <Input
                    id="grant-discount"
                    name="discount_percent"
                    type="number"
                    min={0}
                    max={100}
                    placeholder="from the origin"
                />
            </div>

            <div className="flex items-end lg:col-span-5">
                <Button type="submit">Hand the card over</Button>
            </div>
        </form>
    );
}

ControlResearch.layout = {
    breadcrumbs: [
        { title: 'Control', href: index() },
        { title: 'Research', href: index() },
    ],
};
