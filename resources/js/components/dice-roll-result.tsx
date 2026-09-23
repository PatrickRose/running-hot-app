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
