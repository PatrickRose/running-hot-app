import { Head, router, usePoll } from '@inertiajs/react';
import { useState } from 'react';
import { CardFace } from '@/components/card-face';
import { GameIcon } from '@/components/game-icon';
import Heading from '@/components/heading';
import { SearchPicker } from '@/components/search-picker';
import type { PickerOption } from '@/components/search-picker';
import { listingMatches, ShopFilter } from '@/components/shop-filter';
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
import { index } from '@/routes/control/games';
import { buy, destroy, refund, stock } from '@/routes/control/shop';
import { isProtectionCard } from '@/types/game';
import type {
    GameSummary,
    ShopBuyerOption,
    ShopControlBoard,
    ShopListing,
    ShopListingStatus,
    ShopUnlistedCard,
} from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

type Props = {
    game: GameSummary;
    shop: ShopControlBoard;
};

/**
 * The shop as Control runs it (rulebook 3.3.3).
 *
 * "Control will announce the cards available for sale" is the whole of the job
 * the rulebook gives Control here, so this page is the list and nothing else is
 * derived from anything. No price is read off the card sheet's cost column: the
 * shop does not price cards the way that column suggests, and a wrong price
 * baked in would be worse than none because everything downstream would be
 * built on it.
 *
 * Auctions are deliberately absent. "Control may also decide to auction
 * Protection Cards" happens in the room, and what the application wants
 * afterwards is the result — the Credits moved with the tracker controls and
 * the holding raised on the card page, both of which already exist.
 */
