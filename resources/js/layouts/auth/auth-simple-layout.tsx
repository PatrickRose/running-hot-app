import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { AuthLayoutProps } from '@/types';

/**
 * The application's front door. There is no landing page any more, so this is
 * the first thing anybody sees of the game and it is dressed as the game rather
 * than as a form: the logo's own tube lights the page behind it.
 *
 * The mark is not a link. It used to point at `home`, which now redirects
 * straight back to this form - a link to the page you are already on.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-background p-6 md:p-10">
            {/* The glow off the sign, thrown onto the page behind it. Decorative
                and inert: it must never eat a click meant for the form. */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0"
                style={{
                    background:
                        'radial-gradient(48rem 28rem at 50% -8%, color-mix(in oklab, var(--primary) 20%, transparent), transparent 70%)',
                }}
            />

            <div className="relative w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <AppLogoIcon className="size-20 shadow-neon" />

                        <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                            {name}
                        </p>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col gap-6 rounded-xl border border-border/70 bg-card/80 p-6 shadow-xl backdrop-blur-sm">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
