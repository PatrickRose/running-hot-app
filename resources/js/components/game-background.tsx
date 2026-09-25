import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { background } from '@/routes/control/games';

/**
 * Points every player's sidebar at the game's background reading.
 *
 * The rulebook is the same PDF for every game; the setting, the briefings and
 * whatever else Control has written for tonight are not, and they live
 * wherever Control keeps them. Leaving the box empty takes the link away.
 */
export function GameBackground({
    gameId,
    url,
}: {
    gameId: number;
    url: string | null;
}) {
    return (
        <Form
            {...background.form({ game: gameId })}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <Label htmlFor="background_url">Background link</Label>

                    <div className="flex flex-wrap gap-2">
                        <Input
                            id="background_url"
                            name="background_url"
                            type="url"
                            defaultValue={url ?? ''}
                            placeholder="https://…"
                            className="min-w-64 flex-1 font-mono text-xs"
                        />
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </div>

                    <InputError message={errors.background_url} />
                </>
            )}
        </Form>
    );
}
