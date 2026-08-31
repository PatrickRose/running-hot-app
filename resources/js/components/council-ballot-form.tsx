import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/council/ballots';
import type { CouncilItem, CouncilViewer } from '@/types/game';

/**
 * A CEO's vote: how much Political Will, and behind which resolutions
 * (rulebook 3.1.2).
 *
 * The Political Will may be split across the resolutions on the card, so this
 * is a figure per resolution rather than a choice of one. What the Corporation
 * holds is the cap on the total, and it is shown as it is spent — the server
 * refuses a vote that spreads more than the Corporation has, and being told
 * that after submitting would be a poor way to find out.
 *
 * Nothing here is deducted. Political Will weights the vote; it is not spent on
 * it, so a Corporation votes with everything it has on every card.
 */
export function CouncilBallotForm({
    item,
    viewer,
}: {
    item: CouncilItem;
    viewer: CouncilViewer;
}) {
    const [allocations, setAllocations] = useState<Record<number, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const votable = item.card.resolutions.filter(
        (resolution) => resolution.votable,
    );

    const total = votable.reduce(
        (sum, resolution) => sum + (Number(allocations[resolution.id]) || 0),
        0,
    );

    const held = viewer.corporation?.political_will ?? 0;
    const overspent = total > held;

    return (
        <form
            className="flex flex-col gap-3 rounded-md border p-3"
            onSubmit={(event) => {
                event.preventDefault();
                setSubmitting(true);

                router.post(
                    store.url({ item: item.id }),
                    {
                        allocations: Object.fromEntries(
                            votable.map((resolution) => [
                                resolution.id,
                                Number(allocations[resolution.id]) || 0,
                            ]),
                        ),
                    },
                    {
                        preserveScroll: true,
                        onFinish: () => setSubmitting(false),
                    },
                );
            }}
        >
            <p className="text-sm font-medium">
                Your vote
                {item.secret && (
                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                        The Chair is withholding the breakdown of this one.
                    </span>
                )}
            </p>

            {votable.map((resolution) => (
                <label
                    key={resolution.id}
                    className="flex items-center justify-between gap-3 text-sm"
                >
                    <span>{resolution.text}</span>
                    <Input
                        type="number"
                        min={0}
                        max={held}
                        className="w-24"
                        value={allocations[resolution.id] ?? ''}
                        placeholder="0"
                        onChange={(event) =>
                            setAllocations((current) => ({
                                ...current,
                                [resolution.id]: event.target.value,
                            }))
                        }
                    />
                </label>
            ))}

            <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span
                    className={
                        overspent ? 'text-destructive' : 'text-muted-foreground'
                    }
                >
                    {total} of {held} Political Will
                    {overspent && ' — more than your Corporation holds'}
                </span>
                <Button
                    type="submit"
                    size="sm"
                    disabled={submitting || total === 0 || overspent}
                >
                    Hand it to the Chair
                </Button>
            </div>
        </form>
    );
}
