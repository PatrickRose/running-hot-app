import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { AgendaCardPanel } from '@/components/agenda-card-panel';
import { AgendaComposer } from '@/components/agenda-composer';
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
import { index as councilControl } from '@/routes/control/council';
import { promote } from '@/routes/council';
import { toChair, toControl } from '@/routes/council/agenda-cards';
import { keep } from '@/routes/council/hand';
import { store as ruleOn } from '@/routes/council/rulings';
import type { AgendaCardView, CouncilBoard, GameSummary } from '@/types/game';

type Props = {
    game: GameSummary | null;
    council: CouncilBoard | null;
};

/**
 * The Council Chamber (rulebook 3.1).
 *
 * Laid out as the sitting runs rather than by who may do what: the agenda at
 * the top, because it is what the Council is there for, then the votes, then
 * the Chair's own piles, then the blank cards anybody may fill out. A player
 * with no part in a section simply does not see it.
 */
export default function Council({ game, council }: Props) {
    if (game === null || council === null) {
        return (
            <>
                <Head title="Council" />
                <div className="p-4">
                    <Heading
                        title="Council"
                        description="No game is running."
                    />
                </div>
            </>
        );
    }

    const { session, viewer } = council;
    const chairing = viewer.is_chair || viewer.is_control;

    return (
        <>
            <Head title="Council" />

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
                    {session && <RecessClock session={session} />}
                </div>

                {session === null ? (
                    <p className="text-sm text-muted-foreground">
                        The Council has not sat this turn.
                    </p>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex flex-wrap items-center gap-2">
                                    {session.chair ? (
                                        <>
                                            <FactionBadge
                                                faction={session.chair}
                                                size="small"
                                            />
                                            {session.chair.name} is in the Chair
                                        </>
                                    ) : (
                                        'The Chair is vacant'
                                    )}
                                    {viewer.is_chair && (
                                        <Badge variant="outline">
                                            That is you
                                        </Badge>
                                    )}
                                </CardTitle>
                                <CardDescription>
                                    {session.tabled_count} of{' '}
                                    {session.maximum_items} agenda items this
                                    turn.
                                    {viewer.corporation &&
                                        ` ${viewer.corporation.name} holds ${viewer.corporation.political_will} Political Will, which is the weight of its vote and is not spent on it.`}
                                </CardDescription>
                            </CardHeader>
                        </Card>

                        {chairing && council.hand.length > 0 && (
                            <ChairsHand
                                sessionId={session.id}
                                hand={council.hand}
                                keeps={session.cards_kept}
                            />
                        )}

                        <section className="flex flex-col gap-4">
                            <h2 className="text-lg font-medium">Up for vote</h2>
                            {council.items.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nothing is on the agenda yet. Control picks
                                    the cards, and the Chair keeps two.
                                </p>
                            ) : (
                                council.items.map((item) => (
                                    <CouncilItem
                                        key={item.id}
                                        item={item}
                                        viewer={viewer}
                                        sessionId={session.id}
                                    />
                                ))
                            )}
                        </section>

                        {chairing && (
                            <ChairsPiles
                                sessionId={session.id}
                                withChair={council.with_chair}
                                important={council.important}
                                canPromote={session.can_promote}
                            />
                        )}

                        {viewer.is_control &&
                            council.with_control.length > 0 && (
                                <ControlsPile
                                    gameId={game.id}
                                    cards={council.with_control}
                                />
                            )}
                    </>
                )}

                <MyCards cards={council.my_cards} />

                {viewer.can_submit_agenda && viewer.character_id !== null && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Write an agenda card</CardTitle>
                            <CardDescription>
                                Take a blank card, fill it out, and give it to
                                Control. Control adds any remarks and gives it
                                back; if you still want to, you then submit it
                                to the Chair.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <AgendaComposer characterId={viewer.character_id} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

/**
 * What Control has handed over, before two of them are read out (3.1.1).
 *
 * Nobody but the Chair and Control has seen these, so nobody else is shown
 * them: the discard is meant to be the Chair's own decision, made privately.
 */
function ChairsHand({
    sessionId,
    hand,
    keeps,
}: {
    sessionId: number;
    hand: AgendaCardView[];
    keeps: number;
}) {
    const [kept, setKept] = useState<number[]>([]);
    const allowed = Math.min(keeps, hand.length);

    return (
        <Card>
            <CardHeader>
                <CardTitle>In the Chair&rsquo;s hand</CardTitle>
                <CardDescription>
                    Keep {allowed} to be voted on this turn. The rest are
                    discarded.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {hand.map((card) => (
                    <label
                        key={card.id}
                        className="flex items-start gap-3 rounded-md border p-3"
                    >
                        <input
                            type="checkbox"
                            className="mt-1"
                            checked={kept.includes(card.id)}
                            onChange={(event) =>
                                setKept((current) =>
                                    event.target.checked
                                        ? [...current, card.id]
                                        : current.filter(
                                              (id) => id !== card.id,
                                          ),
                                )
                            }
                        />
                        <div className="flex-1">
                            <AgendaCardPanel card={card} />
                        </div>
                    </label>
                ))}

                <Button
                    size="sm"
                    className="self-start"
                    disabled={kept.length !== allowed}
                    onClick={() =>
                        router.post(
                            keep.url({ session: sessionId }),
                            { kept },
                            { preserveScroll: true },
                        )
                    }
                >
                    Keep {allowed}, discard the rest
                </Button>
            </CardContent>
        </Card>
    );
}

/**
 * The two piles the Chair rules on: cards submitted to them this turn, and the
 * ones they have held as important for a future one (rulebook 3.1.3).
 */
function ChairsPiles({
    sessionId,
    withChair,
    important,
    canPromote,
}: {
    sessionId: number;
    withChair: AgendaCardView[];
    important: AgendaCardView[];
    canPromote: boolean;
}) {
    if (withChair.length === 0 && important.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Submitted to the Chair</CardTitle>
                <CardDescription>
                    Urgent means voted on this turn, if there is room for it.
                    Important means eligible for a future turn, one of which the
                    Chair may promote as Setup opens.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {withChair.map((card) => (
                    <div key={card.id} className="rounded-md border p-3">
                        <AgendaCardPanel card={card}>
                            <div className="flex flex-wrap gap-2">
                                {(
                                    ['urgent', 'important', 'reject'] as const
                                ).map((ruling) => (
                                    <Button
                                        key={ruling}
                                        size="sm"
                                        variant={
                                            ruling === 'reject'
                                                ? 'outline'
                                                : 'default'
                                        }
                                        onClick={() =>
                                            router.post(
                                                ruleOn.url({
                                                    session: sessionId,
                                                }),
                                                {
                                                    agenda_card_id: card.id,
                                                    ruling,
                                                },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {ruling === 'urgent' && 'Urgent'}
                                        {ruling === 'important' && 'Important'}
                                        {ruling === 'reject' && 'Reject'}
                                    </Button>
                                ))}
                            </div>
                        </AgendaCardPanel>
                    </div>
                ))}

                {important.map((card) => (
                    <div key={card.id} className="rounded-md border p-3">
                        <AgendaCardPanel card={card}>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={!canPromote}
                                onClick={() =>
                                    router.post(
                                        promote.url({ session: sessionId }),
                                        { agenda_card_id: card.id },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {canPromote
                                    ? 'Promote onto this turn’s agenda'
                                    : 'One item promoted already this turn'}
                            </Button>
                        </AgendaCardPanel>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

/**
 * Cards a player has handed to Control and Control has not yet remarked on
 * (3.1.3).
 *
 * Control's alone. The Chair does not see these, because a card with Control
 * has not been handed to the Chair yet and might never be — the author reads
 * the remarks first and may keep it back.
 *
 * It is shown here as well as on the Control panel because the player is told
 * their card is with Control, and this is where both of them come looking. The
 * remarks themselves are written on the panel, so this points at it rather
 * than growing a second copy of that form.
 */
function ControlsPile({
    gameId,
    cards,
}: {
    gameId: number;
    cards: AgendaCardView[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Waiting on Control</CardTitle>
                <CardDescription>
                    {cards.length === 1 ? 'A card' : `${cards.length} cards`}{' '}
                    handed to you, and not yet given back to their authors. The
                    Chair cannot see these.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {cards.map((card) => (
                    <div key={card.id} className="rounded-md border p-3">
                        <AgendaCardPanel card={card} />
                    </div>
                ))}

                <Button
                    size="sm"
                    variant="outline"
                    className="self-start"
                    onClick={() =>
                        router.get(councilControl.url({ game: gameId }))
                    }
                >
                    Add your remarks on the Control panel
                </Button>
            </CardContent>
        </Card>
    );
}

/**
 * The viewer's own custom agendas, wherever they have got to in the handshake
 * of 3.1.3 — with them, with Control, with the Chair, or back again.
 */
function MyCards({ cards }: { cards: AgendaCardView[] }) {
    if (cards.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Your agenda cards</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {cards.map((card) => (
                    <div key={card.id} className="rounded-md border p-3">
                        <AgendaCardPanel card={card}>
                            <div className="flex flex-wrap gap-2">
                                {(card.status === 'draft' ||
                                    card.status === 'rejected') && (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                toControl.url({
                                                    card: card.id,
                                                }),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Give it to Control
                                    </Button>
                                )}

                                {card.status === 'annotated' && (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                toChair.url({ card: card.id }),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Submit it to the Chair
                                    </Button>
                                )}
                            </div>
                        </AgendaCardPanel>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
