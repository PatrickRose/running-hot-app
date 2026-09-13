import { GameIcon } from '@/components/game-icon';
import { cn } from '@/lib/utils';
import type { ResearchCardSummary } from '@/types/game';

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
    return (
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
                // A marking limits how the card may be played — "No single"
                // cannot be alone in its set, "Other side must be Cog" says
                // what the far side has to be — and the server refuses an
                // equation that breaks one. At this size only the printed
                // words fit, and barely: what they mean travels in the title
                // and in the button's accessible name.
                <span
                    key={marking.label}
                    title={marking.note}
                    className="w-full truncate text-[0.6rem] leading-none text-muted-foreground"
                >
                    {marking.label}
                </span>
            ))}
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