export default function ControlShop({ game, shop }: Props) {
    usePoll(5000, { only: ['game', 'shop'] });

    return (
        <>
            <Head title={`${game.name} — Shop`} />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Shop"
                    description={
                        shop.open
                            ? 'Open — players can buy now.'
                            : `${shop.phase ?? 'Not started'} — the players' counters are shut, but yours is not.`
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Put a card out</CardTitle>
                        <CardDescription>
                            One line per card, so pricing a card already on the
                            list edits it. Cards that require specialised
                            research are kept off general sale by 3.3.3 and are
                            not offered here — change a card's availability in
                            the catalogue first if you mean to sell it anyway.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <StockForm
                            gameId={game.id}
                            unlisted={shop.unlisted}
                            statuses={shop.statuses}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>The list</CardTitle>
                        <CardDescription>
                            A rumoured line is on the players' list and cannot
                            be bought from, which is how 3.3.3's "rumoured to be
                            in progress" reads. A withdrawn one is off their
                            list entirely and keeps its sales. A blank stock box
                            is a line that never runs out.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TheList gameId={game.id} shop={shop} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>What has been sold</CardTitle>
                        <CardDescription>
                            The stock count says where the shelf ended up; this
                            says how it got there. Refunding puts the Credits,
                            the copy and the stock back together — a copy
                            already installed in a Facility has to come off the
                            stack first.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {shop.purchases.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nobody has bought anything.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4 font-medium">
                                                Card
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                Bought by
                                            </th>
                                            <th className="py-2 pr-4 text-right font-medium">
                                                Paid
                                            </th>
                                            <th className="py-2 pr-4 font-medium">
                                                When
                                            </th>
                                            <th className="py-2 font-medium">
                                                <span className="sr-only">
                                                    Refund
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shop.purchases.map((purchase) => (
                                            <tr
                                                key={purchase.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-2 pr-4 font-medium">
                                                    {purchase.card_name}
                                                </td>
                                                <td className="py-2 pr-4">
                                                    {purchase.buyer_name}
                                                    {purchase.corporation_name && (
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            for{' '}
                                                            {
                                                                purchase.corporation_name
                                                            }
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-2 pr-4 text-right font-mono tabular-nums">
                                                    {purchase.price_paid}
                                                </td>
                                                <td className="py-2 pr-4 text-muted-foreground">
                                                    {purchase.turn === null
                                                        ? '—'
                                                        : `Turn ${purchase.turn} ${purchase.phase ?? ''}`}
                                                </td>
                                                <td className="py-2 text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.delete(
                                                                refund.url({
                                                                    game: game.id,
                                                                    purchase:
                                                                        purchase.id,
                                                                }),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Refund
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

/**
 * Every line Control has put out, filtered once there are enough to need it.
 *
 * Control's list is the long one — it carries the withdrawn lines the players
 * never see, so it is only ever bigger than a counter.
 */
function TheList({ gameId, shop }: { gameId: number; shop: ShopControlBoard }) {
    const [query, setQuery] = useState('');
    const shown = shop.listings.filter((listing) =>
        listingMatches(listing, query),
    );

    if (shop.listings.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nothing is on the list yet.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            {shop.listings.length > 0 && (
                <ShopFilter
                    id="filter-control-shop"
                    value={query}
                    onChange={setQuery}
                    shown={shown.length}
                    total={shop.listings.length}
                />
            )}

            {shown.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No line matches that.
                </p>
            ) : (
                shown.map((listing) => (
                    <ListingRow
                        key={listing.id}
                        gameId={gameId}
                        listing={listing}
                        statuses={shop.statuses}
                        buyers={
                            listing.family === 'protection'
                                ? shop.buyers.protection
                                : shop.buyers.equipment
                        }
                    />
                ))
            )}
        </div>
    );
}

/**
 * A catalogue card as the picker takes it.
 *
 * The code goes into the search text as well as on screen, because a card is
 * identified by its code and that is what Control has in front of them when
 * somebody asks for PS013 by name. A research-only card is offered like any
 * other and simply says so.
 */
function cardOptions(cards: ShopUnlistedCard[]): PickerOption[] {
    return cards.map((card) => {
        const notes = isProtectionCard(card)
            ? [
                  card.kind_label,
                  card.availability === 'rumoured' ? 'rumoured' : '',
                  card.availability === 'research_only' ? 'research only' : '',
              ].filter(Boolean)
            : [card.category_label];

        return {
            value: card.id,
            label: card.name,
            hint: notes.join(' · ') || null,
            search: [card.code ?? '', card.name, ...notes].join(' '),
        };
    });
}

/**
 * The card Control has picked, drawn as a card.
 *
 * Pricing a card you cannot see is guesswork, and the thing somebody at the
 * table will be holding is the artwork — so the form shows it before the price
 * is typed rather than after the line is on the list. `CardFace` draws the
 * card's own words where no artwork exists, which for an invented card is the
 * normal case rather than a failure.
 *
 * The family decides the shape, as it does everywhere else: Equipment is
 * printed portrait and the Protection cards landscape, and forcing one ratio
 * across both crops half of it.
 */
function CardPreview({ card }: { card: ShopUnlistedCard }) {
    const protection = isProtectionCard(card);

    return (
        <div className="flex flex-wrap items-start gap-4 rounded-md border p-4">
            <CardFace
                shape={protection ? 'landscape' : 'portrait'}
                name={card.name}
                code={card.code}
                imagePath={card.image_path}
                lines={
                    protection
                        ? [
                              { label: 'Challenge', value: card.challenge },
                              { label: '', value: card.consequence },
                              {
                                  label: 'Charge',
                                  value: card.charge_consequence
                                      ? `${card.charge_cost}cr — ${card.charge_consequence}`
                                      : null,
                              },
                          ]
                        : [
                              {
                                  label: card.category_label,
                                  value: card.effect,
                                  glyph: card.category_glyph,
                              },
                          ]
                }
            />

            <div className="flex min-w-0 flex-1 flex-col gap-2">
                <p className="font-medium">
                    {card.name}
                    {card.code ? (
                        <span className="ml-2 font-mono text-xs font-normal text-muted-foreground">
                            {card.code}
                        </span>
                    ) : null}
                </p>

                <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <GameIcon
                        glyph={
                            protection ? card.kind_glyph : card.category_glyph
                        }
                        label={
                            protection ? card.kind_label : card.category_label
                        }
                    />
                    <span aria-hidden="true">
                        {protection ? card.kind_label : card.category_label}
                    </span>
                    {protection && card.availability !== 'available' ? (
                        <Badge variant="secondary">
                            {card.availability_label}
                        </Badge>
                    ) : null}
                </p>

                {protection ? (
                    <p className="text-sm">{card.challenge}</p>
                ) : (
                    <p className="text-sm">{card.effect}</p>
                )}
            </div>
        </div>
    );
}

/**
 * Somebody Control can sell to. Their team is searchable as well as shown: a
 * Runner is as likely to be remembered by their gang as by their own name.
 */
function buyerOptions(buyers: ShopBuyerOption[]): PickerOption[] {
    return buyers.map((buyer) => ({
        value: buyer.character_id,
        label: buyer.name,
        hint: [buyer.team, `${buyer.purse_credits}cr`]
            .filter(Boolean)
            .join(' · '),
        search: [buyer.name, buyer.team ?? '', buyer.role_label].join(' '),
    }));
}

/**
 * Putting a card out, or repricing one already on the list.
 *
 * The family is chosen rather than inferred, because the two catalogues number
 * from one apiece and a bare id could name either.
 */
function StockForm({
    gameId,
    unlisted,
    statuses,
}: {
    gameId: number;
    unlisted: ShopControlBoard['unlisted'];
    statuses: ShopControlBoard['statuses'];
}) {
    const [family, setFamily] = useState<'protection' | 'equipment'>(
        'protection',
    );
    const [cardId, setCardId] = useState<number | null>(null);
    const [price, setPrice] = useState('5');
    const [stockLevel, setStockLevel] = useState('');
    const [status, setStatus] = useState<ShopListingStatus>('on_sale');
    const [notes, setNotes] = useState('');

    const options: ShopUnlistedCard[] = unlisted[family];
    const picked = options.find((card) => card.id === cardId) ?? null;

    return (
        <div className="flex flex-col gap-3">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div className="grid gap-1">
                    <Label htmlFor="shop-family">Counter</Label>
                    <select
                        id="shop-family"
                        value={family}
                        onChange={(event) => {
                            setFamily(
                                event.target.value as
                                    'protection' | 'equipment',
                            );
                            setCardId(null);
                        }}
                        className={SELECT_CLASS}
                    >
                        <option value="protection">
                            Corporation shop (Protection)
                        </option>
                        <option value="equipment">Market (Equipment)</option>
                    </select>
                </div>

                <div className="grid gap-1 lg:col-span-2">
                    <Label htmlFor="shop-card">Card</Label>
                    <SearchPicker
                        id="shop-card"
                        options={cardOptions(options)}
                        value={cardId}
                        onChange={setCardId}
                        placeholder={`Search ${options.length} cards…`}
                        searchPlaceholder="Name, code or kind…"
                        emptyMessage="No card matches that. A card already on the list is not offered here — edit its line below instead."
                    />
                </div>

                <div className="grid gap-1">
                    <Label htmlFor="shop-price">Price (Credits)</Label>
                    <Input
                        id="shop-price"
                        type="number"
                        min={0}
                        value={price}
                        onChange={(event) => setPrice(event.target.value)}
                    />
                </div>

                <div className="grid gap-1">
                    <Label htmlFor="shop-stock">Stock</Label>
                    <Input
                        id="shop-stock"
                        type="number"
                        min={0}
                        placeholder="Unlimited"
                        value={stockLevel}
                        onChange={(event) => setStockLevel(event.target.value)}
                    />
                </div>
            </div>

            {picked !== null && <CardPreview card={picked} />}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="grid gap-1">
                    <Label htmlFor="shop-status">Status</Label>
                    <select
                        id="shop-status"
                        value={status}
                        onChange={(event) =>
                            setStatus(event.target.value as ShopListingStatus)
                        }
                        className={SELECT_CLASS}
                    >
                        {statuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="grid gap-1 lg:col-span-3">
                    <Label htmlFor="shop-notes">
                        Notes for the players (optional)
                    </Label>
                    <Input
                        id="shop-notes"
                        value={notes}
                        onChange={(event) => setNotes(event.target.value)}
                        placeholder="What a rumoured card is waiting on, who has first refusal…"
                    />
                </div>
            </div>

            <div>
                <Button
                    disabled={cardId === null}
                    onClick={() =>
                        router.post(
                            stock.url({ game: gameId }),
                            {
                                family,
                                card_id: cardId,
                                price: Number(price),
                                stock:
                                    stockLevel === ''
                                        ? null
                                        : Number(stockLevel),
                                status,
                                notes: notes === '' ? null : notes,
                            },
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setCardId(null);
                                    setNotes('');
                                },
                            },
                        )
                    }
                >
                    Put it out
                </Button>
            </div>
        </div>
    );
}

/**
 * One line on the list, with everything about it editable in place.
 *
 * Control always wins, so every number here moves: the price, the count and
 * whether it is for sale at all. Selling to a named character is here too,
 * because a player will phone a purchase in and it goes through exactly the
 * same service the players' own button does.
 */
function ListingRow({
    gameId,
    listing,
    statuses,
    buyers,
}: {
    gameId: number;
    listing: ShopListing;
    statuses: ShopControlBoard['statuses'];
    buyers: ShopBuyerOption[];
}) {
    const [price, setPrice] = useState(String(listing.price));
    const [stockLevel, setStockLevel] = useState(
        listing.stock === null ? '' : String(listing.stock),
    );
    const [status, setStatus] = useState<ShopListingStatus>(listing.status);
    const [notes, setNotes] = useState(listing.notes ?? '');
    const [buyer, setBuyer] = useState<number | null>(null);

    const save = () =>
        router.post(
            stock.url({ game: gameId }),
            {
                family: listing.family,
                card_id: listing.card.id,
                price: Number(price),
                stock: stockLevel === '' ? null : Number(stockLevel),
                status,
                notes: notes === '' ? null : notes,
            },
            { preserveScroll: true },
        );

    return (
        <div className="flex flex-col gap-3 rounded-md border p-4">
            <div className="flex flex-wrap items-baseline gap-2">
                <p className="font-medium">{listing.card.name}</p>
                {listing.card.code && (
                    <span className="font-mono text-xs text-muted-foreground">
                        {listing.card.code}
                    </span>
                )}
                <Badge variant={listing.available ? 'outline' : 'secondary'}>
                    {listing.status_label}
                </Badge>
                <span className="text-sm text-muted-foreground">
                    {listing.family === 'protection'
                        ? 'Corporation shop'
                        : 'Market'}
                    {listing.sold_count > 0 && ` · ${listing.sold_count} sold`}
                </span>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="grid gap-1">
                    <Label htmlFor={`price-${listing.id}`}>Price</Label>
                    <Input
                        id={`price-${listing.id}`}
                        type="number"
                        min={0}
                        value={price}
                        onChange={(event) => setPrice(event.target.value)}
                    />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`stock-${listing.id}`}>Stock</Label>
                    <Input
                        id={`stock-${listing.id}`}
                        type="number"
                        min={0}
                        placeholder="Unlimited"
                        value={stockLevel}
                        onChange={(event) => setStockLevel(event.target.value)}
                    />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`status-${listing.id}`}>Status</Label>
                    <select
                        id={`status-${listing.id}`}
                        value={status}
                        onChange={(event) =>
                            setStatus(event.target.value as ShopListingStatus)
                        }
                        className={SELECT_CLASS}
                    >
                        {statuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`notes-${listing.id}`}>Notes</Label>
                    <Input
                        id={`notes-${listing.id}`}
                        value={notes}
                        onChange={(event) => setNotes(event.target.value)}
                    />
                </div>
            </div>

            <div className="flex flex-wrap items-end gap-2">
                <Button size="sm" onClick={save}>
                    Save
                </Button>

                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.delete(
                            destroy.url({
                                game: gameId,
                                listing: listing.id,
                            }),
                            { preserveScroll: true },
                        )
                    }
                >
                    Delete
                </Button>

                {buyers.length > 0 && (
                    <>
                        <div className="grid min-w-56 gap-1">
                            <Label
                                htmlFor={`buyer-${listing.id}`}
                                className="text-xs text-muted-foreground"
                            >
                                Sell to
                            </Label>
                            <SearchPicker
                                id={`buyer-${listing.id}`}
                                options={buyerOptions(buyers)}
                                value={buyer}
                                onChange={setBuyer}
                                placeholder={`Search ${buyers.length} people…`}
                                searchPlaceholder="Name or team…"
                                emptyMessage="Nobody of that name holds this counter's seat."
                            />
                        </div>

                        <Button
                            size="sm"
                            variant="secondary"
                            disabled={buyer === null}
                            onClick={() =>
                                router.post(
                                    buy.url({
                                        game: gameId,
                                        listing: listing.id,
                                    }),
                                    { character_id: buyer },
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setBuyer(null),
                                    },
                                )
                            }
                        >
                            Sell
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}

ControlShop.layout = {
    breadcrumbs: [
        { title: 'Control', href: index() },
        { title: 'Shop', href: index() },
    ],
};
