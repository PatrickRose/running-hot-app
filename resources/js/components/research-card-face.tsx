import { GameIcon } from '@/components/game-icon';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { CardMarkingSummary, ResearchCardSummary } from '@/types/game';

/**
 * A card in the research game: a suit and a value, and nothing else
 * (rulebook 3.2.1).
 *
 * Not `CardFace`. That component draws the three printed families — Protection,
 * Equipment, technologies — at the size those cards are printed, from artwork
 * where there is any. A research card has no artwork and nothing to print: it is
 * a number and an icon, it is played five at a time from a hand and six from a
 * pool, and at the size a real card would take you could not fit an equation on
 * the screen. So it is drawn as the small tile it needs to be.
 *
 * The suit is the game's own icon, which the font maps onto an ASCII capital —
 * so it goes through `GameIcon`, and the suit's name is always in the page even
 * when the icon is only a letter. A wild card gets the font's wildcard glyph,
 * which was drawn for exactly this and has had nothing to show it until now.
 *
 * A marked card carries its markings on hover, for the reason `CardFace` does:
 * the tile is 56 pixels wide, which is not enough to print "Other side must be
 * Cog" — and that marking truncated is the one that matters, because it is the
 * suit at the end of it that the far side has to be. So the tile draws the half
 * that fits plus the suit's own icon, and the tooltip says the whole of it.
 */
export function ResearchCardFace({
    card,
    selected = false,
    className,
}: {
    card: ResearchCardSummary;
    selected?: boolean;
    className?: string;
}) {
    const face = (
        <span
            className={cn(
                'flex h-16 w-14 shrink-0 flex-col items-center justify-center gap-0.5 rounded-md border bg-card px-1 text-center shadow-xs',
                card.wild && 'border-dashed',
                selected && 'border-primary ring-2 ring-primary/40',
                className,
            )}
        >
            <span className="font-mono text-xl leading-none font-semibold tabular-nums">
                {card.value}
            </span>
            <GameIcon
                glyph={card.glyph}
                label={card.suit_label ?? 'Wildcard'}
                className="text-lg"
            />
            {card.markings.map((marking) => (
                <MarkingLine key={marking.label} marking={marking} />
            ))}
        </span>
    );

    // An unmarked card has nothing a tooltip could add: its value and its suit
    // are both drawn, and the suit is named in the page already.
    if (card.markings.length === 0) {
        return face;
    }

    return (
        <Tooltip>
            {/* asChild so the tile stays a span. It is regularly inside a
                button already — the equation builder's cards are buttons — and
                a second interactive element inside one would be both invalid
                and a tab stop per card. */}
            <TooltipTrigger asChild>{face}</TooltipTrigger>
            <TooltipContent className="max-w-56">
                <span className="flex flex-col gap-1 text-left">
                    <span className="font-medium">{card.label}</span>
                    {card.markings.map((marking) => (
                        <span key={marking.label} className="leading-snug">
                            <span className="opacity-70">{marking.label}:</span>{' '}
                            {marking.note}
                        </span>
                    ))}
                </span>
            </TooltipContent>
        </Tooltip>
    );
}

/**
 * One marking, in the space a tile has for it.
 *
 * A marking limits how the card may be played — "No single" cannot be alone in
 * its set, "Other side must be Cog" says what the far side has to be — and the
 * server refuses an equation that breaks one, so the card has to say which it
 * is before the equation is built rather than after it is refused.
 *
 * Where the marking names a suit, the suit is drawn as its icon and the words
 * shrink to the part the icon cannot say. The icon carries the marking's full
 * printed wording as its own hidden text, so the words on screen are what is
 * hidden from a screen reader rather than the other way round.
 */
function MarkingLine({ marking }: { marking: CardMarkingSummary }) {
    return (
        <span className="flex w-full items-center justify-center gap-0.5 text-[0.6rem] leading-none text-muted-foreground">
            <span className="truncate" aria-hidden={marking.glyph !== null}>
                {marking.short}
            </span>
            {marking.glyph !== null ? (
                <GameIcon
                    glyph={marking.glyph}
                    label={marking.label}
                    className="text-xs"
                />
            ) : null}
        </span>
    );
}

/**
 * The same tile, as something you can choose.
 *
 * A real button, and it stays one even though the research table wraps it in a
 * draggable: an equation is two sets rather than an ordered stack, so what a
 * player is doing is saying which side each card is on, and a click says that
 * on a phone, with a thumb or from a keyboard. Dragging is laid over the top of
 * it there rather than in place of it — see `ResearchTable`.
 */
export function ResearchCardButton({
    card,
    selected = false,
    disabled = false,
    onClick,
    hint,
}: {
    card: ResearchCardSummary;
    selected?: boolean;
    disabled?: boolean;
    onClick: () => void;
    /** What clicking will do, for anyone who cannot see the highlight. */
    hint?: string;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            aria-pressed={selected}
            className="rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            // The markings are in the name rather than left to the tooltip:
            // a tooltip is only in the page while it is open, so a keyboard
            // user tabbing through a hand would otherwise reach a card with
            // nothing said about what it may not do.
            aria-label={`${card.label}${card.markings
                .map((marking) => `, ${marking.label}: ${marking.note}`)
                .join('')}${hint ? ` — ${hint}` : ''}`}
        >
            <ResearchCardFace
                card={card}
                selected={selected}
                className={disabled ? undefined : 'hover:border-primary/60'}
            />
        </button>
    );
}
