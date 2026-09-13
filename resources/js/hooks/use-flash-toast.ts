import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

/**
 * What a controller said, shown to the person who did it.
 *
 * Two things arrive this way and they are not the same mechanism. `Inertia::flash('toast')`
 * carries a message with its own severity, and is what the settings pages use.
 * A plain `->with('status', ...)` on a redirect is what most of the
 * application does — an install, a reorder, a played equation — and it is
 * always the report of something that worked.
 *
 * Both are read off the visit rather than out of a render, because two
 * identical messages in a row are normal here: installing two cards into the
 * same Facility says the same sentence twice, and a toast that fired on a
 * changed value would show the second one nothing.
 */
export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });
    }, []);

    useEffect(() => {
        return router.on('success', (event) => {
            const props = (event as CustomEvent).detail?.page?.props as
                { flash?: { status?: string | null } } | undefined;
            const status = props?.flash?.status;

            if (!status) {
                return;
            }

            toast.success(status);
        });
    }, []);
}
