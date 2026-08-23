import { Form, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/control/facility-types';
import type { FacilityTypeSummary } from '@/types/game';

/**
 * The game's Facility type catalogue (rulebook 3.3.1).
 *
 * Research, Security and Corporate are seeded, but the rulebook says more types
 * may be researched during the game, so Control adds one here rather than
 * waiting on a deployment. The two grant fields are what make a new type
 * mechanical rather than decorative.
 */
export function FacilityTypeCatalogue({
    gameId,
    types,
}: {
    gameId: number;
    types: FacilityTypeSummary[];
}) {
    return (
        <div className="flex flex-col gap-6">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="py-2 pr-4 font-medium">Type</th>
                            <th className="py-2 pr-4 text-right font-medium">
                                Card slots each
                            </th>
                            <th className="py-2 pr-4 text-right font-medium">
                                Tech storage each
                            </th>
                            <th className="py-2 pr-4 text-right font-medium">
                                Built
                            </th>
                            <th className="py-2 font-medium" />
                        </tr>
                    </thead>
                    <tbody>
                        {types.map((type) => (
                            <tr
                                key={type.id}
                                className="border-b align-top last:border-0"
                            >
                                <td className="py-2 pr-4">
                                    <span className="font-medium">
                                        {type.name}
                                    </span>
                                    {type.description && (
                                        <p className="mt-1 max-w-prose text-muted-foreground">
                                            {type.description}
                                        </p>
                                    )}
                                </td>
                                <td className="py-2 pr-4 text-right font-mono tabular-nums">
                                    {type.protection_slots_granted > 0
                                        ? `+${type.protection_slots_granted}`
                                        : '—'}
                                </td>
                                <td className="py-2 pr-4 text-right font-mono tabular-nums">
                                    {type.technology_capacity_granted > 0
                                        ? `+${type.technology_capacity_granted}`
                                        : '—'}
                                </td>
                                <td className="py-2 pr-4 text-right font-mono tabular-nums">
                                    {type.facility_count}
                                </td>
                                <td className="py-2 text-right">
                                    {type.in_use ? (
                                        <Badge variant="outline">In use</Badge>
                                    ) : (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.delete(
                                                    destroy.url({
                                                        game: gameId,
                                                        facilityType: type.id,
                                                    }),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {types.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="py-4 text-muted-foreground"
                                >
                                    No Facility types yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <Form
                {...store.form({ game: gameId })}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="grid gap-3 rounded-md border p-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2 lg:col-span-2">
                            <Label htmlFor="facility-type-name">
                                New Facility type
                            </Label>
                            <Input
                                id="facility-type-name"
                                name="name"
                                required
                                placeholder="Fabrication"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="facility-type-slots">
                                Card slots each
                            </Label>
                            <Input
                                id="facility-type-slots"
                                name="protection_slots_granted"
                                type="number"
                                min={0}
                                defaultValue={0}
                            />
                            <InputError
                                message={errors.protection_slots_granted}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="facility-type-storage">
                                Tech storage each
                            </Label>
                            <Input
                                id="facility-type-storage"
                                name="technology_capacity_granted"
                                type="number"
                                min={0}
                                defaultValue={0}
                            />
                            <InputError
                                message={errors.technology_capacity_granted}
                            />
                        </div>

                        <div className="grid gap-2 lg:col-span-3">
                            <Label htmlFor="facility-type-description">
                                What it does (optional)
                            </Label>
                            <Input
                                id="facility-type-description"
                                name="description"
                                placeholder="Houses prototypes that cannot leave the line"
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="flex items-end">
                            <Button type="submit" disabled={processing}>
                                Add type
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
