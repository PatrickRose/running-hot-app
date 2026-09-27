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
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { index as cardsIndex } from '@/routes/control/cards';
import { index as councilIndex } from '@/routes/control/council';
import { index as diceIndex } from '@/routes/control/dice';
import { index as facilitiesIndex } from '@/routes/control/facilities';
import { index as gamesIndex, show } from '@/routes/control/games';
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
 * entry itself goes to that game's Control panel, so it still works with the
 * sidebar collapsed to icons, where the sub-list cannot be shown; the chevron
 * beside it opens the rest.
 */
export function NavControl() {
    const { control } = usePage().props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    // Opens on a Control page, so the page you are on is visible in the list.
    // Read once rather than followed, so closing it by hand sticks.
    const [open, setOpen] = useState(() => isCurrentOrParentUrl(gamesIndex()));

    if (control === undefined || control === null) {
        return null;
    }

    const game = control.game;
    const home = game === null ? gamesIndex() : show(game.id);

    const pages: NavItem[] =
        game === null
            ? []
            : [
                  { title: 'Game panel', href: show(game.id) },
                  { title: 'Stats', href: statsIndex(game.id) },
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
                <Collapsible asChild open={open} onOpenChange={setOpen}>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(home)}
                            tooltip={{
                                children:
                                    game === null
                                        ? 'Control'
                                        : `Control — ${game.name}`,
                            }}
                        >
                            <Link href={home} prefetch>
                                <ShieldCheck />
                                <span>
                                    {game === null ? 'Control' : game.name}
                                </span>
                            </Link>
                        </SidebarMenuButton>
                        <CollapsibleTrigger asChild>
                            <SidebarMenuAction className="data-[state=open]:rotate-90">
                                <ChevronRight />
                                <span className="sr-only">
                                    {open ? 'Hide' : 'Show'} Control pages
                                </span>
                            </SidebarMenuAction>
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
