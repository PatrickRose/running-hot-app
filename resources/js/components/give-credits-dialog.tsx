import { router } from '@inertiajs/react';
import { useState } from 'react';
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
import { give } from '@/routes/credits';

/** Somebody Credits can be paid to: a Corporation, or a person with a purse. */
export type CreditRecipient = {
    type: 'character' | 'corporation';
    id: number;
    name: string;
    hint: string;
};

/**
 * Paying somebody out of the purse a seat spends.
 *
 * `GiveCardDialog`'s shape with Credits in place of a card: say who, say how
 * much, and only the Credits move. Whatever was agreed in exchange is settled
 * at the table. The picker's values are positions in the list, because a
 * Corporation and a character can share an id.
 */
export function GiveCreditsDialog({
    fromCharacterId,
    purse,
    recipients,
}: {
    fromCharacterId: number;
    purse: { name: string; credits: number };
    recipients: CreditRecipient[];
}) {
    const [open, setOpen] = useState(false);
    const [toIndex, setToIndex] = useState<number | null>(null);
    const [amount, setAmount] = useState('1');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const options = recipients.map((recipient, index) => ({
        value: index,
        label: recipient.name,
        hint: recipient.hint,
        search: `${recipient.name} ${recipient.hint}`,
    }));

    const close = () => {
        setOpen(false);
        setToIndex(null);
        setAmount('1');
        setError(null);
    };

    const submit = () => {
        const to = toIndex === null ? null : recipients[toIndex];

        if (!to) {
            return;
        }

        router.post(
            give.url(),
            {
                from_character_id: fromCharacterId,
                to_type: to.type,
                to_id: to.id,
                amount: Number(amount),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: close,
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'Those Credits could not be given.',
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
                <Button variant="outline" size="sm">
                    Give Credits
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Give Credits</DialogTitle>
                    <DialogDescription>
                        Out of {purse.name}'s {purse.credits} Credits. Only the
                        Credits move — whatever was agreed for them is settled
                        at the table.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="give-credits-to">To</Label>
                        <SearchPicker
                            id="give-credits-to"
                            options={options}
                            value={toIndex}
                            onChange={setToIndex}
                            placeholder={`Search ${recipients.length} Corporations and people…`}
                            searchPlaceholder="Name, role or gang…"
                            emptyMessage="Nobody of that name can be paid."
                        />
                    </div>

                    <div className="grid gap-1">
                        <Label htmlFor="give-credits-amount">Credits</Label>
                        {/* No `max`, for the reason GiveCardDialog has none:
                            the server says how many there are, and a browser
                            constraint would make that refusal a dead button. */}
                        <Input
                            id="give-credits-amount"
                            type="number"
                            min={1}
                            value={amount}
                            onChange={(event) => setAmount(event.target.value)}
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
                            toIndex === null || amount === '' || processing
                        }
                        onClick={submit}
                    >
                        Give
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
