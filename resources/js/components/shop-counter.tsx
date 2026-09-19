import { router } from '@inertiajs/react';
import { useState } from 'react';
import { CardFace } from '@/components/card-face';
import {
    FILTER_FROM,
    listingMatches,
    ShopFilter,
} from '@/components/shop-filter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { buy } from '@/routes/shop';
import type {
    ShopBuyer,
    ShopCounter as ShopCounterData,
    ShopEquipmentCard,
    ShopListing,
    ShopProtectionCard,
} from '@/types/game';

const SELECT_CLASS =
    'h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * One counter of the shop (rulebook 3.3.3, and 2.1 for the market).
 *
 * The two counters read alike on purpose — a card, a price, what is left — and
 * differ only in who is standing at them and which purse pays. That is the
 * whole of what the rulebook makes different about them, so it is the whole of
 * what the component takes as a prop.
 *
 * Every line is drawn, including the ones that cannot be bought. A rumoured
 * card is the point of 3.3.3's list having three categories: "hold your
 * Credits, the Angel lands next turn" is the decision the list exists to let
 * Security make, and a shop that hid it would be a shop that told them nothing.
 * A sold-out line stays up for the same reason — "first come first served" only
 * reads as a rule if the person who arrived second can see they were second.
 */
export function ShopCounter({
    title,
    description,
    counter,
    shape,
    open,
    readOnlyReason,
}: {
    title: string;
    description: string;
    counter: ShopCounterData;
    shape: 'landscape' | 'portrait';
    open: boolean;
    /** Why this viewer cannot buy, where they cannot. Null when they can. */
    readOnlyReason: string | null;
}) {
    const [query, setQuery] = useState('');
    const shown = counter.listings.filter((listing) =>
        listingMatches(listing, query),
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>
                    {description}
                    {!open && (
                        <>
                            <br />
                            The shop is open during the Setup phase. Until then
                            this is a price list to plan against.
                        </>
                    )}
                    {readOnlyReason !== null && (
                        <>
                            <br />
                            {readOnlyReason}
                        </>
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {counter.listings.length > FILTER_FROM && (
                    <ShopFilter
                        id={`filter-${title}`}
                        value={query}
                        onChange={setQuery}
                        shown={shown.length}
                        total={counter.listings.length}
                    />
                )}

                {counter.listings.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Control has not put anything out yet.
                    </p>
                ) : shown.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nothing on this counter matches that.
                    </p>
                ) : (
                    shown.map((listing) => (
                        <ShopLine
                            key={listing.id}
                            listing={listing}
                            buyers={counter.buyers}
                            shape={shape}
                            open={open}
                        />
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function ShopLine({
    listing,
    buyers,
    shape,
    open,
}: {
    listing: ShopListing;
    buyers: ShopBuyer[];
    shape: 'landscape' | 'portrait';
    open: boolean;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-4 rounded-md border p-4 sm:flex-row',
                // A line nobody can buy from reads as one: dimmed rather than
                // hidden, because knowing it is there is the point.
                !listing.available && 'opacity-70',
            )}
        >
            <CardFace
                shape={shape}
                name={listing.card.name}
                code={listing.card.code}
                imagePath={listing.card.image_path}
                lines={cardLines(listing)}
            />

            <div className="flex min-w-0 flex-1 flex-col gap-3">
                <div className="flex flex-wrap items-baseline gap-2">
                    <p className="font-medium">{listing.card.name}</p>
                    <Badge
                        variant={listing.available ? 'outline' : 'secondary'}
                    >
                        {listing.status_label}
                    </Badge>
                    {listing.sold_out && (
                        <Badge variant="secondary">Sold out</Badge>
                    )}
                </div>

                <p className="text-sm">
                    <span className="font-mono tabular-nums">
                        {listing.price}
                    </span>{' '}
                    Credit{listing.price === 1 ? '' : 's'}
                    <span className="text-muted-foreground">
                        {' '}
                        &middot;{' '}
                        {listing.stock === null
                            ? 'as many as you want'
                            : `${listing.stock} left`}
                    </span>
                </p>

                {listing.notes !== null && (
                    <p className="text-sm text-muted-foreground">
                        {listing.notes}
                    </p>
                )}

                <BuyRow listing={listing} buyers={buyers} open={open} />
            </div>
        </div>
    );
}

/**
 * The buy control, which is a seat picker and a button.
 *
 * The seat is picked rather than assumed because a player may hold more than
 * one, and which of them is standing at the counter decides whose Credits pay
 * and whose hand the card lands in. With exactly one seat the picker collapses
 * to a sentence, because asking somebody to choose between one option is a
 * question that is really a statement.
 *
 * The refusal is kept here rather than read off the page, for the reason the
 * run screen keeps its own per panel: several lines are on screen at once and
 * they all report against the same handful of keys, so a page-level `errors`
 * would put one line's refusal under every line.
 */
function BuyRow({
    listing,
    buyers,
    open,
}: {
    listing: ShopListing;
    buyers: ShopBuyer[];
    open: boolean;
}) {
    const [selected, setSelected] = useState(
        buyers[0]?.character_id.toString() ?? '',
    );
    const [busy, setBusy] = useState(false);
    const [refusal, setRefusal] = useState<string | null>(null);

    if (buyers.length === 0) {
        return null;
    }

    const buyer =
        buyers.find((b) => b.character_id.toString() === selected) ?? buyers[0];
    const held = buyer.held[listing.id] ?? 0;
    const affordable = buyer.credits >= listing.price;

    const submit = () => {
        setBusy(true);
        setRefusal(null);
        router.post(
            buy.url({ listing: listing.id }),
            { character_id: buyer.character_id },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onError: (errors) =>
                    setRefusal(
                        Object.values(errors)[0] ??
                            'The shop would not take that.',
                    ),
            },
        );
    };

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center gap-2">
                {buyers.length > 1 && (
                    <select
                        aria-label="Who is buying"
                        value={selected}
                        onChange={(event) => setSelected(event.target.value)}
                        className={SELECT_CLASS}
                    >
                        {buyers.map((option) => (
                            <option
                                key={option.character_id}
                                value={option.character_id}
                            >
                                {option.name}
                            </option>
                        ))}
                    </select>
                )}

                <Button
                    size="sm"
                    disabled={
                        !listing.available || !open || busy || !affordable
                    }
                    onClick={submit}
                >
                    Buy for {listing.price}cr
                </Button>

                <span className="text-sm text-muted-foreground">
                    {buyer.purse_name} has{' '}
                    <span className="font-mono tabular-nums">
                        {buyer.credits}
                    </span>
                    cr
                    {held > 0 && ` · holding ${held}`}
                </span>
            </div>

            {/* Said before the button is pressed rather than after, because the
                answer is already known and a disabled button with no reason is
                the bug this application keeps finding. */}
            {listing.available && open && !affordable && (
                <p className="text-sm text-muted-foreground">
                    {buyer.purse_name} cannot cover this.
                </p>
            )}

            {refusal !== null && (
                <p
                    role="alert"
                    className="rounded-md border border-amber-500/40 bg-amber-50 px-2 py-1.5 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    {refusal}
                </p>
            )}
        </div>
    );
}

/**
 * What the card itself says, in the order the family prints it.
 *
 * The card is shown as artwork where there is any and as its own words where
 * there is not, which CardFace handles — this only has to say which words.
 */
function cardLines(listing: ShopListing) {
    if (listing.family === 'protection') {
        const card = listing.card as ShopProtectionCard;

        return [
            { label: 'Challenge', value: card.challenge },
            { label: '', value: card.consequence },
            {
                label: 'Charge',
                value: card.charge_consequence
                    ? `${card.charge_cost}cr — ${card.charge_consequence}`
                    : null,
            },
        ];
    }

    const card = listing.card as ShopEquipmentCard;

    return [
        {
            label: card.category_label,
            value: card.effect,
            glyph: card.category_glyph,
        },
    ];
}
