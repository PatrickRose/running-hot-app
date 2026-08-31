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
 * No colour behind it either, for the same reason. A faction's badge sits on the
 * colour its Discord role already wears, which is what makes a logo drawn on a
 * transparent ground legible and what stands in when there is no logo at all.
 * Neither applies here — there is no fallback to colour, and these carry their
 * own ground.
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
            className={`size-6 shrink-0 rounded-md object-contain ${className}`}
        />
    );
}
