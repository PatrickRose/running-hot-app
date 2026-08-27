import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { ResearchSuitSummary } from '@/types/game';

/**
 * A technology's price, in the four Research Point suits (rulebook 3.2.2).
 *
 * The rulebook shows the suits as icons and never names them in its body text,
 * so this does the same. The icons come from the game's own font, which maps
 * each one onto an ASCII letter — so the glyph is a bare capital until the font
 * loads, and a screen reader would read that letter out. Every icon therefore
 * carries the suit's name beside it, hidden from view but not from a reader, and
 * says it again in a tooltip for anyone learning the four by pointing at them.
 *
 * Only the suits that cost something are shown. Most technologies are free in at
 * least one suit, and four columns of mostly zeroes reads worse than the two or
 * three figures that actually matter.
 */
export function ResearchSuitCost({
    cost,
    suits,
}: {
    cost: Record<string, number>;
    suits: ResearchSuitSummary[];
}) {
    const priced = suits.filter((suit) => (cost[suit.value] ?? 0) > 0);

    if (priced.length === 0) {
        // A starting technology, on the tree so its split pieces can be tracked
        // rather than because anybody pays for it.
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
            {priced.map((suit) => (
                <Tooltip key={suit.value}>
                    {/* asChild rather than the default button: this table runs
                        to a hundred and forty rows, and a tab stop per suit per
                        row would drown the page. The name is in the sr-only
                        span inside, so nothing depends on reaching the
                        tooltip. */}
                    <TooltipTrigger asChild>
                        <span className="flex items-center gap-1 whitespace-nowrap">
                            <span className="font-mono tabular-nums">
                                {cost[suit.value]}
                            </span>
                            <ResearchSuitIcon suit={suit} />
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>
                        {cost[suit.value]} {suit.label}
                    </TooltipContent>
                </Tooltip>
            ))}
        </span>
    );
}

/**
 * One suit's icon, with its name for anything that cannot see it.
 */
export function ResearchSuitIcon({
    suit,
    className,
}: {
    suit: ResearchSuitSummary;
    className?: string;
}) {
    return (
        <>
            <span
                aria-hidden="true"
                className={
                    className ?? 'font-icons text-base leading-none not-italic'
                }
            >
                {suit.glyph}
            </span>
            <span className="sr-only">{suit.label}</span>
        </>
    );
}
