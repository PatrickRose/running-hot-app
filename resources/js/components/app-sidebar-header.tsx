import { usePage, usePoll } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { PhaseClock } from '@/components/phase-clock';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';
import type { PhaseSummary } from '@/types/game';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    // Shared from HandleInertiaRequests, so the clock is on every page rather
    // than on the handful that asked for a game.
    const phase = usePage<{ phase: PhaseSummary | null }>().props.phase;

    // One poll for the clock, wherever you are. It asks for nothing but the
    // phase, which is an always prop - so this stays a small request even on a
    // page whose own poll is fetching a board.
    usePoll(5000, { only: ['phase'] });

    return (
        // Sticky, because "how long is left" is the one thing that has to be
        // legible without scrolling back up - and the phase clock is what the
        // whole game runs to.
        <header className="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 bg-background px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {phase && (
                <PhaseClock phase={phase} size="compact" className="ml-auto" />
            )}
        </header>
    );
}
