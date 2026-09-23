import { router } from '@inertiajs/react';
import { useState } from 'react';
import { FactionBadge } from '@/components/faction-badge';
import { peopleToGiveTo } from '@/components/give-card-dialog';
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
import { Label } from '@/components/ui/label';
import { cash, give } from '@/routes/stock-certificates';
import type { EquipmentRecipient, StockCertificate } from '@/types/game';

/**
 * A Stock Certificate, and the two things its holder may do with it.
 *
 * Not a card on the Equipment list: nothing about it is printed on a sheet
 * and it is never played on a run. What it does is a rule the application
 * applies - a share of the issuing Corporation's Income, worked out as the
 * Income stands when it is cashed - so both options say what they would pay
 * before the holder chooses between them.
 */
export function StockCertificateCard({
    certificate,
    recipients,
    onRevoke,
}: {
    certificate: StockCertificate;
    recipients: EquipmentRecipient[];
    /** Control's, for a certificate handed out by mistake. */
    onRevoke?: () => void;
}) {
    return (
        <div className="flex w-64 flex-col gap-3 rounded-lg border bg-card p-3 text-sm shadow-sm">
            <div className="flex items-center gap-2">
                <FactionBadge faction={certificate.corporation} size="small" />
                <div className="flex flex-col">
                    <span className="font-medium">Stock Certificate</span>
                    <span className="text-xs text-muted-foreground">
                        {certificate.corporation.name} · Income{' '}
                        {certificate.corporation.income}
                    </span>
                </div>
            </div>

            <p className="text-xs text-muted-foreground">{certificate.text}</p>

            {certificate.cashed ? (
                <p className="text-xs">
                    Cashed in
                    {certificate.cashed_by
                        ? ` by ${certificate.cashed_by}`
                        : ''}{' '}
                    for {certificate.credits_paid} Credit
                    {certificate.credits_paid === 1 ? '' : 's'} (
                    {certificate.cashed_as?.toLowerCase()}).
                </p>
            ) : (
                <div className="flex flex-wrap gap-2">
                    {certificate.can_cash ? (
                        <CashInDialog certificate={certificate} />
                    ) : null}
                    {certificate.can_give ? (
                        <HandOnDialog
                            certificate={certificate}
                            recipients={recipients.filter(
                                (person) =>
                                    person.character_id !==
                                    certificate.holder_character_id,
                            )}
                        />
                    ) : null}
                    {onRevoke ? (
                        <Button size="sm" variant="ghost" onClick={onRevoke}>
                            Take back
                        </Button>
                    ) : null}
                </div>
            )}
        </div>
    );
}

function CashInDialog({ certificate }: { certificate: StockCertificate }) {
    const [open, setOpen] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = (option: string) =>
        router.post(
            cash.url({ certificate: certificate.id }),
            { option },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setError(null);
                    setOpen(false);
                },
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'That certificate could not be cashed in.',
                    ),
            },
        );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">Cash in</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Cash in a {certificate.corporation.name} certificate
                    </DialogTitle>
                    <DialogDescription>
                        Their Income is {certificate.corporation.income} right
                        now. A certificate is cashed once, so choose carefully.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-2">
                    {certificate.options.map((option) => (
                        <Button
                            key={option.value}
                            variant="outline"
                            className="h-auto justify-start py-2 text-left whitespace-normal"
                            onClick={() => submit(option.value)}
                        >
                            Take {option.credits} Credit
                            {option.credits === 1 ? '' : 's'}
                            {option.income_reduction > 0
                                ? ` and cut ${certificate.corporation.name}'s Income by ${option.income_reduction}`
                                : ''}
                        </Button>
                    ))}
                </div>

                {error ? (
                    <p className="text-sm text-destructive">{error}</p>
                ) : null}

                <DialogFooter>
                    <Button variant="ghost" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Handing it on, which is how one is sold (3.4.3). Only the certificate
 * moves: what was agreed for it is settled at the table.
 */
function HandOnDialog({
    certificate,
    recipients,
}: {
    certificate: StockCertificate;
    recipients: EquipmentRecipient[];
}) {
    const [open, setOpen] = useState(false);
    const [toId, setToId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const close = () => {
        setOpen(false);
        setToId(null);
        setError(null);
    };

    const people = peopleToGiveTo(recipients);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => (next ? setOpen(true) : close())}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Hand on
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hand the certificate on</DialogTitle>
                    <DialogDescription>
                        Only the certificate moves — whatever was agreed for it
                        is settled at the table.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-1">
                    <Label htmlFor={`certificate-to-${certificate.id}`}>
                        {people.label}
                    </Label>
                    <SearchPicker
                        id={`certificate-to-${certificate.id}`}
                        options={people.options}
                        value={toId}
                        onChange={setToId}
                        placeholder={people.placeholder}
                        searchPlaceholder={people.searchPlaceholder}
                        emptyMessage={people.emptyMessage}
                    />
                </div>

                {error ? (
                    <p className="text-sm text-destructive">{error}</p>
                ) : null}

                <DialogFooter>
                    <Button variant="ghost" onClick={close}>
                        Cancel
                    </Button>
                    <Button
                        disabled={toId === null}
                        onClick={() =>
                            toId !== null &&
                            router.post(
                                give.url({ certificate: certificate.id }),
                                { to_character_id: toId },
                                {
                                    preserveScroll: true,
                                    onSuccess: close,
                                    onError: (errors) =>
                                        setError(
                                            Object.values(errors)[0] ??
                                                'That certificate could not be handed on.',
                                        ),
                                },
                            )
                        }
                    >
                        Hand it over
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
