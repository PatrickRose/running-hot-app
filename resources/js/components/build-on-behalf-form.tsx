import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { onBehalf } from '@/routes/control/facilities';
import type { CorporationFacilities, FacilityTypeSummary } from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/** What the builder is paid when Control names nothing else. */
const DEFAULT_FEE = '1';

/**
 * One Corporation building a Facility for another: MCM's Construction Leader.
 *
 * The owner is charged and the builder is paid, and both boxes start at the
 * default rather than blank so Control can see what will move before pressing
 * Build. The charge follows the type and the builder until Control types over
 * it: the type sheet's price, less the builder's own build discount.
 */
export function BuildOnBehalfForm({
    gameId,
    corporations,
    facilityTypes,
}: {
    gameId: number;
    corporations: CorporationFacilities[];
    facilityTypes: FacilityTypeSummary[];
}) {
    // The Corporation holding the biggest build discount is the one building
    // for others - in practice MCM, and the only one holding a discount at all.
    const defaultBuilder = [...corporations].sort(
        (a, b) =>
            (b.facility_build_discount ?? 0) - (a.facility_build_discount ?? 0),
    )[0];

    const [builderId, setBuilderId] = useState<number | undefined>(
        defaultBuilder?.id,
    );
    const [ownerId, setOwnerId] = useState<number | undefined>(
        corporations.find(
            (corporation) => corporation.id !== defaultBuilder?.id,
        )?.id,
    );
    const [typeId, setTypeId] = useState<number | undefined>(
        facilityTypes[0]?.id,
    );
    // Null follows the default; anything else is Control's own number.
    const [cost, setCost] = useState<string | null>(null);
    const [fee, setFee] = useState(DEFAULT_FEE);

    const builder = corporations.find(
        (corporation) => corporation.id === builderId,
    );
    const type = facilityTypes.find((candidate) => candidate.id === typeId);

    const defaultCost =
        type === undefined
            ? ''
            : String(
                  Math.max(
                      0,
                      type.build_cost - (builder?.facility_build_discount ?? 0),
                  ),
              );

    const reset = () => {
        setCost(null);
        setFee(DEFAULT_FEE);
    };

    if (corporations.length < 2) {
        return (
            <p className="text-sm text-muted-foreground">
                It takes two Corporations for one to build for the other.
            </p>
        );
    }

    return (
        <Form
            {...onBehalf.form({ game: gameId })}
            options={{ preserveScroll: true }}
            resetOnSuccess={['name']}
            onSuccess={reset}
            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="on-behalf-builder">Builder</Label>
                        <select
                            id="on-behalf-builder"
                            name="builder_corporation_id"
                            className={SELECT_CLASS}
                            value={builderId}
                            onChange={(event) =>
                                setBuilderId(Number(event.target.value))
                            }
                        >
                            {corporations.map((corporation) => (
                                <option
                                    key={corporation.id}
                                    value={corporation.id}
                                >
                                    {corporation.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.builder_corporation_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="on-behalf-owner">Built for</Label>
                        <select
                            id="on-behalf-owner"
                            name="corporation_id"
                            className={SELECT_CLASS}
                            value={ownerId}
                            onChange={(event) =>
                                setOwnerId(Number(event.target.value))
                            }
                        >
                            {corporations.map((corporation) => (
                                <option
                                    key={corporation.id}
                                    value={corporation.id}
                                >
                                    {corporation.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.corporation_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="on-behalf-type">Type</Label>
                        <select
                            id="on-behalf-type"
                            name="facility_type_id"
                            className={SELECT_CLASS}
                            value={typeId}
                            onChange={(event) =>
                                setTypeId(Number(event.target.value))
                            }
                        >
                            {facilityTypes.map((candidate) => (
                                <option key={candidate.id} value={candidate.id}>
                                    {candidate.name} — {candidate.build_cost}{' '}
                                    Credits
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.facility_type_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="on-behalf-name">Name</Label>
                        <Input
                            id="on-behalf-name"
                            name="name"
                            required
                            placeholder="Attercliffe Yard"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="on-behalf-cost">
                            Charge {ownerLabel(corporations, ownerId)}
                        </Label>
                        <Input
                            id="on-behalf-cost"
                            name="cost"
                            type="number"
                            min={0}
                            value={cost ?? defaultCost}
                            onChange={(event) => setCost(event.target.value)}
                        />
                        <InputError message={errors.cost} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="on-behalf-fee">
                            Pay {builder?.name ?? 'the builder'}
                        </Label>
                        <Input
                            id="on-behalf-fee"
                            name="fee"
                            type="number"
                            min={0}
                            value={fee}
                            onChange={(event) => setFee(event.target.value)}
                        />
                        <InputError message={errors.fee} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="on-behalf-mode">When</Label>
                        <select
                            id="on-behalf-mode"
                            name="mode"
                            className={SELECT_CLASS}
                            defaultValue="requisition"
                        >
                            <option value="requisition">
                                Requisition (opens next turn)
                            </option>
                            <option value="immediate">Build now</option>
                        </select>
                        <InputError message={errors.mode} />
                    </div>

                    <div className="grid content-end gap-2">
                        <Button type="submit" disabled={processing}>
                            Build
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function ownerLabel(
    corporations: CorporationFacilities[],
    ownerId: number | undefined,
): string {
    return (
        corporations.find((corporation) => corporation.id === ownerId)?.name ??
        'the owner'
    );
}
