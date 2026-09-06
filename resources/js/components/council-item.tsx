import { router } from '@inertiajs/react';
import { useState } from 'react';
import { AgendaAmendmentForm } from '@/components/agenda-amendment-form';
import { AgendaCardPanel } from '@/components/agenda-card-panel';
import { CouncilBallotForm } from '@/components/council-ballot-form';
import { FactionBadge } from '@/components/faction-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { returnMethod } from '@/routes/council/ballots';
import { resolve, secret } from '@/routes/council/items';
import type {
    CouncilItem as CouncilItemView,
    CouncilViewer,
} from '@/types/game';

/**
 * One card in front of the Council, and everything that may be seen of the vote
 * on it (rulebook 3.1.2).
 *
 * The three tiers of visibility are the whole shape of this component, and they
 * are decided on the server rather than here — a field arrives null when the
 * viewer is not entitled to it, so there is nothing to hide in the browser.
 * What this does is say which tier you are in, because a player looking at a
 * secret vote should know that a breakdown exists and is being withheld rather
 * than think nobody has voted.
 */
export function CouncilItem({
    item,
    viewer,
    sessionId,
}: {
    item: CouncilItemView;
    viewer: CouncilViewer;
    sessionId: number;
}) {
    // May act as the Chair on this vote, which Control may too. Being the
    // Chair is viewer.is_chair, and nothing here claims it.
    const chairing = viewer.can_chair;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    {item.card.title}
                    {item.secret && (
                        <Badge variant="outline">Secret ballot</Badge>
                    )}
                    {item.resolved && <Badge>Resolved</Badge>}
                </CardTitle>
                <CardDescription>{item.source_label}</CardDescription>
            </CardHeader>

            <CardContent className="flex flex-col gap-4">
                <AgendaCardPanel card={item.card} />

                {item.outcome && (
                    <p className="rounded-md bg-muted p-3 text-sm">
                        <span className="font-medium">Carried: </span>
                        {item.outcome.text}
                        {item.tie_broken &&
                            ' — on the Chair’s decision, the vote being tied.'}
                    </p>
                )}

                <Submitted item={item} />

                {item.totals && <Totals item={item} />}

                {item.totals === null && (
                    <p className="text-sm text-muted-foreground">
                        {item.secret
                            ? 'The Chair is withholding the breakdown of this vote.'
                            : 'The breakdown is read out once the Chair resolves the vote.'}
                    </p>
                )}

                {item.your_ballot && (
                    <p className="text-sm">
                        <span className="font-medium">Your vote: </span>
                        {item.card.resolutions
                            .filter(
                                (resolution) =>
                                    (item.your_ballot?.allocations[
                                        resolution.id
                                    ] ?? 0) > 0,
                            )
                            .map(
                                (resolution) =>
                                    `${resolution.text} (${item.your_ballot?.allocations[resolution.id]})`,
                            )
                            .join(', ')}
                    </p>
                )}

                {item.can_vote && viewer.corporation && (
                    <CouncilBallotForm item={item} viewer={viewer} />
                )}

                {chairing && !item.resolved && (
                    <ChairControls
                        item={item}
                        sessionId={sessionId}
                        asControl={!viewer.is_chair}
                    />
                )}
            </CardContent>
        </Card>
    );
}

/**
 * Who has handed a slip to the Chair.
 *
 * Public even in a secret vote, because at the table you can see somebody vote
 * without seeing what they wrote. It is also what tells the Chair whether the
 * Council has finished voting.
 */
function Submitted({ item }: { item: CouncilItemView }) {
    if (item.submitted.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nobody has voted yet.
            </p>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-3 text-sm">
            <span className="text-muted-foreground">Votes in from</span>
            {item.submitted.map((corporation) => (
                <span
                    key={corporation.corporation_id}
                    className="flex items-center gap-1.5"
                >
                    <FactionBadge faction={corporation} size="small" />
                    {corporation.name}
                </span>
            ))}
        </div>
    );
}

