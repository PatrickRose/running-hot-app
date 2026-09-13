import { CardFacesDialog } from '@/components/card-faces-dialog';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    ResearchFacilitySummary,
    TechnologyHoldingSummary,
} from '@/types/game';

/**
 * The technology cards a Corporation has (rulebook 3.2.2).
 *
 * Grouped by the Facility storing them, because that is the thing that
 * constrains them: storage is 2 for every Corporate Facility the Corporation
 * owns, a claimed copy takes a slot just as a researched card does (footnote 8
 * to 3.2.6), and a full Facility is why the next technology cannot be
 * researched. A flat list would hide the only number that stops you.
 *
 * Two badges carry the rules that are easy to get wrong. A card that is not
 * usable is either a copy nobody has paid for yet or a piece of a split
 * technology whose thief has not collected the rest (3.2.7) — in both cases the
 * card is real, it is stored, it counts against capacity, and it does nothing.
 */
export function TechnologyHoldings({
    holdings,
    facilities,
}: {
    holdings: TechnologyHoldingSummary[];
    facilities: ResearchFacilitySummary[];
}) {
    const standing = holdings.filter(
        (holding) => holding.status !== 'destroyed',
    );
    const destroyed = holdings.filter(
        (holding) => holding.status === 'destroyed',
    );
    const unhoused = standing.filter((holding) => holding.facility_id === null);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Your technologies</CardTitle>
                <CardDescription>
                    Each Facility stores 2 for every Corporate Facility you own,
                    and a copy you have not paid for still takes up a slot.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {facilities.map((facility) => (
                    <FacilityShelf
                        key={facility.id}
                        title={`${facility.name} — ${facility.facility_type}`}
                        subtitle={
                            facility.available
                                ? `${facility.stored} of ${facility.capacity} stored`
                                : 'Still being built'
                        }
                        holdings={standing.filter(
                            (holding) => holding.facility_id === facility.id,
                        )}
                    />
                ))}

                {unhoused.length > 0 && (
                    <FacilityShelf
                        title="Not in a Facility"
                        subtitle="Control places these"
                        holdings={unhoused}
                    />
                )}

                {destroyed.length > 0 && (
                    <FacilityShelf
                        title="Destroyed"
                        subtitle="A Run got to these. Control may let you salvage the research."
                        holdings={destroyed}
                    />
                )}

                {holdings.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Nothing researched yet.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function FacilityShelf({
    title,
    subtitle,
    holdings,
}: {
    title: string;
    subtitle: string;
    holdings: TechnologyHoldingSummary[];
}) {
    return (
        <div className="rounded-md border p-3">
            <p className="font-medium">
                {title}
                <span className="ml-2 text-sm font-normal text-muted-foreground">
                    {subtitle}
                </span>
            </p>

            <ul className="mt-2 flex flex-col gap-1.5 text-sm">
                {holdings.map((holding) => (
                    <li
                        key={holding.id}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <CardFacesDialog
                            name={holding.name}
                            code={holding.code}
                            frontPath={holding.image_path}
                            backPath={holding.back_image_path}
                        />
                        <span>{holding.name}</span>
                        {holding.split_pieces !== null && (
                            <Badge variant="secondary">
                                part {holding.split_piece} of{' '}
                                {holding.split_pieces}
                            </Badge>
                        )}
                        {holding.origin !== 'researched' && (
                            <Badge variant="outline">
                                {holding.origin_label}
                                {holding.discount_percent > 0 &&
                                    ` · ${holding.discount_percent}% off`}
                            </Badge>
                        )}
                        {holding.status === 'claimed' && (
                            <Badge variant="secondary">
                                Not researched yet
                            </Badge>
                        )}
                        {holding.status === 'researched' && !holding.usable && (
                            <Badge variant="secondary">Needs every piece</Badge>
                        )}
                        {holding.notes && (
                            <span className="text-xs text-muted-foreground">
                                {holding.notes}
                            </span>
                        )}
                    </li>
                ))}
                {holdings.length === 0 && (
                    <li className="text-muted-foreground">Empty.</li>
                )}
            </ul>
        </div>
    );
}
