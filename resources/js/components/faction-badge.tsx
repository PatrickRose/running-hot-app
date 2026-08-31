import type { Faction } from '@/types/game';

/**
 * A faction drawn as its logo, or as its initials on its own colour.
 *
 * The fallback is the normal case rather than an error state: a clean checkout
 * has no artwork at all, and a Corporation Control invents mid-game has never
 * had a logo drawn for it. So a faction with nothing on record still gets a
 * badge the same size and the same colour as one that has artwork, and a row of
 * them still lines up.
 *
 * The colour is the one the server sent, which is the colour of the Discord
 * role these players are already wearing — so a team looks the same here, in
 * the channel list, and on the #facility-list embed.
 *
 * Always paired with the faction's name in the surrounding markup, so the badge
 * is decorative: `aria-hidden` on the logo and the initials alike, because a
 * screen reader reading "GE" after "Genetic Equity" is reading the name twice.
 */
export function FactionBadge({
    faction,
    size = 'default',
    className = '',
}: {
    faction: Faction;
    /** `small` for a badge beside a line of text, `large` for a page heading. */
    size?: 'small' | 'default' | 'large';
    className?: string;
}) {
    const box = {
        small: 'size-6 text-[0.5rem]',
        default: 'size-9 text-xs',
        large: 'size-12 text-sm',
    }[size];

    return (
        <span
            aria-hidden="true"
            className={`flex shrink-0 items-center justify-center overflow-hidden rounded-md ring-1 ring-black/10 dark:ring-white/15 ${box} ${className}`}
            // The faction's own colour only where it is standing in for a logo.
            // Behind real artwork it would be an arbitrary hash colour imposed
            // on somebody's brand — Dancers drawn on yellow when their own
            // palette is magenta — so a logo gets a plain white ground instead.
            // Not none: the artwork is transparent around a black diamond
            // frame, which on a dark page is a logo nobody can see. White is
            // what these are drawn to sit on, in either theme.
            style={
                faction.logo_path === null
                    ? { backgroundColor: faction.colour }
                    : undefined
            }
        >
            {faction.logo_path ? (
                <img
                    src={faction.logo_path}
                    alt=""
                    className="size-full bg-white object-contain"
                />
            ) : (
                <span className="font-semibold tracking-wide text-white drop-shadow-sm">
                    {initials(faction.name)}
                </span>
            )}
        </span>
    );
}

/**
 * Up to three initials from a faction's name.
 *
 * Words rather than characters, so Digital Tactical Control reads DTC and
 * Gordon reads G. A one-word name with no capitals past the first — g33ks —
 * falls back to its first two characters, because a single "g" in a box is not
 * enough to tell two gangs apart.
 */
function initials(name: string): string {
    const words = name
        .split(/[\s-]+/)
        .filter((word) => /[a-z0-9]/i.test(word))
        .slice(0, 3);

    if (words.length > 1) {
        return words
            .map((word) => word[0])
            .join('')
            .toUpperCase();
    }

    return (words[0] ?? name).slice(0, 2).toUpperCase();
}
