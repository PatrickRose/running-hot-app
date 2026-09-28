import { usePoll } from '@inertiajs/react';
import { useEffect } from 'react';

type PollOptions = Parameters<typeof usePoll>[1];

/**
 * Polls while `live` holds, and stops the moment it does not.
 *
 * A poll is a request, and a request keeps the deployment awake: an open tab
 * polling a game that is not running is paying for compute to learn nothing,
 * and stops the environment ever scaling back to zero. So every poll hangs off
 * whether the game is being played, and a page left open overnight goes quiet
 * once the game finishes.
 *
 * Followed rather than read once, because the poll that lands the game's end
 * is also the one that has to stop it — and a page reading a Draft game starts
 * polling again as soon as a visit brings it back running.
 */
export function useLivePoll(
    live: boolean,
    interval: number,
    options?: PollOptions,
): void {
    const { start, stop } = usePoll(interval, options, { autoStart: false });

    useEffect(() => {
        if (live) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [live, start, stop]);
}
