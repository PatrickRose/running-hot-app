import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { requisition as requisitionRoute } from '@/routes/corporations/facilities';
import type {
    FacilityRequisition as FacilityRequisitionState,
    RequisitionableFacilityType,
} from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * What a Facility of this type adds to the Corporation, in words.
 *
 * The same four columns Control's catalogue shows as a table, said as a
 * sentence each because a tile is narrower than a table row.
 */
function grants(type: RequisitionableFacilityType): string[] {
    const lines: string[] = [];

    if (type.physical_slots_granted > 0 || type.cyber_slots_granted > 0) {
        lines.push(
            `+${type.physical_slots_granted} physical and +${type.cyber_slots_granted} cyber slots in every Facility`,
        );
    }

    if (type.technology_capacity_granted > 0) {
        lines.push(
            `+${type.technology_capacity_granted} technology storage in every Facility`,
        );
    }

    if (type.card_move_discount > 0) {
        lines.push(`${type.card_move_discount} Credits off moving cards`);
    }

    return lines;
}

/**
 * The type sheet, and the CEO's requisition slip (rulebook 3.3.1).
 *
 * Every Corporate seat reads the sheet, because what a Facility costs and does
 * is the whole Corporation's business. CEOs are the only ones allowed to build
 * Facilities, so only the CEO gets the form. The price is the type sheet's and is not asked for: naming another is
 * Control's override, and so is a build that opens at once.
 *
 * Each tile's Build button picks that type in the form rather than posting,
 * because a Facility still needs a name before it can be built.
 */
export function FacilityRequisition({
    requisition,
}: {
    requisition: FacilityRequisitionState;
}) {
    const [typeId, setTypeId] = useState<string>(
        requisition.types[0] ? String(requisition.types[0].id) : '',
    );

    const chosen = requisition.types.find((type) => String(type.id) === typeId);
    const mayBuild = requisition.can_requisition && requisition.open;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Build a Facility</CardTitle>
                <CardDescription>
                    {requisition.credits} Credits to spend &middot; A
                    requisition is raised during the Setup phase and the
                    Facility opens the Setup phase after.{' '}
                    {requisition.can_requisition
                        ? requisition.open
                            ? `Anything you build now opens on turn ${requisition.opens_on_turn}.`
                            : 'Requisitions reopen with the next Setup phase.'
                        : 'Only your CEO can build Facilities.'}
                    {requisition.build_discount > 0 &&
                        ` Construction Leader takes ${requisition.build_discount} Credits off every build.`}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                {requisition.can_requisition && (
                    <Form
                        {...requisitionRoute.form({
                            corporation: requisition.corporation_id,
                        })}
                        options={{ preserveScroll: true }}
                        resetOnSuccess={['name']}
                        className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="requisition-type">
                                        Type
                                    </Label>
                                    <select
                                        id="requisition-type"
                                        name="facility_type_id"
                                        className={SELECT_CLASS}
                                        value={typeId}
                                        onChange={(event) =>
                                            setTypeId(event.target.value)
                                        }
                                        required
                                    >
                                        {requisition.types.map((type) => (
                                            <option
                                                key={type.id}
                                                value={type.id}
                                            >
                                                {type.name} — {type.cost}{' '}
                                                Credits
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.facility_type_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="requisition-name">
                                        Name
                                    </Label>
                                    <Input
                                        id="requisition-name"
                                        name="name"
                                        required
                                        placeholder="Attercliffe Yard"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={processing || !mayBuild}
                                >
                                    {chosen
                                        ? `Build for ${chosen.cost} Credits`
                                        : 'Build'}
                                </Button>

                                {/* "Cannot afford" is reported against a
                                    cost this form does not ask for, so it
                                    is drawn here rather than lost. */}
                                <InputError
                                    className="sm:col-span-3"
                                    message={errors.cost}
                                />
                            </>
                        )}
                    </Form>
                )}

                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {requisition.types.map((type) => (
                        <li
                            key={type.id}
                            className="flex flex-col gap-2 rounded-md border p-4 text-sm"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="font-medium">{type.name}</p>
                                <Badge
                                    variant={
                                        type.cost > requisition.credits
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {type.cost < type.build_cost && (
                                        <span className="mr-1 text-muted-foreground line-through">
                                            {type.build_cost}
                                        </span>
                                    )}
                                    {type.cost} Credits
                                </Badge>
                            </div>
                            {type.description && (
                                <p className="text-muted-foreground">
                                    {type.description}
                                </p>
                            )}
                            {grants(type).map((line) => (
                                <p key={line}>{line}</p>
                            ))}
                            <p className="text-muted-foreground">
                                Stacking: {type.grant_scaling_label}
                            </p>
                            {type.access_effect && (
                                <p className="text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        If Runners get in:
                                    </span>{' '}
                                    {type.access_effect}
                                </p>
                            )}
                            <div className="mt-auto flex flex-wrap items-center justify-between gap-2 pt-1">
                                <span className="text-muted-foreground">
                                    You have {type.owned}
                                </span>
                                {requisition.can_requisition && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={!mayBuild}
                                        aria-label={`Build a ${type.name} Facility`}
                                        onClick={() => {
                                            setTypeId(String(type.id));
                                            document
                                                .getElementById(
                                                    'requisition-name',
                                                )
                                                ?.focus();
                                        }}
                                    >
                                        Build
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
