import { useState } from 'react';
import { GameIcon } from '@/components/game-icon';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * One of the card's own lines, in the order it is printed.
 *
 * A line may carry an icon from the game's font — an Equipment card's category
 * is drawn as well as named — in which case the icon leads and the words follow.
 */
type CardLine = {
    label: string;
    value: string | null;
    glyph?: string | null;
};

/**
 * The two sizes the game's cards are printed at.
 *
 * Every piece of artwork is one or the other: 600x440 for the Protection and
 * research cards, 600x817 for Equipment. Holding a card to its family's shape is
 * what lets a card with no artwork sit in a row beside cards that have some
 * without being visibly the odd one out.
 */
const SHAPES = {
    landscape: 'aspect-[600/440]',
    portrait: 'aspect-[600/817]',
} as const;

export type CardShape = keyof typeof SHAPES;

/**
 * The fade over the last of a text card's words.
 *
 * A card is the size of a card, so a wordy one runs past the bottom of its box
 * - and a hard cut through a half-drawn line reads as a bug rather than as more
 * text. Fading the last few millimetres says there is more, and the tooltip and
 * the screen-reader text carry all of it. Nothing is faded when the words fit:
 * the gradient falls on empty card.
 */
const OVERFLOW_FADE =
    'linear-gradient(to bottom, black calc(100% - 0.75rem), transparent)';

/**
 * A card, shown as its printed artwork where there is any and as a card-shaped
 * box of its own text where there is not.
 *
 * The text box is not a fallback so much as the other normal case. Control
 * invents cards during play - the rulebook has research proposals priced and
 * added to the tree mid-game, and a DTC technology hands out a bypass card named
 * after whichever Protection Card it counters - and those have never been
 * printed. They still have to read as cards, which means being the size of one:
 * both faces are the same width and the same shape, so a list of one family
 * lines up whether or not the artwork has been drawn yet.
 *
 * The shape is the family's rather than the image's, because the families are
 * not printed alike - Equipment portrait, the Protection and research cards
 * landscape - and forcing one ratio across all three would crop two thirds of
 * the game.
 *
 * The artwork is high resolution and a stack view can show a dozen at once, so
 * images are lazy and decode off the main thread. An image that fails to load
 * falls back to the text, which also covers a checkout that does not have the
 * artwork committed.
 *
 * Every card carries its text on hover, because at this size neither face
 * reliably shows it: the printing on the artwork is not legible, and a wordy
 * card can outrun its box. Control ruling on a challenge needs to read it, so
 * the same words are also always in the page for a screen reader rather than
 * behind a hover.
 */
export function CardFace({
    name,
    code,
    imagePath,
    lines,
    footer,
    shape = 'landscape',
    className,
}: {
    name: string;
    code?: string | null;
    imagePath?: string | null;
    lines?: CardLine[];
    footer?: string | null;
    shape?: CardShape;
    className?: string;
}) {
    const [imageFailed, setImageFailed] = useState(false);
    const showImage = Boolean(imagePath) && !imageFailed;

    return (
        <Tooltip>
            {/* asChild so the trigger stays a figure rather than becoming a
                button. A page can list two hundred cards, and a tab stop each
                would bury every real control on the page. The tooltip is
                therefore a convenience for a pointer, and the text below it is
                what a screen reader gets - always, without having to find and
                hover anything. */}
            <TooltipTrigger asChild>
                <figure
                    className={cn(
                        'flex w-40 shrink-0 flex-col gap-1 sm:w-48',
                        className,
                    )}
                >
                    {showImage ? (
                        <img
                            src={imagePath as string}
                            alt=""
                            loading="lazy"
                            decoding="async"
                            onError={() => setImageFailed(true)}
                            // The shape is declared rather than waited for, so
                            // a page of lazy images does not reflow as they
                            // arrive. object-contain keeps a card that was
                            // scanned at some other ratio whole.
                            className={cn(
                                'w-full rounded-lg border bg-muted object-contain',
                                SHAPES[shape],
                            )}
                        />
                    ) : (
                        <div
                            className={cn(
                                // overflow-hidden holds the box to the aspect ratio:
                                // without it the ratio is only a
                                // preferred size and a wordy card
                                // grows past the artwork beside it.
                                'flex flex-col overflow-hidden rounded-lg border bg-card p-3 text-card-foreground shadow-xs',
                                SHAPES[shape],
                            )}
                        >
                            {/* The words fade where they run past the card;
                                the footer sits below the fade, because what a
                                card is - a Charge, research-only - is worth
                                more at a glance than one more line of prose. */}
                            <div
                                className="flex min-h-0 flex-1 flex-col gap-1.5 overflow-hidden"
                                style={{
                                    maskImage: OVERFLOW_FADE,
                                    WebkitMaskImage: OVERFLOW_FADE,
                                }}
                            >
                                {code ? (
                                    <span className="self-end font-mono text-[10px] text-muted-foreground">
                                        {code}
                                    </span>
                                ) : null}

                                {printed(lines).map((line, index) => (
                                    <p
                                        key={`${index}-${line.label}`}
                                        className="text-xs leading-snug"
                                    >
                                        {line.glyph ? (
                                            <GameIcon
                                                glyph={line.glyph}
                                                label={line.label}
                                                className="mr-1 font-icons text-sm leading-none not-italic"
                                            />
                                        ) : null}
                                        <span className="text-muted-foreground">
                                            {line.label}
                                        </span>{' '}
                                        {line.value}
                                    </p>
                                ))}
                            </div>

                            {footer ? (
                                <span className="shrink-0 pt-1 text-[10px] text-muted-foreground">
                                    {footer}
                                </span>
                            ) : null}
                        </div>
                    )}

                    <figcaption className="truncate text-xs text-muted-foreground">
                        {name}
                    </figcaption>
                    {/* The figcaption above already names the card, so this
                        adds only the words that are not on screen. */}
                    <span className="sr-only">
                        <CardWords code={code} lines={lines} footer={footer} />
                    </span>
                </figure>
            </TooltipTrigger>
            <TooltipContent side="right" className="max-w-xs">
                <CardWords
                    name={name}
                    code={code}
                    lines={lines}
                    footer={footer}
                />
            </TooltipContent>
        </Tooltip>
    );
}

/**
 * Every word printed on a card.
 *
 * Shared by the tooltip and by the text a screen reader reads, so the two can
 * never drift into saying different things about the same card. The name is
 * optional because the two differ on exactly one point: the tooltip has to name
 * the card it belongs to, while the reader has already had the name from the
 * caption.
 */
function CardWords({
    name,
    code,
    lines,
    footer,
}: {
    name?: string;
    code?: string | null;
    lines?: CardLine[];
    footer?: string | null;
}) {
    return (
        <span className="flex flex-col gap-1 text-left">
            {name ? (
                <span className="font-medium">
                    {name}
                    {code ? (
                        <span className="ml-2 font-mono text-[10px] opacity-70">
                            {code}
                        </span>
                    ) : null}
                </span>
            ) : null}

            {printed(lines).map((line, index) => (
                <span key={`${index}-${line.label}`} className="leading-snug">
                    {line.label ? (
                        <span className="opacity-70">{line.label} </span>
                    ) : null}
                    {line.value}
                </span>
            ))}

            {footer ? <span className="opacity-70">{footer}</span> : null}
        </span>
    );
}

/**
 * The lines that actually say something. A card with no Charge has no Charge
 * line rather than an empty one.
 */
function printed(lines?: CardLine[]): CardLine[] {
    return (lines ?? []).filter((line) => line.value);
}