function Totals({ item }: { item: CouncilItemView }) {
    const totals = item.totals ?? {};

    return (
        <div className="flex flex-col gap-2">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-2 pr-4 font-medium">Resolution</th>
                        <th className="py-2 text-right font-medium">
                            Political Will
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {item.card.resolutions
                        .filter((resolution) => resolution.votable)
                        .map((resolution) => (
                            <tr
                                key={resolution.id}
                                className="border-b last:border-0"
                            >
                                <td className="py-2 pr-4">{resolution.text}</td>
                                <td className="py-2 text-right font-mono tabular-nums">
                                    {totals[resolution.id] ?? 0}
                                </td>
                            </tr>
                        ))}
                </tbody>
            </table>

            {item.breakdown && item.breakdown.length > 0 && (
                <ul className="flex flex-col gap-1 text-sm text-muted-foreground">
                    {item.breakdown.map((record) => (
                        <li
                            key={record.corporation_id}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <FactionBadge faction={record} size="small" />
                            {record.name}:{' '}
                            {item.card.resolutions
                                .filter(
                                    (resolution) =>
                                        (record.allocations[resolution.id] ??
                                            0) > 0,
                                )
                                .map(
                                    (resolution) =>
                                        `${resolution.text} (${record.allocations[resolution.id]})`,
                                )
                                .join(', ')}
                        </li>
                    ))}
                </ul>
            )}

            {item.tied && !item.resolved && (
                <p className="text-sm text-amber-600 dark:text-amber-500">
                    Tied. The Chair decides which resolution carries.
                </p>
            )}
        </div>
    );
}

/**
 * The Chair's powers over a vote in progress: declaring it secret before
 * anybody has voted, handing a slip back, and resolving it.
 */
function ChairControls({
    item,
    sessionId,
    asControl,
}: {
    item: CouncilItemView;
    sessionId: number;
    /** Control using the Chair's controls rather than the Chair itself. */
    asControl: boolean;
}) {
    const [choice, setChoice] = useState<string>('');

    const votable = item.card.resolutions.filter(
        (resolution) => resolution.votable,
    );

    return (
        <div className="flex flex-col gap-3 rounded-md border border-dashed p-3">
            <p className="text-sm font-medium">
                The Chair
                {asControl && (
                    <span className="ml-2 font-normal text-muted-foreground">
                        &mdash; you are acting as Control
                    </span>
                )}
            </p>

            <div className="flex flex-wrap items-center gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.post(
                            secret.url({ item: item.id }),
                            { secret: !item.secret },
                            { preserveScroll: true },
                        )
                    }
                >
                    {item.secret
                        ? 'Read the breakdown out after all'
                        : 'Declare this vote secret'}
                </Button>

                {!item.secret && (
                    <span className="text-xs text-muted-foreground">
                        Any votes already in are handed back — 3.1.2.
                    </span>
                )}
            </div>

            {item.submitted.length > 0 && (
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-muted-foreground">
                        Hand a vote back
                    </span>
                    {item.submitted.map((corporation) => (
                        <Button
                            key={corporation.ballot_id}
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                router.delete(
                                    returnMethod.url({
                                        ballot: corporation.ballot_id,
                                    }),
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {corporation.name}
                        </Button>
                    ))}
                </div>
            )}

            <AgendaAmendmentForm sessionId={sessionId} card={item.card} />

            <div className="flex flex-wrap items-center gap-2">
                {item.tied && (
                    <select
                        aria-label="Resolution the Chair chooses"
                        className="h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none"
                        value={choice}
                        onChange={(event) => setChoice(event.target.value)}
                    >
                        <option value="">Choose a resolution…</option>
                        {votable.map((resolution) => (
                            <option key={resolution.id} value={resolution.id}>
                                {resolution.text}
                            </option>
                        ))}
                    </select>
                )}

                <Button
                    size="sm"
                    onClick={() =>
                        router.post(
                            resolve.url({ item: item.id }),
                            choice === ''
                                ? {}
                                : { resolution_id: Number(choice) },
                            { preserveScroll: true },
                        )
                    }
                >
                    Resolve the vote
                </Button>
            </div>
        </div>
    );
}
