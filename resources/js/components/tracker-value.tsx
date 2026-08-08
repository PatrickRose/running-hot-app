import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { store } from '@/routes/control/trackers';

const QUICK_STEPS = [-5, -1, 1, 5];

/**
 * A single tracker value with a Control editor behind it.
 *
 * Every change captures an optional reason, because the point of the ledger is
 * that Control can explain a number three turns later.
 */
export function TrackerValue({
    gameId,
    subjectType,
    subjectId,
    subjectName,
    tracker,
    trackerLabel,
    value,
    tone = 'default',
}: {
    gameId: number;
    subjectType: string;
    subjectId: number;
    subjectName: string;
    tracker: string;
    trackerLabel: string;
    value: number;
    tone?: 'default' | 'warn';
}) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [setValue, setSetValue] = useState(String(value));

    const submit = (mode: 'adjust' | 'set', amount: number) => {
        router.post(
            store.url({ game: gameId }),
            {
                subject_type: subjectType,
                subject_id: subjectId,
                tracker,
                mode,
                value: amount,
                reason: reason || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setReason('');
                    setOpen(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'w-full rounded px-2 py-1 text-right font-mono tabular-nums hover:bg-accent',
                        tone === 'warn' &&
                            value > 0 &&
                            'text-amber-600 dark:text-amber-500',
                    )}
                    aria-label={`Adjust ${trackerLabel} for ${subjectName}`}
                >
                    {value}
                </button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {subjectName} &middot; {trackerLabel}
                    </DialogTitle>
                    <DialogDescription>
                        Currently {value}. Every change is written to the game
                        log.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`reason-${tracker}-${subjectId}`}>
                            Reason (optional)
                        </Label>
                        <Input
                            id={`reason-${tracker}-${subjectId}`}
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            placeholder="Expose in The Business Times"
                        />
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {QUICK_STEPS.map((step) => (
                            <Button
                                key={step}
                                type="button"
                                variant="outline"
                                onClick={() => submit('adjust', step)}
                            >
                                {step > 0 ? `+${step}` : step}
                            </Button>
                        ))}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={`set-${tracker}-${subjectId}`}>
                            Set to
                        </Label>
                        <div className="flex gap-2">
                            <Input
                                id={`set-${tracker}-${subjectId}`}
                                type="number"
                                value={setValue}
                                onChange={(event) =>
                                    setSetValue(event.target.value)
                                }
                            />
                            <Button
                                type="button"
                                onClick={() => submit('set', Number(setValue))}
                            >
                                Set
                            </Button>
                        </div>
                    </div>
                </div>

                <DialogFooter />
            </DialogContent>
        </Dialog>
    );
}
