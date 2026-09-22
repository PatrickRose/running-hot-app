import { ClockIcon, FlagIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { GameSummary } from '@/types/game';

/**
 * Why this page cannot be acted on.
 *
 * A game is worth reading either side of the evening - a Draft one so a player
 * can read their own briefing before the session, a Finished one because it is
 * the record of how the game went - and every act in the application is refused
 * off the clock by the policy behind it. So these pages come up read-only on
 * their own, and what was missing was anything saying *why*: a board whose
 * buttons have all gone reads as a broken page rather than as a game that has
 * not started.
 *
 * Nothing here enforces anything, and nothing should. The server has already
 * decided; this is the sentence that tells the player what they are looking at.
 */
export function GameStateNotice({ game }: { game: GameSummary }) {
    if (game.status === 'running') {
        return null;
    }

    const notStarted = game.status === 'draft';

    return (
        <Alert>
            {notStarted ? <ClockIcon /> : <FlagIcon />}
            <AlertTitle>
                {notStarted
                    ? 'This game has not started yet.'
                    : 'This game has finished.'}
            </AlertTitle>
            <AlertDescription>
                <p>
                    {notStarted
                        ? 'Control has not started the clock, so nothing can be done here yet. Everything set up so far is readable: your seats, the Facilities, and what you are carrying.'
                        : 'Nothing further can be done in it. What is here is the record of how it went, and it stays readable.'}
                </p>
            </AlertDescription>
        </Alert>
    );
}
