import { usePage, usePoll } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { PhaseClock } from '@/components/phase-clock';
import { PlayerStandingStrip } from '@/components/player-standing';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';
import type { PhaseSummary, PlayerStanding } from '@/types/game';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    // Both shared from HandleInertiaRequests, so they are on every page rather
    // than on the handful that asked for a game.
    const { phase, standing } = usePage<{
        phase: PhaseSummary | null;
        standing: PlayerStanding | null;
    }>().props;

    // One poll for the header, wherever you are. It asks for nothing but the
    // phase — which is an always prop, so this stays a small request even on a
    // page whose own poll is fetching a board. The standing rides along without
    // being named for the same reason: an always prop ignores the filter, so
    // Credits re-anchor on every poll any page already makes.
    usePoll(5000, { only: ['phase'] });

    return (
        // Sticky, because "how long is left" and "what can I afford" are the
        // two things that have to be legible without scrolling back up — and
        // the phase clock is what the whole game runs to.
        <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 bg-background px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto flex items-center gap-3 sm:gap-4">
                {standing && <PlayerStandingStrip standing={standing} />}
                {phase && <PhaseClock phase={phase} size="compact" />}
            </div>
        </header>
    );
}
