import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import type { PhaseSummary } from '@/types/game';

function formatDuration(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

/**
 * Counts down the current phase.
 *
 * The server is always the authority. Each time fresh phase data arrives the
 * inner component is remounted (via its key) and re-anchors to the server's
 * remaining seconds, so a drifting client clock can never make a phase appear
 * to run long — it only ever fills in the gap between polls.
 */
export function PhaseClock({
    phase,
    size = 'default',
    className,
}: {
    phase: PhaseSummary;
    size?: 'compact' | 'default' | 'large';
    className?: string;
}) {
    return (
        <PhaseCountdown
            key={`${phase.id}:${phase.status}:${phase.ends_at}:${phase.remaining_seconds}`}
            phase={phase}
            size={size}
            className={className}
        />
    );
}

function PhaseCountdown({
    phase,
    size,
    className,
}: {
    phase: PhaseSummary;
    size: 'compact' | 'default' | 'large';
    className?: string;
}) {
    // Anchored once at mount, so remaining time is derived rather than synced.
    const [anchoredAt] = useState(() => Date.now());
    const [now, setNow] = useState(anchoredAt);

    useEffect(() => {
        if (phase.status !== 'running') {
            return;
        }

        const interval = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(interval);
    }, [phase.status]);

    const elapsed =
        phase.status === 'running' ? Math.floor((now - anchoredAt) / 1000) : 0;
    const remaining = Math.max(0, phase.remaining_seconds - elapsed);

    const expired = remaining === 0;
    const urgent = remaining > 0 && remaining <= 60;

    const digits = (
        <span
            className={cn(
                'font-mono font-semibold tabular-nums',
                size === 'large' && 'text-6xl',
                size === 'default' && 'text-3xl',
                size === 'compact' && 'text-lg leading-none',
                phase.status === 'paused' && 'text-muted-foreground',
                urgent && 'text-amber-600 dark:text-amber-500',
                expired && 'text-red-600 dark:text-red-500',
            )}
            aria-live="polite"
        >
            {formatDuration(remaining)}
        </span>
    );

    // Laid out along the line rather than down the page, for the one place the
    // clock has a header's height to live in and no more. The label goes
    // before the digits and is dropped on a narrow screen, where the time left
    // is the half worth keeping.
    if (size === 'compact') {
        return (
            <div className={cn('flex items-center gap-2', className)}>
                <span className="hidden text-xs whitespace-nowrap text-muted-foreground sm:inline">
                    Turn {phase.turn} &middot; {phase.type_label}
                    {phase.status === 'paused' && ' (paused)'}
                </span>
                {digits}
            </div>
        );
    }

    return (
        <div className={cn('flex flex-col', className)}>
            <span className="text-sm text-muted-foreground">
                Turn {phase.turn} &middot; {phase.type_label}
                {phase.status === 'paused' && ' (paused)'}
            </span>
            {digits}
            {expired && phase.status === 'running' && (
                <span className="text-xs text-red-600 dark:text-red-500">
                    Time is up — waiting on the phase to roll over.
                </span>
            )}
        </div>
    );
}
