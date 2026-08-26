import { useState } from 'react';
import { cn } from '@/lib/utils';

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
    /** The card's own words, in the order they are printed. */
    lines?: Array<{ label: string; value: string | null }>;
    footer?: string | null;
    className?: string;
}) {
    const [imageFailed, setImageFailed] = useState(false);
    const showImage = Boolean(imagePath) && !imageFailed;

    if (showImage) {
        return (
            <figure
                className={cn(
                    'flex w-40 shrink-0 flex-col gap-1 sm:w-48',
                    className,
                )}
            >
                <img
                    src={imagePath as string}
                    alt={name}
                    loading="lazy"
                    decoding="async"
                    onError={() => setImageFailed(true)}
                    className="aspect-[5/7] w-full rounded-lg border bg-muted object-cover"
                />
                <figcaption className="truncate text-xs text-muted-foreground">
                    {name}
                </figcaption>
            </figure>
        );
    }

    return (
        <article
            className={cn(
                'flex w-40 shrink-0 flex-col gap-2 rounded-lg border bg-card p-3 text-card-foreground shadow-xs sm:w-48',
                className,
            )}
        >
            <header className="flex items-baseline justify-between gap-2">
                <h4 className="text-sm leading-tight font-medium">{name}</h4>
                {code ? (
                    <span className="shrink-0 font-mono text-[10px] text-muted-foreground">
                        {code}
                    </span>
                ) : null}
            </header>

            {lines
                ?.filter((line) => line.value)
                .map((line) => (
                    <p key={line.label} className="text-xs leading-snug">
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
