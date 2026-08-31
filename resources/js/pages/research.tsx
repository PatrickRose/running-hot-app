import { Head } from '@inertiajs/react';
import { FactionBadge } from '@/components/faction-badge';
import Heading from '@/components/heading';
import { ResearchCardFace } from '@/components/research-card-face';
import { ResearchPoints } from '@/components/research-points';
import { ResearchScoring } from '@/components/research-scoring';
import { ResearchTable } from '@/components/research-table';
import { ResearchTree } from '@/components/research-tree';
import { TechnologyHoldings } from '@/components/technology-holdings';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { GameSummary, ResearchBoard } from '@/types/game';

type Props = {
    game: GameSummary | null;
    research: ResearchBoard | null;
};

/**
 * The Research player's page (rulebook 3.2).
 *
 * Both halves of the sub-game on one screen, because they are one job: points
 * are earned at the table during the Action phase and spent on the tree during
 * Setup, and what is worth spending depends on what was just earned. Putting
 * them on separate pages would mean flipping between two views every fifteen
 * minutes.
 *
 * The order down the page is the order of a turn. Your points, then the table
 * you earn them at, then the equations waiting to be scored, then the tree you
 * spend them on, then what you have already built, then the deck you play from.
 *
 * A player with no Corporate seat gets the table and nothing else — which is
 * correct rather than an error. The research table is a table in a room, and a
 * Runner may watch it.
 */
export default function Research({ game, research }: Props) {
    if (game === null || research === null) {
        return (
            <>
                <Head title="Research" />
                <div className="p-4">
                    <Heading
                        title="Research"
                        description="No game is running."
                    />
                </div>
            </>
        );
    }

    const own = research.own;

    return (
        <>
            <Head title="Research" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Research"
                    description={
                        research.turn === null
                            ? 'The game has not started.'
                            : `Turn ${research.turn} · ${
                                  game.phase?.type_label ?? 'no phase'
                              }`
                    }
                />

                {own && (
                    <ResearchPoints
                        points={own.points}
                        suits={research.suits}
                        corporations={research.corporations}
                        canTrade={own.can_play}
                    />
                )}

                <ResearchTable session={research.session} own={own} />

                {own && (
                    <ResearchScoring
                        equations={own.pending_equations}
                        suits={research.suits}
                        canScore={own.can_play}
                    />
                )}

                {own && (
                    <ResearchTree
                        tree={own.tree}
                        facilities={own.facilities}
                        suits={research.suits}
                        points={own.points}
                        canResearch={own.can_play}
                    />
                )}

                {own && (
                    <TechnologyHoldings
                        holdings={own.holdings}
                        facilities={own.facilities}
                    />
                )}

                {own && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FactionBadge faction={own} size="small" />
                                Your research deck
                            </CardTitle>
                            <CardDescription>
                                {own.deck.length} card
                                {own.deck.length === 1 ? '' : 's'} in all,{' '}
                                {own.deck_remaining} of them still face down.
                                Buying more is priced on your tech tree.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {own.deck.map((card) => (
                                <span
                                    key={card.id}
                                    className="flex flex-col items-center gap-1"
                                >
                                    <ResearchCardFace card={card} />
                                    <Badge
                                        variant={
                                            card.zone === 'spent'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                        className="text-[0.6rem]"
                                    >
                                        {card.zone_label}
                                    </Badge>
                                </span>
                            ))}
                            {own.deck.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    You have no research deck yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {own === null && (
                    <Card>
                        <CardHeader>
                            <CardTitle>You are not in a Corporation</CardTitle>
                            <CardDescription>
                                The research game belongs to the Corporations,
                                so there is nothing here for you to play. The
                                table above is public.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}
            </div>
        </>
    );
}

Research.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Research', href: dashboard() },
    ],
};
