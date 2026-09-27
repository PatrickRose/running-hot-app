import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /**
             * The sections this player is offered, decided server-side by
             * App\Support\Navigation from the seats they hold. Optional
             * because a partial reload leaves the client holding the list it
             * already had rather than re-sending it.
             */
            nav?: string[];
            /**
             * The current game's background link, shared beside the nav so
             * the sidebar can offer it. Optional for the reason nav is.
             */
            reference?: { background_url: string | null };
            /**
             * Null for somebody who is Control of nothing. Otherwise the game
             * the sidebar's Control links point at — null when they are not
             * Control of the game they are reading, which leaves only the list
             * of games. Optional for the reason nav is.
             */
            control?: { game: { id: number; name: string } | null } | null;
            [key: string]: unknown;
        };
    }
}
