import { router } from '@inertiajs/react';
import { useState } from 'react';
import { peopleToGiveTo } from '@/components/give-card-dialog';
import { SearchPicker } from '@/components/search-picker';
import { StockCertificateCard } from '@/components/stock-certificate-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/control/stock-certificates';
import type {
    EquipmentRecipient,
    ProtectionCardRecipient,
    StockCertificate,
} from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * Control handing out Stock Certificates, and every one in the game (3.4.3).
 *
 * A Runner spends an access on a Corporate Facility's own effect and chooses a
 * certificate; this is where Control puts it in their hand. From there it is
 * the holder's to cash in or hand on, on `/equipment` - and Control can do
 * both from here as well, because Control always can.
 */
export function StockCertificateControl({
    gameId,
    certificates,
    corporations,
    people,
}: {
    gameId: number;
    certificates: StockCertificate[];
    corporations: ProtectionCardRecipient[];
    people: EquipmentRecipient[];
}) {
    const [corporationId, setCorporationId] = useState<number | null>(
        corporations[0]?.corporation_id ?? null,
    );
    const [holderId, setHolderId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);

    const picker = peopleToGiveTo(people);

    const issue = () =>
        corporationId !== null &&
        holderId !== null &&
        router.post(
            store.url({ game: gameId }),
            { corporation_id: corporationId, character_id: holderId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setHolderId(null);
                    setError(null);
                },
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'That certificate could not be handed out.',
                    ),
            },
        );

    const revoke = (certificate: StockCertificate) =>
        router.delete(
            destroy.url({ game: gameId, certificate: certificate.id }),
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'That certificate could not be taken back.',
                    ),
            },
        );

    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <div className="grid gap-1">
                    <Label htmlFor="certificate-corporation">A share in</Label>
                    <select
                        id="certificate-corporation"
                        value={corporationId ?? ''}
                        onChange={(event) =>
                            setCorporationId(Number(event.target.value))
                        }
                        className={SELECT_CLASS}
                    >
                        {corporations.map((corporation) => (
                            <option
                                key={corporation.corporation_id}
                                value={corporation.corporation_id}
                            >
                                {corporation.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="certificate-holder">Handed to</Label>
                    <SearchPicker
                        id="certificate-holder"
                        options={picker.options}
                        value={holderId}
                        onChange={setHolderId}
                        placeholder={picker.placeholder}
                        searchPlaceholder={picker.searchPlaceholder}
                        emptyMessage={picker.emptyMessage}
                    />
                </div>
                <Button
                    disabled={corporationId === null || holderId === null}
                    onClick={issue}
                >
                    Hand it over
                </Button>
            </div>

            {error ? <p className="text-sm text-destructive">{error}</p> : null}

            {certificates.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No Stock Certificates have been handed out yet.
                </p>
            ) : (
                <div className="flex flex-wrap gap-3">
                    {certificates.map((certificate) => (
                        <div
                            key={certificate.id}
                            className="flex flex-col gap-1"
                        >
                            <span className="text-xs text-muted-foreground">
                                {certificate.holder_name ?? 'Nobody'}
                            </span>
                            <StockCertificateCard
                                certificate={certificate}
                                recipients={people}
                                onRevoke={() => revoke(certificate)}
                            />
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
