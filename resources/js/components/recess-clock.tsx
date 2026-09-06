import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';
import type { CouncilSessionView } from '@/types/game';

function formatDuration(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}

/**
 * Counts down to the Council's recess (rulebook 3.1.1).
 *
 * A second clock inside the Setup phase, and server-authoritative for the same
 * reason the phase clock is: the remaining seconds are derived from a stored
 * moment and re-anchored every time fresh data arrives, so the browser only
 * ever fills in the gap between polls. A paused phase pauses this too — the
 * server sends the seconds frozen at the pause, and nothing ticks here while it
 * says so.
 */
export function RecessClock({
    session,
    className,
}: {
    session: CouncilSessionView;
    className?: string;
}) {
    if (session.recess_seconds_remaining === null) {
        return null;
    }

    return (
        <RecessCountdown
            key={`${session.id}:${session.recess_at}:${session.recess_seconds_remaining}:${session.paused}`}
            session={session}
            className={className}
        />
    );
}

function RecessCountdown({
    session,
    className,
}: {
    session: CouncilSessionView;
    className?: string;
}) {
    const [anchoredAt] = useState(() => Date.now());
    const [now, setNow] = useState(anchoredAt);

    const ticking =
        !session.paused && (session.recess_seconds_remaining ?? 0) > 0;

    useEffect(() => {
        if (!ticking) {
            return;
        }

        const interval = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(interval);
    }, [ticking]);

    const elapsed = session.paused ? 0 : Math.floor((now - anchoredAt) / 1000);
    const remaining = Math.max(
        0,
        (session.recess_seconds_remaining ?? 0) - elapsed,
    );

    return (
        <div className={cn('flex flex-col', className)}>
            <span className="text-sm text-muted-foreground">
                {remaining === 0 ? 'The Council is in recess' : 'Recess in'}
                {session.paused && ' (paused)'}
            </span>
            {remaining > 0 && (
                <span
                    className={cn(
                        'font-mono text-2xl font-semibold tabular-nums',
                        session.paused && 'text-muted-foreground',
                        remaining <= 60 && 'text-amber-600 dark:text-amber-500',
                    )}
                >
                    {formatDuration(remaining)}
                </span>
            )}
        </div>
    );
}
