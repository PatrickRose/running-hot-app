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
            [key: string]: unknown;
        };
    }
}
