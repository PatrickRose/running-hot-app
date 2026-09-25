import { Link, usePage } from '@inertiajs/react';
import {
    Backpack,
    BookOpen,
    Building2,
    Layers,
    Crosshair,
    Dices,
    FlaskConical,
    Gavel,
    LayoutGrid,
    ScrollText,
    ShoppingCart,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { AppearanceToggle } from '@/components/appearance-toggle';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    cards,
    council,
    dashboard,
    dice,
    equipment,
    facilities,
    research,
    rulebook,
    runs,
    shop,
} from '@/routes';
import type { NavItem } from '@/types';

type Section = NavItem & { section: string };

const mainNavItems: Section[] = [
    {
        section: 'dashboard',
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        section: 'facilities',
        title: 'Facilities',
        href: facilities(),
        icon: Building2,
    },
    {
        section: 'runs',
        title: 'Runs',
        href: runs(),
        icon: Crosshair,
    },
    {
        section: 'equipment',
        title: 'Equipment',
        href: equipment(),
        icon: Backpack,
    },
    {
        section: 'cards',
        title: 'Cards',
        href: cards(),
        icon: Layers,
    },
    {
        section: 'council',
        title: 'Council',
        href: council(),
        icon: Gavel,
    },
    {
        section: 'research',
        title: 'Research',
        href: research(),
        icon: FlaskConical,
    },
    {
        section: 'shop',
        title: 'Shop',
        href: shop(),
        icon: ShoppingCart,
    },
    {
        section: 'dice',
        title: 'Dice',
        href: dice(),
        icon: Dices,
    },
];

export function AppSidebar() {
    // Which sections this player holds a seat for, decided server-side by
    // App\Support\Navigation. Undefined on a partial reload, which is not
    // "none": the client keeps the list it already had, so fall back to
    // drawing everything rather than blanking the sidebar mid-poll.
    const { nav, reference } = usePage().props;

    const items =
        nav === undefined
            ? mainNavItems
            : mainNavItems.filter((item) => nav.includes(item.section));

    // The reading, which is everybody's whatever seat they hold. Both open in
    // a new tab, so a player checking a rule mid-run does not lose the page
    // they were working from. The background link is Control's to set per
    // game and is simply absent until it has been.
    const backgroundUrl = reference?.background_url ?? null;

    const referenceItems: NavItem[] = [
        { title: 'Rulebook', href: rulebook(), icon: BookOpen },
        ...(backgroundUrl === null
            ? []
            : [{ title: 'Background', href: backgroundUrl, icon: ScrollText }]),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
                <NavFooter items={referenceItems} className="mt-auto" />
            </SidebarContent>

            <SidebarFooter>
                <AppearanceToggle />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
