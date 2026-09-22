import { useState } from 'react';
import type { ReactNode } from 'react';
import { CardFace } from '@/components/card-face';
import { SearchPicker } from '@/components/search-picker';
import type { PickerOption } from '@/components/search-picker';
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
import type { EquipmentRecipient } from '@/types/game';

/** The card being handed over, as either list has it. */
export type GiveableCard = {
    name: string;
    code: string | null;
    image_path: string | null;
    effect: string;
    category_label: string;
    category_glyph: string;
};

/**
 * Handing a card over by picking it up.
 *
 * The card is the control. Both places a card changes hands — Control working
 * down the Equipment list, and a player looking at their own hand — are pages
 * of card faces, and the thing being talked about at the table is the card, so
 * pointing at it is the gesture: click the card, say who it is going to, done.
 * A picker asking which of seventy-four cards you meant was a second list to
 * search when the answer was already on screen and under the pointer.
 *
 * The trigger is a real button wrapping the face rather than a click handler on
 * it, so the keyboard reaches it — and it is the *only* tab stop on a card,
 * because `CardFace`'s own tooltip is deliberately not focusable and a page can
 * list two hundred of them.
 *
 * Who it goes to is still a picker: a roster of forty is a list nobody finds
 * anybody in as a native `select`, which is the shop's reasoning exactly.
 *
 * Only the card moves, in both places. There is no price box and there
 * deliberately is not one: what was agreed in exchange is settled at the table,
 * as a research point trade is (3.2.5).
 */
export function GiveCardDialog({
    card,
    recipients,
    title,
    description,
    actionLabel,
    inHand = null,
    submit,
    children,
}: {
    card: GiveableCard;
    recipients: EquipmentRecipient[];
    title: string;
    description: string;
    actionLabel: string;
    /** Copies in the hand it is coming out of, where it is coming out of one. */
    inHand?: number | null;
    submit: (
        to: number,
        copies: number,
        handlers: {
            onSuccess: () => void;
            onError: (message: string) => void;
        },
    ) => void;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [toId, setToId] = useState<number | null>(null);
    const [copies, setCopies] = useState('1');
    const [error, setError] = useState<string | null>(null);

    const close = () => {
        setOpen(false);
        setToId(null);
        setCopies('1');
        setError(null);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => (next ? setOpen(true) : close())}
        >
            <DialogTrigger className="rounded-lg text-left ring-offset-background transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none">
                {children}
                <span className="sr-only">
                    {actionLabel} — {card.name}
                </span>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <div className="flex flex-wrap items-start gap-4">
                    <CardFace
                        name={card.name}
                        code={card.code}
                        imagePath={card.image_path}
                        shape="portrait"
                        lines={[
                            {
                                label: 'Type',
                                value: card.category_label,
                                glyph: card.category_glyph,
                            },
                            { label: '', value: card.effect },
                        ]}
                    />

                    <div className="flex min-w-48 flex-1 flex-col gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="give-card-to">To</Label>
                            <SearchPicker
                                id="give-card-to"
                                options={recipientOptions(recipients)}
                                value={toId}
                                onChange={setToId}
                                placeholder={`Search ${recipients.length} people…`}
                                searchPlaceholder="Name, role or team…"
                                emptyMessage="Nobody of that name is in this game."
                            />
                        </div>

                        <div className="grid gap-1">
                            <Label htmlFor="give-card-copies">Copies</Label>
                            {/* No `max`, deliberately: a browser-side
                                constraint that blocks the submit outright
                                makes a refusal look like a dead button, which
                                is the bug the run screen's top-up already
                                had. The server says how many are there. */}
                            <Input
                                id="give-card-copies"
                                type="number"
                                min={1}
                                value={copies}
                                onChange={(event) =>
                                    setCopies(event.target.value)
                                }
                            />
                            {inHand !== null ? (
                                <p className="text-xs text-muted-foreground">
                                    {inHand} in hand.
                                </p>
                            ) : null}
                        </div>
                    </div>
                </div>

                {/* Kept on the dialog rather than read off the page: a page
                    draws a great many of these and they all report against the
                    same handful of keys, so a page-level `errors` would put one
                    card's refusal under every card on screen. */}
                {error ? (
                    <p className="text-sm text-destructive">{error}</p>
                ) : null}

                <DialogFooter>
                    <Button variant="ghost" onClick={close}>
                        Cancel
                    </Button>
                    <Button
                        disabled={toId === null || copies === ''}
                        onClick={() =>
                            toId !== null &&
                            submit(toId, Number(copies), {
                                onSuccess: close,
                                onError: setError,
                            })
                        }
                    >
                        {actionLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function recipientOptions(
    recipients: EquipmentRecipient[],
): PickerOption[] {
    return recipients.map((person) => ({
        value: person.character_id,
        label: person.name,
        hint: person.team
            ? `${person.role_label} — ${person.team}`
            : person.role_label,
        search: [person.name, person.role_label, person.team]
            .filter(Boolean)
            .join(' '),
    }));
}
