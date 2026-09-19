import { SearchIcon } from 'lucide-react';
import { Input } from '@/components/ui/input';
import type { ShopListing } from '@/types/game';

/**
 * How many lines a counter has to carry before it gets a filter.
 *
 * A shop with four things on it does not need searching, and an input above it
 * is one more thing to read past. Eighty does — Control can put the whole
 * catalogue out — so the control appears when it starts earning its place.
 */
export const FILTER_FROM = 8;

/**
 * Whether a line matches what somebody has typed.
 *
 * Matches the code as well as the name, because a card is identified by its
 * code, and the status word too so "rumoured" narrows to the rumoured lines.
 * Exported so the player's counters and Control's list cannot drift on what
 * searching means.
 */
export function listingMatches(listing: ShopListing, query: string): boolean {
    const needle = query.trim().toLowerCase();

    if (needle === '') {
        return true;
    }

    return [
        listing.card.name,
        listing.card.code ?? '',
        listing.status_label,
        listing.notes ?? '',
    ]
        .join(' ')
        .toLowerCase()
        .includes(needle);
}

/**
 * The filter box itself, with a count of what survived it.
 *
 * The count is the point as much as the input is: a search that matches
 * nothing and a shop that is empty look identical without it.
 */
export function ShopFilter({
    id,
    value,
    onChange,
    shown,
    total,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    shown: number;
    total: number;
}) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="relative min-w-56 flex-1">
                <SearchIcon
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id={id}
                    type="search"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder="Filter by name, code or status…"
                    aria-label="Filter the list"
                    className="pl-9"
                />
            </div>
            <p aria-live="polite" className="text-sm text-muted-foreground">
                {shown === total
                    ? `${total} on the list`
                    : `${shown} of ${total}`}
            </p>
        </div>
    );
}
