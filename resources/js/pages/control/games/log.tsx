import { Head, Link, router, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/control/games';
import { index as logIndex } from '@/routes/control/log';
import type {
    ControlGameSummary,
    GameLogFilters,
    GameLogOption,
    GameLogOptions,
    TrackerAdjustmentPage,
} from '@/types/game';

type Props = {
    game: ControlGameSummary;
    adjustments: TrackerAdjustmentPage;
    filters: GameLogFilters;
    options: GameLogOptions;
};

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * Every tracker movement in the game, newest first, a page at a time.
 *
 * The Stats page keeps the last forty beside the numbers they moved; this is
 * the rest of the ledger, for "why did that number change?" asked about
 * something older. Filtering is done by the server, because the whole evening's
 * ledger is far more than a browser should be sent to search.
 *
 * Polls only on the first page. New rows arrive at the top, so refreshing a
 * later page would shuffle the rows Control is reading down underneath them.
 */
export default function ControlGameLog({
    game,
    adjustments,
    filters,
    options,
}: Props) {
    const onFirstPage = adjustments.current_page === 1;
    const { start, stop } = usePoll(
        5000,
        { only: ['adjustments', 'options'] },
        { autoStart: false },
    );

    // Followed rather than read once: paging keeps this component mounted, so
    // the poll has to stop when Control leaves the first page and resume when
    // they come back to it.
    useEffect(() => {
        if (onFirstPage) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [onFirstPage, start, stop]);

    const filtered =
        filters.tracker !== null ||
        filters.subject !== null ||
        filters.turn !== null;

    const apply = (changes: Partial<GameLogFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            logIndex.url(game.id, {
                query: {
                    tracker: next.tracker ?? undefined,
                    subject: next.subject ?? undefined,
                    turn: next.turn ?? undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Game log — ${game.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Game log"
                        description="Every tracker movement in the game, newest first. This is how Control answers “why did that number change?” three turns later."
                    />
                    <Button
                        variant="ghost"
                        onClick={() => router.get(show.url({ game: game.id }))}
                    >
                        Back to {game.name}
                    </Button>
                </div>

                <div className="grid max-w-3xl gap-4 sm:grid-cols-3">
                    <FilterSelect
                        id="log-subject"
                        label="Subject"
                        value={filters.subject}
                        options={options.subjects}
                        onChange={(subject) => apply({ subject })}
                    />
                    <FilterSelect
                        id="log-tracker"
                        label="Tracker"
                        value={filters.tracker}
                        options={options.trackers}
                        onChange={(tracker) => apply({ tracker })}
                    />
                    <FilterSelect
                        id="log-turn"
                        label="Turn"
                        value={
                            filters.turn === null ? null : String(filters.turn)
                        }
                        options={options.turns.map((turn) => ({
                            value: String(turn),
                            label: `Turn ${turn}`,
                        }))}
                        onChange={(turn) =>
                            apply({ turn: turn === null ? null : Number(turn) })
                        }
                    />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="flex flex-col gap-1.5">
                                <CardTitle>Tracker movements</CardTitle>
                                <CardDescription>
                                    {adjustments.total === 0
                                        ? filtered
                                            ? 'Nothing matches these filters.'
                                            : 'Nothing has moved yet.'
                                        : `${adjustments.from}–${adjustments.to} of ${adjustments.total}${filtered ? ' matching' : ''}`}
                                </CardDescription>
                            </div>
                            {filtered && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        apply({
                                            tracker: null,
                                            subject: null,
                                            turn: null,
                                        })
                                    }
                                >
                                    Clear filters
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">
                                        When
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Subject
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Tracker
                                    </th>
                                    <th className="py-2 pr-4 text-right font-medium">
                                        Change
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Reason
                                    </th>
                                    <th className="py-2 font-medium">By</th>
                                </tr>
                            </thead>
                            <tbody>
                                {adjustments.data.map((adjustment) => (
                                    <tr
                                        key={adjustment.id}
                                        className="border-b align-top last:border-0"
                                    >
                                        <td className="py-2 pr-4 whitespace-nowrap text-muted-foreground">
                                            <div>
                                                {adjustment.turn === null
                                                    ? 'No phase running'
                                                    : `Turn ${adjustment.turn}, ${adjustment.phase}`}
                                            </div>
                                            {adjustment.at !== null && (
                                                <time
                                                    dateTime={adjustment.at}
                                                    className="text-xs"
                                                >
                                                    {formatTime(adjustment.at)}
                                                </time>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {adjustment.subject}
                                        </td>
                                        <td className="py-2 pr-4 text-muted-foreground">
                                            {adjustment.tracker_label}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono whitespace-nowrap tabular-nums">
                                            {adjustment.value_before} →{' '}
                                            {adjustment.value_after}
                                        </td>
                                        <td className="py-2 pr-4 text-muted-foreground">
                                            {adjustment.reason ?? '—'}
                                        </td>
                                        <td className="py-2 text-muted-foreground">
                                            {adjustment.automated
                                                ? 'Upkeep'
                                                : (adjustment.actor ?? '—')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {adjustments.last_page > 1 && (
                            <nav
                                aria-label="Game log pages"
                                className="mt-4 flex items-center justify-between gap-3 text-sm"
                            >
                                <PageLink
                                    href={adjustments.prev_page_url}
                                    label="Newer"
                                />
                                <span className="text-muted-foreground">
                                    Page {adjustments.current_page} of{' '}
                                    {adjustments.last_page}
                                </span>
                                <PageLink
                                    href={adjustments.next_page_url}
                                    label="Older"
                                />
                            </nav>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function FilterSelect({
    id,
    label,
    value,
    options,
    onChange,
}: {
    id: string;
    label: string;
    value: string | null;
    options: GameLogOption[];
    onChange: (value: string | null) => void;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value ?? ''}
                onChange={(event) =>
                    onChange(
                        event.target.value === '' ? null : event.target.value,
                    )
                }
                className={SELECT_CLASS}
            >
                <option value="">Any</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

function PageLink({ href, label }: { href: string | null; label: string }) {
    if (href === null) {
        return (
            <Button variant="outline" size="sm" disabled>
                {label}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveScroll>
                {label}
            </Link>
        </Button>
    );
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

ControlGameLog.layout = {
    breadcrumbs: [
        { title: 'Control', href: index() },
        { title: 'Game log', href: index() },
    ],
};
