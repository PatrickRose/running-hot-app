import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { stats } from '@/routes/control/characters';
import type { CharacterSubject } from '@/types/game';

const FIELDS: Array<['brawn' | 'hack' | 'charisma' | 'body', string]> = [
    ['brawn', 'Brawn'],
    ['hack', 'Hack'],
    ['charisma', 'Charisma'],
    ['body', 'Body'],
];

/**
 * The four printed stats on a character sheet, edited in one go.
 *
 * Not a TrackerValue each, and the difference matters: Wounds, Tags and Credits
 * move during play and every movement is a ledger row explaining itself. These
 * four are what a character is. Nothing in the rules spends them, so there is
 * nothing for a ledger to explain — only Control correcting a roster, which is
 * one edit of four numbers rather than four adjustments.
 *
 * An Inertia Form rather than router.patch, so each row keeps its own refusal:
 * every character on the page reports against the same four keys, and a
 * page-level `errors` would put one row's message under all of them.
 */
export function CharacterStatsForm({
    gameId,
    character,
}: {
    gameId: number;
    character: CharacterSubject;
}) {
    return (
        <Form
            {...stats.form({ game: gameId, character: character.subject_id })}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-1"
        >
            {({ processing, errors }) => (
                <>
                    {/* One line, so a character is one row of the table rather
                        than three. The inputs are narrow because the numbers
                        are: nothing on a character sheet runs to two digits. */}
                    <div className="flex items-end gap-1.5">
                        {FIELDS.map(([field, label]) => (
                            <div key={field} className="flex flex-col gap-0.5">
                                <label
                                    htmlFor={`${field}-${character.subject_id}`}
                                    className="text-xs text-muted-foreground"
                                >
                                    {label}
                                </label>
                                <Input
                                    id={`${field}-${character.subject_id}`}
                                    name={field}
                                    type="number"
                                    defaultValue={character[field]}
                                    className="h-8 w-14 px-2 text-right font-mono tabular-nums"
                                />
                            </div>
                        ))}

                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={processing}
                        >
                            Save
                        </Button>
                    </div>

                    {/* One line for whichever of the four was refused, under
                        the row it belongs to. */}
                    <InputError
                        message={
                            errors.brawn ??
                            errors.hack ??
                            errors.charisma ??
                            errors.body
                        }
                    />
                </>
            )}
        </Form>
    );
}
