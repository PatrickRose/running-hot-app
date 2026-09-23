import { SearchIcon } from 'lucide-react';
import { useState } from 'react';
import { CharacterLogo } from '@/components/character-logo';
import { FactionBadge } from '@/components/faction-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { DiceRoll } from '@/types/game';

/**
 * The faces of one roll, each die drawn as the number it landed on.
 *
 * Successes are filled and the rest are outlined, so the count is readable at
 * a glance and the faces are still there for "which of those was the 8?". The
 * d8s are drawn rounder than the d6s so a mixed pool reads as two sizes.
 */
export function DiceFaces({ roll }: { roll: DiceRoll }) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {roll.faces.d6.map((face, index) => (
                <Die
                    key={`d6-${index}`}
                    face={face}
                    size={6}
                    success={face >= roll.success_on}
                />
            ))}
            {roll.faces.d8.map((face, index) => (
                <Die
                    key={`d8-${index}`}
                    face={face}
                    size={8}
                    success={face >= roll.success_on}
                />
            ))}
        </div>
    );
}

function Die({
    face,
    size,
    success,
}: {
    face: number;
    size: 6 | 8;
    success: boolean;
}) {
    return (
        <span
            className={cn(
                'inline-flex size-8 items-center justify-center border text-sm font-semibold tabular-nums',
                size === 6 ? 'rounded-md' : 'rounded-full',
                success
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border text-muted-foreground',
            )}
        >
            <span aria-hidden="true">{face}</span>
            <span className="sr-only">
                d{size} showing {face}
                {success ? ', a success' : ''}
            </span>
        </span>
    );
}

/** "3d6 + 2d8", in the order the faces are drawn. */
export function poolLabel(roll: Pick<DiceRoll, 'd6' | 'd8'>): string {
    return [
        roll.d6 > 0 ? `${roll.d6}d6` : null,
        roll.d8 > 0 ? `${roll.d8}d8` : null,
    ]
        .filter(Boolean)
        .join(' + ');
}

export function successLabel(successes: number): string {
    return `${successes} success${successes === 1 ? '' : 'es'}`;
}

/** "Turn 3 · Action", or null for a roll made with no phase running. */
function whenLabel(roll: DiceRoll): string | null {
    if (roll.turn === null) {
        return null;
    }

    return [`Turn ${roll.turn}`, roll.phase_label].filter(Boolean).join(' · ');
}

function timeLabel(roll: DiceRoll): string | null {
    return roll.rolled_at === null
        ? null
        : new Date(roll.rolled_at).toLocaleTimeString();
}

/**
 * Whether a roll matches what somebody has typed.
 *
 * Everything written on the card is searchable - who, their team, what for,
 * the turn and phase, the pool and the time - because "when did Wicker last
 * roll?" and "what did anybody roll in turn 3?" are both questions Control
 * asks.
 */
export function rollMatches(roll: DiceRoll, query: string): boolean {
    const needle = query.trim().toLowerCase();

    if (needle === '') {
        return true;
    }

    return [
        roll.character_name,
        roll.team?.name,
        roll.user_name,
        roll.purpose,
        whenLabel(roll),
        poolLabel(roll),
        successLabel(roll.successes),
        timeLabel(roll),
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
        .includes(needle);
}

/**
 * One roll: who and what for on the left, the successes on the right, the
 * faces underneath.
 */
export function DiceRollCard({ roll }: { roll: DiceRoll }) {
    const meta = [
        poolLabel(roll),
        whenLabel(roll),
        timeLabel(roll),
        // Only worth saying when it is somebody other than the seat: a player
        // account is usually named after the character it plays.
        roll.character_name &&
        roll.user_name &&
        roll.user_name !== roll.character_name
            ? `rolled by ${roll.user_name}`
            : null,
    ].filter(Boolean);

    return (
        // min-w-0 because a grid item will not shrink below its content
        // otherwise, and a long name then pushes the card off a phone.
        <Card className="min-w-0 gap-4">
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 flex-col gap-1">
                        <CardTitle className="flex items-start gap-2 text-base">
                            {roll.team !== null ? (
                                <FactionBadge
                                    faction={roll.team}
                                    size="small"
                                    className="shrink-0"
                                />
                            ) : (
                                <CharacterLogo
                                    logoPath={roll.character_logo_path}
                                />
                            )}
                            {/* Wraps rather than truncates: four to a row
                                leaves little room, and a name cut to "Digi…"
                                is no name at all. */}
                            <span className="flex min-w-0 flex-wrap gap-x-2 break-words">
                                {roll.team !== null ? (
                                    <span className="text-muted-foreground">
                                        {roll.team.name}
                                    </span>
                                ) : null}
                                <span>
                                    {roll.character_name ??
                                        roll.user_name ??
                                        'Control'}
                                </span>
                            </span>
                        </CardTitle>
                        {roll.purpose ? (
                            <p className="text-sm font-medium text-muted-foreground">
                                {roll.purpose}
                            </p>
                        ) : null}
                        <CardDescription>{meta.join(' · ')}</CardDescription>
                    </div>
                    <p className="shrink-0 text-lg font-semibold whitespace-nowrap tabular-nums">
                        {successLabel(roll.successes)}
                    </p>
                </div>
            </CardHeader>
            <CardContent>
                <DiceFaces roll={roll} />
            </CardContent>
        </Card>
    );
}

/**
 * Every roll a page carries, searchable, four to a row on a wide screen.
 *
 * The filter is always drawn and always says how many survived it, for the
 * reason the shop's is: a search that matches nothing and a log that is empty
 * look identical without a count.
 */
export function DiceRollGrid({
    rolls,
    emptyMessage,
}: {
    rolls: DiceRoll[];
    emptyMessage: string;
}) {
    const [query, setQuery] = useState('');
    const shown = rolls.filter((roll) => rollMatches(roll, query));

    if (rolls.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyMessage}</p>;
    }

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-3">
                <div className="relative min-w-56 flex-1">
                    <SearchIcon
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search by name, team, reason, turn or time…"
                        aria-label="Search the rolls"
                        className="pl-9"
                    />
                </div>
                <p aria-live="polite" className="text-sm text-muted-foreground">
                    {shown.length === rolls.length
                        ? `${rolls.length} ${rolls.length === 1 ? 'roll' : 'rolls'}`
                        : `${shown.length} of ${rolls.length}`}
                </p>
            </div>

            {shown.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No roll matches that.
                </p>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {shown.map((roll) => (
                        <DiceRollCard key={roll.id} roll={roll} />
                    ))}
                </div>
            )}
        </div>
    );
}
