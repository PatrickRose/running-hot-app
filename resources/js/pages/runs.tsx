import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { RunPanel } from '@/components/run-panel';
import { RunSubmitForm } from '@/components/run-submit-form';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { GameSummary, RunBoard } from '@/types/game';

type Props = {
    game: GameSummary | null;
    board: RunBoard | null;
};

/**
 * The Facility game, from whichever side of it you are on (rulebook 3.4).
 *
 * One page for both sides, because plenty of people are on both at once — a
 * Security player whose Corporation is being hit is also watching their own
 * queue, and Control is watching everything. What differs is the payload rather
 * than the layout: a Runner is handed no stack depth, no budget and a nameless
 * card until Security flips it, so the same panel simply has nothing to draw in
 * those places.
 */
export default function Runs({ game, board }: Props) {
    if (game === null || board === null) {
        return (
            <>
                <Head title="Runs" />
                <div className="p-4">
                    <Heading title="Runs" description="No game is running." />
                </div>
            </>
        );
    }

    const nothingOn = board.yours.length === 0 && board.defending.length === 0;

    return (
        <>
            <Head title="Runs" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Runs"
                    description={
                        board.turn === null
                            ? 'The game has not started.'
                            : `Turn ${board.turn}${
                                  board.is_action_phase
                                      ? ' · Action phase'
                                      : ' · runs are resolved during the Action phase'
                              }`
                    }
                />

                {board.can_submit && (
                    <RunSubmitForm
                        targets={board.targets}
                        party={board.party}
                    />
                )}

                {board.yours.length > 0 && (
                    <section className="flex flex-col gap-4">
                        <h2 className="text-lg font-semibold">Your runs</h2>
                        {board.yours.map((run) => (
                            <RunPanel key={run.id} run={run} />
                        ))}
                    </section>
                )}

                {board.defending.length > 0 && (
                    <section className="flex flex-col gap-4">
                        <h2 className="text-lg font-semibold">
                            {board.is_control
                                ? 'Every run in the game'
                                : 'Coming at your Facilities'}
                        </h2>
                        {!board.is_control && (
                            <p className="text-sm text-muted-foreground">
                                A run appears here once the Runners are actually
                                in it. Targets and budgets are both chosen in
                                Secret, at the same time, so there is nothing to
                                see until then.
                            </p>
                        )}
                        {board.defending.map((run) => (
                            <RunPanel key={run.id} run={run} />
                        ))}
                    </section>
                )}

                {nothingOn && !board.can_submit && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Nothing on</CardTitle>
                            <CardDescription>
                                No run is coming at your Facilities, and you are
                                not on one. A run appears here the moment it
                                goes in.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}
            </div>
        </>
    );
}
