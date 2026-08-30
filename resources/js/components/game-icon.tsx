import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * One icon from the game's own font, with its name for anything that cannot see
 * it.
 *
 * The font is an icon font: every icon is drawn by an ASCII capital, so the
 * glyph is a plain letter and only reads as a picture while the font is loaded.
 * A screen reader left to itself would say "E" where the page means Physical.
 * So the glyph is always hidden and the name always present — that pairing is
 * the whole point of this component, and the reason nothing renders a glyph
 * directly.
 *
 * The meanings live in App\Support\IconFont, which is where the designer's
 * mapping is written down.
 */
export function GameIcon({
    glyph,
    label,
    className,
}: {
    glyph: string;
    label: string;
    className?: string;
}) {
    return (
        <>
            <span
                aria-hidden="true"
                className={cn(
                    'font-icons text-base leading-none not-italic',
                    className,
                )}
            >
                {glyph}
            </span>
            <span className="sr-only">{label}</span>
        </>
    );
}

/**
 * An icon that names itself on hover, for the places that show it without its
 * name written out.
 *
 * asChild rather than the button Radix would give it: these appear once per row
 * in tables running to a hundred and forty rows, and a tab stop each would bury
 * every real control on the page. Nothing depends on reaching the tooltip — the
 * name is in the icon's own hidden text either way.
 */
export function GameIconWithTooltip({
    glyph,
    label,
    tooltip,
    className,
}: {
    glyph: string;
    label: string;
    /** Defaults to the label, which is what it usually should say. */
    tooltip?: string;
    className?: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="inline-flex items-center">
                    <GameIcon
                        glyph={glyph}
                        label={label}
                        className={className}
                    />
                </span>
            </TooltipTrigger>
            <TooltipContent>{tooltip ?? label}</TooltipContent>
        </Tooltip>
    );
}
