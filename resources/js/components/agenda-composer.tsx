import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/council/agenda-cards';

/** The bounds of rulebook 3.1.4, which the server enforces as well. */
const MINIMUM_RESOLUTIONS = 2;
const MAXIMUM_RESOLUTIONS = 5;

/**
 * A blank agenda card from the Council Chamber (rulebook 3.1.3).
 *
 * Any player may fill one out — the rulebook hands blank cards to players, not
 * to CEOs — which is why this sits on the page for everybody rather than in the
 * CEO's half of it.
 *
 * Writing it is only the first of three steps, and the form says so: the card
 * goes to Control for its remarks, comes back, and is only then submitted to
 * the Chair, and each of those is a deliberate act rather than something that
 * happens on save.
 */
export function AgendaComposer({ characterId }: { characterId: number }) {
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const [resolutions, setResolutions] = useState<string[]>(['', '']);
    const [submitting, setSubmitting] = useState(false);

    const filled = resolutions.filter((text) => text.trim() !== '');
    const ready = title.trim() !== '' && filled.length >= MINIMUM_RESOLUTIONS;

    return (
        <form
            className="flex flex-col gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                setSubmitting(true);

                router.post(
                    store.url(),
                    {
                        character_id: characterId,
                        title,
                        body: body === '' ? null : body,
                        resolutions: filled,
                    },
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            setTitle('');
                            setBody('');
                            setResolutions(['', '']);
                        },
                        onFinish: () => setSubmitting(false),
                    },
                );
            }}
        >
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="agenda-title">What is being asked</Label>
                <Input
                    id="agenda-title"
                    value={title}
                    onChange={(event) => setTitle(event.target.value)}
                    placeholder="A levy on water reclamation"
                />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="agenda-body">Background (optional)</Label>
                <textarea
                    id="agenda-body"
                    className="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    value={body}
                    onChange={(event) => setBody(event.target.value)}
                />
            </div>

            <fieldset className="flex flex-col gap-1.5">
                <legend className="text-sm font-medium">
                    Resolutions ({MINIMUM_RESOLUTIONS}&ndash;
                    {MAXIMUM_RESOLUTIONS})
                </legend>
                {resolutions.map((text, index) => (
                    <Input
                        key={index}
                        aria-label={`Resolution ${index + 1}`}
                        value={text}
                        placeholder={`Resolution ${index + 1}`}
                        onChange={(event) =>
                            setResolutions((current) =>
                                current.map((existing, position) =>
                                    position === index
                                        ? event.target.value
                                        : existing,
                                ),
                            )
                        }
                    />
                ))}

                {resolutions.length < MAXIMUM_RESOLUTIONS && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="self-start"
                        onClick={() =>
                            setResolutions((current) => [...current, ''])
                        }
                    >
                        Another resolution
                    </Button>
                )}
            </fieldset>

            <Button type="submit" size="sm" disabled={!ready || submitting}>
                Draft it
            </Button>
        </form>
    );
}
