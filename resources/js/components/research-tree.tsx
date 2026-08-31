import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { CardFace } from '@/components/card-face';
import InputError from '@/components/input-error';
import { ResearchSuitCost } from '@/components/research-suit-cost';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as customiseDeck } from '@/routes/research/deck';
import { store as researchTechnology } from '@/routes/research/technologies';
import type {
    ResearchFacilitySummary,
    ResearchSuitSummary,
    ResearchTreeEntry,
} from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * A Corporation's tech tree (rulebook 3.2.2), and the deck customisation the
 * same tree prices (3.2.3).
 *
 * The tree runs to a hundred and forty rows across the five Corporations, so
 * this is a table with a filter rather than a graph: what a Research player
 * actually asks is "what can we afford now?", and a picture of the whole tree
 * answers a question nobody has during a fifteen-minute phase. The three things
 * that gate a row — the points, the prerequisites, and having somewhere to put
 * the result — are worked out on the server and shown on the row, so the answer
 * is on the screen rather than a click away.
 *
 * Choosing a Facility is part of researching rather than a step afterwards.
 * Footnote 7 to 3.2.2 makes it a bar on researching at all: a Corporation with
 * nowhere to house a technology may not research it.
 */
export function ResearchTree({
    tree,
    facilities,
    suits,
    points,
    canResearch,
}: {
    tree: ResearchTreeEntry[];
    facilities: ResearchFacilitySummary[];
    suits: ResearchSuitSummary[];
    points: Record<string, number>;
    canResearch: boolean;
}) {
    const [filter, setFilter] = useState('');
    const [readyOnly, setReadyOnly] = useState(true);
    const [open, setOpen] = useState<ResearchTreeEntry | null>(null);

    const rows = useMemo(() => {
        const needle = filter.trim().toLowerCase();

        return tree.filter((entry) => {
            if (readyOnly && !isReady(entry)) {
                return false;
            }

            if (needle === '') {
                return true;
            }

            return (
                entry.name.toLowerCase().includes(needle) ||
                (entry.code ?? '').toLowerCase().includes(needle) ||
                (entry.description ?? '').toLowerCase().includes(needle) ||
                (entry.effect ?? '').toLowerCase().includes(needle)
            );
        });
    }, [tree, filter, readyOnly]);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Your tech tree</CardTitle>
                <CardDescription>
                    Spent during the Setup phase. What comes out has to be
                    housed in one of your Facilities — and if there is nowhere
                    to put it, you may not research it at all.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="tree-filter">Search</Label>
                        <Input
                            id="tree-filter"
                            value={filter}
                            onChange={(event) => setFilter(event.target.value)}
                            placeholder="Laser Porridge"
                            className="w-64"
                        />
                    </div>
                    <Button
                        type="button"
                        variant={readyOnly ? 'default' : 'outline'}
                        onClick={() => setReadyOnly((current) => !current)}
                    >
                        {readyOnly
                            ? 'Showing what you can research'
                            : 'Showing everything'}
                    </Button>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2 pr-4 font-medium">
                                    Technology
                                </th>
                                <th className="py-2 pr-4 font-medium">Cost</th>
                                <th className="py-2 pr-4 font-medium">
                                    Needed first
                                </th>
                                <th className="py-2 pr-4 font-medium">
                                    Housed in
                                </th>
                                <th className="py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((entry) => (
                                <tr
                                    key={entry.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-2 pr-4">
                                        <span className="font-medium">
                                            {entry.name}
                                        </span>
                                        {entry.code && (
                                            <span className="ml-2 font-mono text-xs text-muted-foreground">
                                                {entry.code}
                                            </span>
                                        )}
                                        <div className="mt-1 flex flex-wrap gap-1">
                                            {entry.is_deck_customisation && (
                                                <Badge variant="outline">
                                                    Deck customisation
                                                </Badge>
                                            )}
                                            {entry.split_group && (
                                                <Badge variant="secondary">
                                                    {entry.split_group} — part{' '}
                                                    {entry.split_piece} of{' '}
                                                    {entry.split_pieces}
                                                </Badge>
                                            )}
                                            {entry.researched_count > 0 && (
                                                <Badge>
                                                    Researched
                                                    {entry.researched_count > 1
                                                        ? ` ×${entry.researched_count}`
                                                        : ''}
                                                </Badge>
                                            )}
                                            {entry.claims.length > 0 && (
                                                <Badge variant="outline">
                                                    {
                                                        entry.claims[0]
                                                            .origin_label
                                                    }{' '}
                                                    ·{' '}
                                                    {
                                                        entry.claims[0]
                                                            .discount_percent
                                                    }
                                                    % off
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="py-2 pr-4">
                                        {entry.is_deck_customisation &&
                                        entry.deck_grant ? (
                                            <FlexibleCost
                                                grant={entry.deck_grant}
                                            />
                                        ) : (
                                            <ResearchSuitCost
                                                cost={entry.cost}
                                                suits={suits}
                                            />
                                        )}
                                    </td>
                                    <td className="py-2 pr-4 text-muted-foreground">
                                        {entry.missing_prerequisites.length > 0
                                            ? entry.missing_prerequisites.join(
                                                  ', ',
                                              )
                                            : entry.prerequisites.length > 0
                                              ? '—'
                                              : ''}
                                    </td>
                                    <td className="py-2 pr-4 text-muted-foreground">
                                        {entry.required_facility_type ??
                                            (entry.is_deck_customisation
                                                ? '—'
                                                : 'Anywhere')}
                                    </td>
                                    <td className="py-2 text-right">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setOpen(entry)}
                                        >
                                            Open
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="py-4 text-muted-foreground"
                                    >
                                        Nothing here. Earn more points, or show
                                        everything.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </CardContent>

            <Dialog
                open={open !== null}
                onOpenChange={(next) => !next && setOpen(null)}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    {open && (
                        <TechnologyDetail
                            entry={open}
                            facilities={facilities}
                            suits={suits}
                            points={points}
                            canResearch={canResearch}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </Card>
    );
}

/**
 * Whether a row is one this Corporation could act on now.
 *
 * Affordability alone is not enough: a technology whose prerequisites are
 * missing is not "ready" however many points are on the table.
 */
function isReady(entry: ResearchTreeEntry): boolean {
    if (entry.missing_prerequisites.length > 0) {
        return false;
    }

    if (entry.is_deck_customisation) {
        return true;
    }

    return entry.affordable || entry.claims.length > 0;
}

/**
 * A deck customisation row's price: amounts in suits of the player's choosing.
 *
 * Written as "4 in any suit" rather than as four columns, because that is what
 * the row says and there is no suit to put an icon against yet.
 */
function FlexibleCost({
    grant,
}: {
    grant: NonNullable<ResearchTreeEntry['deck_grant']>;
}) {
    return (
        <span className="text-muted-foreground">
            {grant.amounts.join(' + ')} in{' '}
            {grant.amounts.length === 1
                ? 'any suit'
                : `${grant.amounts.length} different suits`}
        </span>
    );
}

function TechnologyDetail({
    entry,
    facilities,
    suits,
    points,
    canResearch,
}: {
    entry: ResearchTreeEntry;
    facilities: ResearchFacilitySummary[];
    suits: ResearchSuitSummary[];
    points: Record<string, number>;
    canResearch: boolean;
}) {
    // Where this card could actually go: open, with room, and of the type the
    // card names if it names one.
    const usable = facilities.filter(
        (facility) =>
            facility.available &&
            facility.stored < facility.capacity &&
            (entry.required_facility_type === null ||
                facility.facility_type === entry.required_facility_type),
    );

    return (
        <>
            <DialogHeader>
                <DialogTitle>{entry.name}</DialogTitle>
                <DialogDescription>
                    {entry.description ?? 'No proposal written on the card.'}
                </DialogDescription>
            </DialogHeader>

            <div className="flex flex-wrap gap-4">
                <CardFace
                    shape="landscape"
                    name={entry.name}
                    code={entry.code}
                    imagePath={entry.image_path ?? entry.back_image_path}
                    lines={[
                        { label: '', value: entry.description },
                        { label: 'Effect', value: entry.effect },
                    ]}
                    className="w-56"
                />
                <div className="flex-1 space-y-2 text-sm">
                    {entry.effect && (
                        <p>
                            <span className="text-muted-foreground">
                                Effect:{' '}
                            </span>
                            {entry.effect}
                        </p>
                    )}
                    {entry.prerequisites.length > 0 && (
                        <p>
                            <span className="text-muted-foreground">
                                Prerequisites:{' '}
                            </span>
                            {entry.prerequisites.join(', ')}
                        </p>
                    )}
                    {entry.split_group && (
                        <p className="text-muted-foreground">
                            One of {entry.split_pieces} pieces of{' '}
                            {entry.split_group}. You can work it holding any one
                            piece; anybody who takes or copies it needs them
                            all.
                        </p>
                    )}
                    {!entry.is_deck_customisation && (
                        <p className="flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground">Cost:</span>
                            <ResearchSuitCost cost={entry.cost} suits={suits} />
                        </p>
                    )}
                </div>
            </div>

            {!canResearch && (
                <p className="text-sm text-muted-foreground">
                    Your Corporation's Research player spends the points.
                </p>
            )}

            {canResearch && entry.is_deck_customisation && entry.deck_grant && (
                <DeckCustomisationForm
                    entry={entry}
                    grant={entry.deck_grant}
                    suits={suits}
                    points={points}
                />
            )}

            {canResearch && !entry.is_deck_customisation && (
                <ResearchForm entry={entry} facilities={usable} />
            )}
        </>
    );
}

function ResearchForm({
    entry,
    facilities,
}: {
    entry: ResearchTreeEntry;
    facilities: ResearchFacilitySummary[];
}) {
    if (facilities.length === 0) {
        return (
            <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                Nowhere to house this. Storage is 2 for every Corporate Facility
                you own, so build one before researching anything else.
            </p>
        );
    }

    return (
        <Form
            {...researchTechnology.form()}
            options={{ preserveScroll: true }}
            className="grid gap-3 border-t pt-4 sm:grid-cols-2"
        >
            {({ errors, processing }) => (
                <>
                    <input
                        type="hidden"
                        name="technology_type_id"
                        value={entry.id}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor={`facility-${entry.id}`}>
                            House it in
                        </Label>
                        <select
                            id={`facility-${entry.id}`}
                            name="facility_id"
                            className={SELECT_CLASS}
                        >
                            {facilities.map((facility) => (
                                <option key={facility.id} value={facility.id}>
                                    {facility.name} — {facility.facility_type} (
                                    {facility.stored}/{facility.capacity})
                                </option>
                            ))}
                        </select>
                    </div>

                    {entry.claims.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor={`claim-${entry.id}`}>
                                Trade in a card you already have
                            </Label>
                            <select
                                id={`claim-${entry.id}`}
                                name="technology_holding_id"
                                className={SELECT_CLASS}
                            >
                                <option value="">
                                    No — pay the full price
                                </option>
                                {entry.claims.map((claim) => (
                                    <option key={claim.id} value={claim.id}>
                                        {claim.origin_label} —{' '}
                                        {claim.discount_percent}% off
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                    <div className="sm:col-span-2">
                        <InputError
                            message={
                                errors.technology_type_id ?? errors.facility_id
                            }
                        />
                        <Button
                            type="submit"
                            className="mt-2"
                            disabled={processing}
                        >
                            Research it
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

/**
 * Buying a card into the deck (rulebook 3.2.3).
 *
 * The suits are the player's, one per amount the row asks for, and they have to
 * be different — "6 research credits in any suit and 3 in another". The card
 * takes the suit of the first amount, which is what the row's "in the first
 * suit" means, unless the row grants a wild card and it has none.
 */
function DeckCustomisationForm({
    entry,
    grant,
    suits,
    points,
}: {
    entry: ResearchTreeEntry;
    grant: NonNullable<ResearchTreeEntry['deck_grant']>;
    suits: ResearchSuitSummary[];
    points: Record<string, number>;
}) {
    return (
        <Form
            {...customiseDeck.form()}
            options={{ preserveScroll: true }}
            className="grid gap-3 border-t pt-4"
        >
            {({ errors, processing }) => (
                <>
                    <input
                        type="hidden"
                        name="technology_type_id"
                        value={entry.id}
                    />

                    {grant.requires_research_facilities > 0 && (
                        <p className="text-sm text-muted-foreground">
                            Needs {grant.requires_research_facilities} Research
                            Facilities.
                        </p>
                    )}

                    <div className="flex flex-wrap gap-3">
                        {grant.amounts.map((amount, index) => (
                            <div
                                // The amounts are a fixed list off the card, so
                                // the position is the identity.
                                key={index}
                                className="grid gap-2"
                            >
                                <Label
                                    htmlFor={`deck-suit-${entry.id}-${index}`}
                                >
                                    {amount} points from
                                    {index === 0 && !grant.wild
                                        ? ' (the card’s suit)'
                                        : ''}
                                </Label>
                                <select
                                    id={`deck-suit-${entry.id}-${index}`}
                                    name="suits[]"
                                    className={SELECT_CLASS}
                                    defaultValue={suits[index]?.value}
                                >
                                    {suits.map((suit) => (
                                        <option
                                            key={suit.value}
                                            value={suit.value}
                                        >
                                            {suit.label} —{' '}
                                            {points[suit.value] ?? 0} held
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ))}

                        <div className="grid gap-2">
                            <Label htmlFor={`deck-value-${entry.id}`}>
                                Card value ({grant.value_min}–{grant.value_max})
                            </Label>
                            <Input
                                id={`deck-value-${entry.id}`}
                                name="value"
                                type="number"
                                min={grant.value_min}
                                max={grant.value_max}
                                defaultValue={grant.value_min}
                                className="w-28"
                            />
                        </div>
                    </div>

                    {grant.wild && (
                        <p className="text-sm text-muted-foreground">
                            This adds a wild card, which counts as any suit.
                        </p>
                    )}
                    {grant.restriction && (
                        <p className="text-sm text-muted-foreground">
                            The card is printed “{grant.restriction}”.
                        </p>
                    )}

                    <div>
                        <InputError
                            message={
                                errors.suits ??
                                errors.value ??
                                errors.technology_type_id
                            }
                        />
                        <Button
                            type="submit"
                            className="mt-2"
                            disabled={processing}
                        >
                            Add it to your deck
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
