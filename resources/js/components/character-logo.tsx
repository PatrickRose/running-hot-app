/**
 * A character's own logo, where they have one.
 *
 * Only the characters that are organisations rather than people have artwork —
 * Business Times and Th3 Undergr0und are newspapers, HM Government is a
 * government, and each is staffed by one player. Everyone else is somebody's
 * name, so this renders nothing at all rather than the initials a faction falls
 * back to: a coloured square against all forty-odd of them would imply an
 * organisation where there is none.
 *
 * Not the faction colour behind it, though. A faction's badge falls back to that
 * colour when it has no artwork, and there is no such fallback here. But these
 * are drawn transparent around a black diamond frame, which on a dark page is a
 * logo nobody can see — so it gets the same plain white ground a faction's real
 * artwork gets, which is what the artwork is drawn to sit on in either theme.
 *
 * Decorative, because the character's name is always written beside it.
 */
export function CharacterLogo({
    logoPath,
    className = '',
}: {
    logoPath: string | null;
    className?: string;
}) {
    if (logoPath === null) {
        return null;
    }

    return (
        <img
            src={logoPath}
            alt=""
            aria-hidden="true"
            className={`size-6 shrink-0 rounded-md bg-white object-contain ring-1 ring-black/10 dark:ring-white/15 ${className}`}
        />
    );
}
