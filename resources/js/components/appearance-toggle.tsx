import { Moon, Sun } from 'lucide-react';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';

/**
 * Light or dark, one click, where somebody actually is.
 *
 * The three-way choice still lives on the settings page, because "follow the
 * system" is a preference you set once. This is the other thing entirely: the
 * room's lights went up, or somebody is reading a Facility board on a phone in
 * daylight, and they want the other theme now rather than after two navigations
 * into settings.
 *
 * It reads `resolvedAppearance` rather than `appearance`, so a reader on
 * `system` at night is offered light - the question is "what am I looking at",
 * not "what did I choose". Choosing here does set the theme outright, which is
 * what asking for one of the two means.
 */
export function AppearanceToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const goingDark = resolvedAppearance === 'light';
    const label = goingDark ? 'Switch to dark mode' : 'Switch to light mode';

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton
                    onClick={() =>
                        updateAppearance(goingDark ? 'dark' : 'light')
                    }
                    tooltip={{ children: label }}
                    aria-label={label}
                >
                    {goingDark ? <Moon /> : <Sun />}
                    <span>{label}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
