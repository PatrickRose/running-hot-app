import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/control/equipment-cards';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * Control adding an Equipment card during play (rulebook 3.4.1).
 *
 * Needed on the night rather than only at setup: DTC's "Unfortunate Malfunction"
 * hands out a single-use bypass card named after whichever Protection Card it
 * counters, and no such card is printed. A card invented here has no code and so
 * no artwork, and is shown as its text.
 */
export function EquipmentCardForm({ gameId }: { gameId: number }) {
    return (
        <Form
            {...store.form({ game: gameId })}
            resetOnSuccess
            className="grid gap-4 border-t pt-4 lg:grid-cols-2"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="equipment-name">Name</Label>
                        <Input
                            id="equipment-name"
                            name="name"
                            required
                            placeholder="Roboscorpion bypass"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="equipment-category">Type</Label>
                        <select
                            id="equipment-category"
                            name="category"
                            defaultValue="single-use"
                            className={SELECT_CLASS}
                        >
                            <option value="permanent">
                                Permanent — equipped before the Run, three at a
                                time
                            </option>
                            <option value="this-run">
                                This run — lasts the Run, then back to Control
                            </option>
                            <option value="single-use">
                                Single use — applied at once, then back to
                                Control
                            </option>
                        </select>
                        <InputError message={errors.category} />
                    </div>

                    <div className="grid gap-2 lg:col-span-2">
                        <Label htmlFor="equipment-effect">Effect</Label>
                        <Input
                            id="equipment-effect"
                            name="effect"
                            required
                            placeholder="Automatically succeed against a Roboscorpion protection card"
                        />
                        <InputError message={errors.effect} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="equipment-cost">Cost</Label>
                        <Input
                            id="equipment-cost"
                            name="cost"
                            type="number"
                            min={0}
                            placeholder="Unpriced"
                        />
                        <p className="text-xs text-muted-foreground">
                            Optional. The card list carries no prices — the
                            market is its own piece of work.
                        </p>
                        <InputError message={errors.cost} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="equipment-code">Card code</Label>
                        <Input
                            id="equipment-code"
                            name="code"
                            placeholder="Leave blank for a new card"
                        />
                        <p className="text-xs text-muted-foreground">
                            How artwork is found. A card invented here has none
                            and shows as its text.
                        </p>
                        <InputError message={errors.code} />
                    </div>

                    <div className="lg:col-span-2">
                        <Button type="submit" disabled={processing}>
                            Add the card
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
