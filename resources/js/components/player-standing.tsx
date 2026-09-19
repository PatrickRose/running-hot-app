import { cn } from '@/lib/utils';
import type { PlayerStanding, StandingCharacter } from '@/types/game';

/**
 * One number in the header strip.
 *
 * The label shortens rather than disappearing below `lg`: a bare number in a
 * header is unreadable, and the header has a fixed height to live in — so the
 * strip never wraps and never pushes the clock off the line. The full name is
 * always in the accessible text and in the title, whichever form is drawn.
 */
function Tally({
    label,
    short,
    value,
    tone = 'default',
}: {
    label: string;
    short: string;
    value: number;
    tone?: 'default' | 'warn' | 'alarm';
}) {
    return (
        <span
            className="flex items-baseline gap-1 whitespace-nowrap"
            title={`${label}: ${value}`}
        >
            <span aria-hidden="true" className="text-xs text-muted-foreground">
                <span className="lg:hidden">{short}</span>
                <span className="hidden lg:inline">{label}</span>
            </span>
            <span className="sr-only">{label}</span>
            <span
                className={cn(
                    'font-mono text-sm font-semibold tabular-nums',
                    tone === 'warn' && 'text-amber-600 dark:text-amber-500',
                    tone === 'alarm' && 'text-red-600 dark:text-red-500',
                )}
            >
                {value}
            </span>
        </span>
    );
}

/**
 * What the player is standing at, beside the clock on every page.
 *
 * Same reasoning as the clock itself: the whole game runs to these numbers, and
 * "how many Credits do I have?" was only answerable by leaving whatever page
 * the decision was on and going back to the dashboard.
 *
 * Which numbers a player sees is the server's decision, not this component's —
 * see GamePresenter::standing(). A Corporate player gets their Corporation's
 * Credits, since that is the only purse they spend from; a Runner or Freelancer
 * gets their own three; the Press outlets and HM Government get neither, and
 * arrive here as an empty list rather than a row of zeroes. Procatorion's two
 * numbers belong to the game, so everybody is shown them.
 */
export function PlayerStandingStrip({
    standing,
    className,
}: {
    standing: PlayerStanding;
    className?: string;
}) {
    if (standing.characters.length === 0) {
        return (
            <div className={cn('flex items-center gap-3', className)}>
                <Procatorion standing={standing} />
            </div>
        );
    }

    return (
        <div className={cn('flex items-center gap-3', className)}>
            <Procatorion standing={standing} />
            {standing.characters.map((character) => (
                <CharacterTallies
                    key={character.character_id}
                    character={character}
                />
            ))}
        </div>
    );
}

/**
 * Stability and Civil Unrest. Held back until `md`, because a player's own
 * Credits are the half worth keeping when the line runs out of room.
 */
function Procatorion({ standing }: { standing: PlayerStanding }) {
    return (
        <span className="hidden items-center gap-3 border-r pr-3 md:flex">
            <Tally
                label="Stability"
                short="Stab"
                value={standing.stability}
                // Zero is where the UK Government moves to close Procatorion
                // (rulebook 2.3.3). Nothing above it is a threshold the rules
                // name, so nothing above it is coloured.
                tone={standing.stability === 0 ? 'alarm' : 'default'}
            />
            <Tally
                label="Civil Unrest"
                short="Unrest"
                value={standing.civil_unrest}
            />
        </span>
    );
}

function CharacterTallies({ character }: { character: StandingCharacter }) {
    return (
        <span className="flex items-center gap-3">
            {/* Whose Credits these are: the Corporation for a Corporate seat,
                and the character otherwise. Only where there is room — a player
                holding one character already knows. */}
            <span className="hidden max-w-32 truncate text-xs text-muted-foreground xl:inline">
                {character.subject}
            </span>
            <Tally label="Credits" short="Cr" value={character.credits} />
            {character.wounds !== null && (
                <Tally
                    label="Wounds"
                    short="W"
                    value={character.wounds}
                    tone={
                        character.incapacitated
                            ? 'alarm'
                            : character.wounds > 0
                              ? 'warn'
                              : 'default'
                    }
                />
            )}
            {character.tags !== null && (
                <Tally
                    label="Tags"
                    short="Tg"
                    value={character.tags}
                    tone={character.tags > 0 ? 'warn' : 'default'}
                />
            )}
        </span>
    );
}
