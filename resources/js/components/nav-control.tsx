import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { index as cardsIndex } from '@/routes/control/cards';
import { index as councilIndex } from '@/routes/control/council';
import { index as diceIndex } from '@/routes/control/dice';
import { index as facilitiesIndex } from '@/routes/control/facilities';
import { index as gamesIndex, show } from '@/routes/control/games';
import { index as logIndex } from '@/routes/control/log';
import { index as researchIndex } from '@/routes/control/research';
import { index as runsIndex } from '@/routes/control/runs';
import { index as shopIndex } from '@/routes/control/shop';
import { index as statsIndex } from '@/routes/control/stats';
import type { NavItem } from '@/types';

/**
 * Control's own pages, as one expandable entry in the sidebar.
 *
 * Drawn only for somebody who is Control of something — the server says who,
 * through the shared `control` prop, and which game the links point at. The
 * entry itself only opens and shuts the list, and the Control panel is the
 * list's first link. With the sidebar collapsed to icons, where the list cannot
 * be shown, clicking it widens the sidebar and opens the list instead.
 */
export function NavControl() {
    const { control } = usePage().props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const sidebar = useSidebar();

    // Opens on a Control page, so the page you are on is visible in the list.
    // Read once rather than followed, so closing it by hand sticks.
    const [open, setOpen] = useState(() => isCurrentOrParentUrl(gamesIndex()));

    if (control === undefined || control === null) {
        return null;
    }

    const game = control.game;

    const pages: NavItem[] =
        game === null
            ? []
            : [
                  { title: 'Game panel', href: show(game.id) },
                  { title: 'Stats', href: statsIndex(game.id) },
                  { title: 'Game log', href: logIndex(game.id) },
                  { title: 'Facilities', href: facilitiesIndex(game.id) },
                  { title: 'Run history', href: runsIndex(game.id) },
                  { title: 'Card lists', href: cardsIndex(game.id) },
                  { title: 'Council', href: councilIndex(game.id) },
                  { title: 'Research', href: researchIndex(game.id) },
                  { title: 'Shop', href: shopIndex(game.id) },
                  { title: 'Dice rolls', href: diceIndex(game.id) },
              ];

    pages.push({ title: 'All games', href: gamesIndex() });

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Control</SidebarGroupLabel>
            <SidebarMenu>
                <Collapsible
                    asChild
                    open={open}
                    onOpenChange={(opening) => {
                        // Collapsed to icons, the sub-list cannot be drawn, so
                        // a click widens the sidebar and opens the list rather
                        // than toggling something nobody can see.
                        if (
                            sidebar.state === 'collapsed' &&
                            !sidebar.isMobile
                        ) {
                            sidebar.setOpen(true);
                            setOpen(true);

                            return;
                        }

                        setOpen(opening);
                    }}
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuButton
                                tooltip={{
                                    children:
                                        game === null
                                            ? 'Control'
                                            : `Control — ${game.name}`,
                                }}
                                className="group/control"
                            >
                                <ShieldCheck />
                                <span className="truncate">
                                    {game === null ? 'Control' : game.name}
                                </span>
                                <ChevronRight
                                    aria-hidden
                                    className="ml-auto transition-transform group-data-[state=open]/control:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                {pages.map((page) => (
                                    <SidebarMenuSubItem key={page.title}>
                                        <SidebarMenuSubButton
                                            asChild
                                            isActive={isCurrentUrl(page.href)}
                                        >
                                            <Link href={page.href} prefetch>
                                                <span>{page.title}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                ))}
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </SidebarMenu>
        </SidebarGroup>
    );
}
