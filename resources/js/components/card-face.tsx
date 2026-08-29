import { useState } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/** The card's own words, in the order they are printed. */
type CardLine = { label: string; value: string | null };

/**
 * A card, shown as its printed artwork where there is any and as a card-shaped
 * box of its own text where there is not.
 *
 * The text box is not a fallback so much as the other normal case. Control
 * invents cards during play - the rulebook has research proposals priced and
 * added to the tree mid-game, and a DTC technology hands out a bypass card named
 * after whichever Protection Card it counters - and those have never been
 * printed. They still have to read as cards.
 *
 * The artwork is high resolution and a stack view can show a dozen at once, so
 * images are lazy and decode off the main thread. An image that fails to load
 * falls back to the text, which also covers a checkout that does not have the
 * artwork committed.
 *
 * Cards are given a width and left to find their own height, because the three
 * families are not the same shape: Equipment is printed portrait, and the
 * Protection and research cards landscape.
 *
 * A card showing its artwork carries its text on hover, because the artwork is
 * the one case where the words are not on screen: at this size the printing on a
 * card is not legible, and Control ruling on a challenge needs to read it. The
 * text box needs no tooltip - it is already the text.
 */
export function CardFace({
    name,
    code,
    imagePath,
    lines,
    footer,
    className,
}: {
    name: string;
    code?: string | null;
    imagePath?: string | null;
    lines?: CardLine[];
    footer?: string | null;
    className?: string;
}) {
    const [imageFailed, setImageFailed] = useState(false);
    const showImage = Boolean(imagePath) && !imageFailed;

    if (!showImage) {
        return (
            <article
                className={cn(
                    'flex w-40 shrink-0 flex-col gap-2 rounded-lg border bg-card p-3 text-card-foreground shadow-xs sm:w-48',
                    className,
                )}
            >
                <CardHeading name={name} code={code} />

                {printed(lines).map((line, index) => (
                    <p
                        key={`${index}-${line.label}`}
                        className="text-xs leading-snug"
                    >
                        <span className="text-muted-foreground">
                            {line.label}
                        </span>{' '}
                        {line.value}
                    </p>
                ))}

                {footer ? (
                    <footer className="mt-auto text-[10px] text-muted-foreground">
                        {footer}
                    </footer>
                ) : null}
            </article>
        );
    }

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
                    <img
                        src={imagePath as string}
                        alt=""
                        loading="lazy"
                        decoding="async"
                        onError={() => setImageFailed(true)}
                        // No fixed aspect: the families are not the same
                        // shape. Equipment is printed portrait and both the
                        // Protection and research cards landscape, so forcing
                        // one ratio would crop two thirds of the game.
                        className="h-auto w-full rounded-lg border bg-muted"
                    />
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

function CardHeading({ name, code }: { name: string; code?: string | null }) {
    return (
        <header className="flex items-baseline justify-between gap-2">
            <h4 className="text-sm leading-tight font-medium">{name}</h4>
            {code ? (
                <span className="shrink-0 font-mono text-[10px] text-muted-foreground">
                    {code}
                </span>
            ) : null}
        </header>
    );
}

/**
 * The lines that actually say something. A card with no Charge has no Charge
 * line rather than an empty one.
 */
function printed(lines?: CardLine[]): CardLine[] {
    return (lines ?? []).filter((line) => line.value);
}
