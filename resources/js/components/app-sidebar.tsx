import { Link, usePage } from '@inertiajs/react';
import {
    Backpack,
    Building2,
    Crosshair,
    Dices,
    FlaskConical,
    Gavel,
    LayoutGrid,
    ShoppingCart,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { AppearanceToggle } from '@/components/appearance-toggle';
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
    council,
    dashboard,
    dice,
    equipment,
    facilities,
    research,
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
    const { nav } = usePage().props;

    const items =
        nav === undefined
            ? mainNavItems
            : mainNavItems.filter((item) => nav.includes(item.section));

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
            </SidebarContent>

            <SidebarFooter>
                <AppearanceToggle />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
