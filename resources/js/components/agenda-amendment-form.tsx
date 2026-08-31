import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/council/amendments';
import type { AgendaCardView, AgendaAmendment } from '@/types/game';

const SELECT_CLASS =
    'h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * The Chair proposing a change to a card's resolutions (rulebook 3.1.4).
 *
 * Proposing only. Nothing on the card moves until Council Control signs it off,
 * which is what the rulebook requires and what stops the words changing
 * underneath a CEO who is deciding how to vote — so the button says so.
 *
 * The bounds (at most five resolutions, never fewer than two) are the server's
 * to enforce, and it checks them again at sign-off: two amendments can be
 * waiting at once, and it is signing both off that would take a card past them.
 */
export function AgendaAmendmentForm({
    sessionId,
    card,
}: {
    sessionId: number;
    card: AgendaCardView;
}) {
    const [amendment, setAmendment] = useState<AgendaAmendment>('addition');
    const [resolutionId, setResolutionId] = useState('');
    const [text, setText] = useState('');

    const votable = card.resolutions.filter((resolution) => resolution.votable);

    const needsResolution = amendment !== 'addition';
    const needsText = amendment !== 'removal';

    const ready =
        (!needsResolution || resolutionId !== '') &&
        (!needsText || text.trim() !== '');

    return (
        <form
            className="flex flex-wrap items-center gap-2"
            onSubmit={(event) => {
                event.preventDefault();

                router.post(
                    store.url({ session: sessionId, card: card.id }),
                    {
                        amendment,
                        resolution_id:
                            resolutionId === '' ? null : Number(resolutionId),
                        text: text === '' ? null : text,
                    },
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            setText('');
                            setResolutionId('');
                        },
                    },
                );
            }}
        >
            <select
                aria-label="Amendment"
                className={SELECT_CLASS}
                value={amendment}
                onChange={(event) =>
                    setAmendment(event.target.value as AgendaAmendment)
                }
            >
                <option value="addition">Add a resolution</option>
                <option value="removal">Remove one</option>
                <option value="rewording">Reword one</option>
            </select>

            {needsResolution && (
                <select
                    aria-label="Resolution to amend"
                    className={SELECT_CLASS}
                    value={resolutionId}
                    onChange={(event) => setResolutionId(event.target.value)}
                >
                    <option value="">Which one…</option>
                    {votable.map((resolution) => (
                        <option key={resolution.id} value={resolution.id}>
                            {resolution.text}
                        </option>
                    ))}
                </select>
            )}

            {needsText && (
                <Input
                    aria-label="Wording"
                    className="w-64"
                    value={text}
                    placeholder="The wording"
                    onChange={(event) => setText(event.target.value)}
                />
            )}

            <Button type="submit" size="sm" variant="outline" disabled={!ready}>
                Ask Council Control
            </Button>
        </form>
    );
}
