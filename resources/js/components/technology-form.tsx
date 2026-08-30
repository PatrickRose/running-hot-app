import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/control/technologies';
import type {
    FacilityTypeSummary,
    ResearchSuitSummary,
    TechnologyTreeSummary,
} from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * Control adding a technology to a tech tree during play (rulebook 3.2.4).
 *
 * The rulebook asks for this outright: players write their own research
 * proposals during the Setup phase and Research Control prices them and adds
 * them to the tree there and then. So the tree has to grow on the night.
 *
 * The tree and the Corporation are chosen together, because a technology sits on
 * one Corporation's tree or on the set common to all of them — picking a
 * Corporation is picking the tree.
 */
export function TechnologyForm({
    gameId,
    trees,
    facilityTypes,
    suits,
}: {
    gameId: number;
    trees: TechnologyTreeSummary[];
    facilityTypes: FacilityTypeSummary[];
    suits: ResearchSuitSummary[];
}) {
    return (
        <Form
            {...store.form({ game: gameId })}
            resetOnSuccess
            className="grid gap-4 border-t pt-4 lg:grid-cols-2"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="technology-name">Name</Label>
                        <Input
                            id="technology-name"
                            name="name"
                            required
                            placeholder="Laser Porridge"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="technology-tree">Tech tree</Label>
                        <select
                            id="technology-tree"
                            name="tree"
                            className={SELECT_CLASS}
                            onChange={(event) => {
                                // The Corporation travels with the tree, so the
                                // two can never disagree.
                                const chosen = trees.find(
                                    (tree) => tree.tree === event.target.value,
                                );
                                const field =
                                    event.currentTarget.form?.elements.namedItem(
                                        'corporation_id',
                                    );

                                if (field instanceof HTMLInputElement) {
                                    field.value = chosen?.corporation_id
                                        ? String(chosen.corporation_id)
                                        : '';
                                }
                            }}
                        >
                            {trees.map((tree) => (
                                <option key={tree.tree} value={tree.tree}>
                                    {tree.label}
                                </option>
                            ))}
                        </select>
                        <input
                            type="hidden"
                            name="corporation_id"
                            defaultValue={trees[0]?.corporation_id ?? ''}
                        />
                        <InputError message={errors.tree} />
                    </div>

                    <div className="grid gap-2 lg:col-span-2">
                        <Label htmlFor="technology-description">
                            Description
                        </Label>
                        <Input
                            id="technology-description"
                            name="description"
                            placeholder="Test whether a laser could be used to heat up porridge safely"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2 lg:col-span-2">
                        <Label htmlFor="technology-effect">Effect</Label>
                        <Input
                            id="technology-effect"
                            name="effect"
                            placeholder="Unlock: Keresh"
                        />
                        <p className="text-xs text-muted-foreground">
                            What the Corporation gets. Held as words — nothing
                            acts on it, because the research game is not built.
                        </p>
                        <InputError message={errors.effect} />
                    </div>

                    <fieldset className="grid gap-2 lg:col-span-2">
                        <legend className="mb-2 text-sm font-medium">
                            Cost in Research Points
                        </legend>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {suits.map((suit) => (
                                <div key={suit.value} className="grid gap-1">
                                    <Label
                                        htmlFor={`technology-${suit.value}`}
                                        className="flex items-center gap-1.5 text-xs"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="font-icons text-base leading-none not-italic"
                                        >
                                            {suit.glyph}
                                        </span>
                                        {suit.label}
                                    </Label>
                                    <Input
                                        id={`technology-${suit.value}`}
                                        name={`${suit.value}_cost`}
                                        type="number"
                                        min={0}
                                        defaultValue={0}
                                        required
                                    />
                                </div>
                            ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Zero is a real price. Nothing in any suit makes it a
                            starting technology rather than a free one.
                        </p>
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="technology-prerequisites">
                            Prerequisites
                        </Label>
                        <Input
                            id="technology-prerequisites"
                            name="prerequisites"
                            placeholder="Deadly snake; Snake woman"
                        />
                        <p className="text-xs text-muted-foreground">
                            Card titles, separated by semicolons. A proposal may
                            name research that does not exist yet.
                        </p>
                        <InputError message={errors.prerequisites} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="technology-facility">
                            Must be housed in
                        </Label>
                        <select
                            id="technology-facility"
                            name="required_facility_type_id"
                            defaultValue=""
                            className={SELECT_CLASS}
                        >
                            <option value="">Any Facility</option>
                            {facilityTypes.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.required_facility_type_id}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="technology-copy">Copy strength</Label>
                        <Input
                            id="technology-copy"
                            name="copy_strength"
                            type="number"
                            min={0}
                            defaultValue={4}
                        />
                        <InputError message={errors.copy_strength} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="technology-destroy">
                            Destroy strength
                        </Label>
                        <Input
                            id="technology-destroy"
                            name="destroy_strength"
                            type="number"
                            min={0}
                            defaultValue={4}
                        />
                        <InputError message={errors.destroy_strength} />
                    </div>

                    <div className="grid gap-2 lg:col-span-2">
                        <Label htmlFor="technology-code">Card code</Label>
                        <Input
                            id="technology-code"
                            name="code"
                            placeholder="Leave blank for a new technology"
                        />
                        <InputError message={errors.code} />
                    </div>

                    <div className="lg:col-span-2">
                        <Button type="submit" disabled={processing}>
                            Add the technology
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
