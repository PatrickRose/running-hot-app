import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FactionBadge } from '@/components/faction-badge';
import Heading from '@/components/heading';
import { RunLog } from '@/components/run-panel';
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
import { show } from '@/routes/control/games';
import type {
    ControlGameSummary,
    RunHistoryEntry,
    RunHistoryTurn,
} from '@/types/game';

type Props = {
    game: ControlGameSummary;
    turns: RunHistoryTurn[];
};

/**
 * Every run that has ended, turn by turn, newest first.
 *
 * The run screen only ever shows this turn, so this is where Control answers
 * who hit what, with whom, and whether they got in once the clock has moved on.
 * Read-only: a ruling made afterwards goes through the tracker controls.
 *
 * No poll. A run only arrives here once it is over, and nothing on it changes
 * after that — the live runs are on the run screen, which does poll.
 */
export default function ControlRuns({ game, turns }: Props) {
    const [search, setSearch] = useState('');
    const term = search.trim().toLowerCase();

    const shown = turns
        .map((turn) => ({
            ...turn,
            runs: turn.runs.filter((run) => matches(run, term)),
        }))
        .filter((turn) => turn.runs.length > 0);

    const total = turns.reduce((sum, turn) => sum + turn.runs.length, 0);
    const matching = shown.reduce((sum, turn) => sum + turn.runs.length, 0);

    return (
        <>
            <Head title={`Run history — ${game.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Run history"
                        description="Every run that has ended, from every turn. Live runs are on the run screen."
                    />
                    <Button
                        variant="ghost"
                        onClick={() => router.get(show.url({ game: game.id }))}
                    >
                        Back to {game.name}
                    </Button>
                </div>

                {total === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No run has ended yet.
                    </p>
                ) : (
                    <>
                        <div className="flex max-w-md flex-col gap-2">
                            <Label htmlFor="run-history-search">
                                Search by Facility, Corporation, Runner or gang
                            </Label>
                            <Input
                                id="run-history-search"
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {term === ''
                                    ? `${total} run${total === 1 ? '' : 's'}`
                                    : `${matching} of ${total} runs`}
                            </p>
                        </div>

                        {shown.map((turn) => (
                            <section
                                key={turn.number}
                                className="flex flex-col gap-4"
                            >
                                <h2 className="text-lg font-semibold">
                                    Turn {turn.number}
                                </h2>
                                {turn.runs.map((run) => (
                                    <HistoricRun key={run.id} run={run} />
                                ))}
                            </section>
                        ))}
                    </>
                )}
            </div>
        </>
    );
}

/**
 * Whether a run matches the search: its target, whose target it was, or
 * anybody who went in.
 */
function matches(run: RunHistoryEntry, term: string): boolean {
    if (term === '') {
        return true;
    }

    return [
        run.facility.name,
        run.facility.facility_type,
        run.facility.corporation.name,
        ...run.runners.flatMap((runner) => [
            runner.name,
            runner.gang?.name ?? '',
        ]),
    ].some((text) => text.toLowerCase().includes(term));
}

function HistoricRun({ run }: { run: RunHistoryEntry }) {
    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <FactionBadge faction={run.facility.corporation} />
                        <div>
                            <CardTitle>{run.facility.name}</CardTitle>
                            <CardDescription>
                                {run.facility.corporation.name}
                                {run.facility.is_plot &&
                                    ' (Plot Facility)'} ·{' '}
                                {run.facility.facility_type}
                            </CardDescription>
                        </div>
                    </div>
                    <Badge
                        className={
                            run.status === 'succeeded'
                                ? 'bg-emerald-600 hover:bg-emerald-600'
                                : 'bg-rose-600 hover:bg-rose-600'
                        }
                    >
                        {run.status_label}
                    </Badge>
                </div>
                <p className="flex flex-wrap gap-x-3 text-sm text-muted-foreground">
                    <span>
                        {run.cards_passed} card
                        {run.cards_passed === 1 ? '' : 's'} passed
                        {run.active_cards_passed !== run.cards_passed &&
                            ` (${run.active_cards_passed} active)`}
                    </span>
                    <span>
                        {run.alerts} Alert{run.alerts === 1 ? '' : 's'} raised
                    </span>
                    {run.ignored_end_the_run > 0 && (
                        <span>
                            {run.ignored_end_the_run} End the Run ignored
                        </span>
                    )}
                </p>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                <section className="flex flex-col gap-2">
                    <h3 className="font-medium">Runners</h3>
                    <ul className="flex flex-col gap-1 text-sm">
                        {run.runners.map((runner) => (
                            <li
                                key={runner.character_id}
                                className="flex flex-wrap items-center gap-2"
                            >
                                {runner.gang !== null && (
                                    <FactionBadge
                                        faction={runner.gang}
                                        size="small"
                                    />
                                )}
                                <span className="font-medium">
                                    {runner.name}
                                </span>
                                {runner.gang !== null && (
                                    <span className="text-muted-foreground">
                                        {runner.gang.name}
                                    </span>
                                )}
                                {runner.is_leader && (
                                    <Badge variant="outline">Run Leader</Badge>
                                )}
                                {runner.left && (
                                    <span className="text-muted-foreground">
                                        · {runner.left_reason ?? 'Left'}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>

                {run.accesses.length > 0 && (
                    <section className="flex flex-col gap-2">
                        <h3 className="font-medium">What they took</h3>
                        <ul className="flex flex-col gap-1 text-sm">
                            {run.accesses.map((taken) => (
                                <li
                                    key={taken.id}
                                    className="flex flex-wrap gap-x-2"
                                >
                                    <span className="font-medium">
                                        {taken.character}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {taken.kind_label}
                                        {taken.action_label !== null
                                            ? ` · ${taken.action_label}`
                                            : ''}
                                        {taken.technology !== null
                                            ? ` · ${taken.technology}`
                                            : ''}
                                        {taken.credits !== null
                                            ? ` · ${taken.credits} Credits`
                                            : ''}
                                        {taken.outcome !== null
                                            ? ` · ${taken.outcome.replace(/_/g, ' ')}`
                                            : ' · face up, undecided'}
                                        {taken.discount_percent !== null
                                            ? ` (${taken.discount_percent}% off)`
                                            : ''}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <RunLog log={run.log} />
            </CardContent>
        </Card>
    );
}
