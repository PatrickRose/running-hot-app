import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { CreditRecipient } from '@/components/give-credits-dialog';
import { SearchPicker } from '@/components/search-picker';
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
import { move } from '@/routes/control/credits';

/**
 * Control taking Credits from one purse and handing them to another.
 *
 * `GiveCreditsDialog` with the payer chosen too, since nobody's seat stands
 * behind a ruling. Both pickers draw from the same list, and their values are
 * positions in it because a Corporation and a character can share an id.
 */
export function MoveCreditsDialog({
    gameId,
    purses,
}: {
    gameId: number;
    purses: CreditRecipient[];
}) {
    const [open, setOpen] = useState(false);
    const [fromIndex, setFromIndex] = useState<number | null>(null);
    const [toIndex, setToIndex] = useState<number | null>(null);
    const [amount, setAmount] = useState('1');
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const options = purses.map((purse, index) => ({
        value: index,
        label: purse.name,
        hint: purse.hint,
        search: `${purse.name} ${purse.hint}`,
    }));

    const close = () => {
        setOpen(false);
        setFromIndex(null);
        setToIndex(null);
        setAmount('1');
        setReason('');
        setError(null);
    };

    const submit = () => {
        const from = fromIndex === null ? null : purses[fromIndex];
        const to = toIndex === null ? null : purses[toIndex];

        if (!from || !to) {
            return;
        }

        router.post(
            move.url({ game: gameId }),
            {
                from_type: from.type,
                from_id: from.id,
                to_type: to.type,
                to_id: to.id,
                amount: Number(amount),
                reason: reason === '' ? null : reason,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: close,
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'Those Credits could not be moved.',
                    ),
            },
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => (next ? setOpen(true) : close())}
        >
            <DialogTrigger asChild>
                <Button variant="outline">Move Credits</Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Move Credits</DialogTitle>
                    <DialogDescription>
                        Take Credits from one Corporation or person and hand
                        them to another. Both sides are written to the ledger
                        with your name on them.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="move-credits-from">From</Label>
                        <SearchPicker
                            id="move-credits-from"
                            options={options}
                            value={fromIndex}
                            onChange={setFromIndex}
                            placeholder={`Search ${purses.length} Corporations and people…`}
                            searchPlaceholder="Name, role or gang…"
                            emptyMessage="Nobody of that name carries Credits."
                        />
                    </div>

                    <div className="grid gap-1">
                        <Label htmlFor="move-credits-to">To</Label>
                        <SearchPicker
                            id="move-credits-to"
                            options={options}
                            value={toIndex}
                            onChange={setToIndex}
                            placeholder={`Search ${purses.length} Corporations and people…`}
                            searchPlaceholder="Name, role or gang…"
                            emptyMessage="Nobody of that name carries Credits."
                        />
                    </div>

                    <div className="grid gap-1">
                        <Label htmlFor="move-credits-amount">Credits</Label>
                        {/* No `max`: the server says how many there are. */}
                        <Input
                            id="move-credits-amount"
                            type="number"
                            min={1}
                            value={amount}
                            onChange={(event) => setAmount(event.target.value)}
                        />
                    </div>

                    <div className="grid gap-1">
                        <Label htmlFor="move-credits-reason">
                            Reason (optional)
                        </Label>
                        <Input
                            id="move-credits-reason"
                            value={reason}
                            placeholder="Blackmail paid out"
                            onChange={(event) => setReason(event.target.value)}
                        />
                    </div>
                </div>

                {error ? (
                    <p className="text-sm text-destructive">{error}</p>
                ) : null}

                <DialogFooter>
                    <Button variant="ghost" onClick={close}>
                        Cancel
                    </Button>
                    <Button
                        disabled={
                            fromIndex === null ||
                            toIndex === null ||
                            amount === '' ||
                            processing
                        }
                        onClick={submit}
                    >
                        Move
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
