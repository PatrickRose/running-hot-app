import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { webhook } from '@/routes/control/games';

/**
 * Repoints a game at a different Discord channel.
 *
 * The webhook is required when a game is created, so this exists so that a
 * mispaste does not mean recreating the game.
 */
export function GameWebhook({
    gameId,
    url,
    isPlaceholder,
}: {
    gameId: number;
    url: string;
    isPlaceholder: boolean;
}) {
    return (
        <Form
            {...webhook.form({ game: gameId })}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <Label htmlFor="discord_webhook_url">
                        Announcement webhook
                    </Label>

                    {isPlaceholder && (
                        <p className="text-sm text-amber-600 dark:text-amber-500">
                            This is the seeded placeholder — announcements will
                            not arrive until you set the real webhook.
                        </p>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Input
                            id="discord_webhook_url"
                            name="discord_webhook_url"
                            type="url"
                            required
                            defaultValue={url}
                            className="min-w-64 flex-1 font-mono text-xs"
                        />
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </div>

                    <InputError message={errors.discord_webhook_url} />
                </>
            )}
        </Form>
    );
}
