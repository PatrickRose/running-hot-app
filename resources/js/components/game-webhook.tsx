import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { webhook } from '@/routes/control/games';

/**
 * Repoints a game at a different Discord channel.
 *
 * Provisioning the game's server creates this webhook, so most of the time
 * there is nothing to do here. It stays for the game whose server the
 * application did not build, and for correcting a mispaste.
 */
export function GameWebhook({
    gameId,
    url,
}: {
    gameId: number;
    url: string | null;
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

                    {url === null && (
                        <p className="text-sm text-amber-600 dark:text-amber-500">
                            No announcement channel yet, so phase changes are
                            not being posted anywhere. Provisioning the game's
                            Discord server below creates one.
                        </p>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Input
                            id="discord_webhook_url"
                            name="discord_webhook_url"
                            type="url"
                            defaultValue={url ?? ''}
                            placeholder="https://discord.com/api/webhooks/…"
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
