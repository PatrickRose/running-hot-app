import { Form, router } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { discord } from '@/routes/control/games';
import { provision, syncRoles } from '@/routes/control/games/discord';
import type { DiscordMemberSync, GameDiscord } from '@/types/game';

const STATUS_STYLES: Record<DiscordMemberSync['status'], string> = {
    synced: 'text-emerald-600 dark:text-emerald-500',
    not_a_member: 'text-amber-600 dark:text-amber-500',
    failed: 'text-red-600 dark:text-red-500',
};

const RESOURCE_LABELS: Record<string, string> = {
    role: 'roles',
    category: 'categories',
    text_channel: 'text channels',
    voice_channel: 'voice channels',
    webhook: 'webhooks',
};

/**
 * Control's view of a game's Discord server: which guild it is, what the
 * application has built in it, and who it could not reach.
 *
 * The application never creates the server itself — Discord only lets a bot do
 * that while it is in fewer than ten guilds, and the result has no members in
 * it — so this starts from a server ID Control pastes in.
 */
export function GameDiscordPanel({
    gameId,
    discordState,
    syncs,
}: {
    gameId: number;
    discordState: GameDiscord;
    syncs: DiscordMemberSync[];
}) {
    const notJoined = syncs.filter((sync) => sync.status === 'not_a_member');
    const failed = syncs.filter((sync) => sync.status === 'failed');
    const isProvisioned = discordState.provision_status === 'completed';

    const built = Object.entries(discordState.resource_counts)
        .map(([kind, total]) => `${total} ${RESOURCE_LABELS[kind] ?? kind}`)
        .join(', ');

    return (
        <Card>
            <CardHeader>
                <CardTitle>Discord server</CardTitle>
                <CardDescription>
                    Create the server in Discord, invite the bot with Manage
                    Roles, Manage Channels, Manage Webhooks and Create Instant
                    Invite, then paste the server ID here and provision.
                </CardDescription>
            </CardHeader>

            <CardContent className="flex flex-col gap-6">
                {!discordState.bot_configured && (
                    <p className="text-sm text-amber-600 dark:text-amber-500">
                        No bot token is configured, so the application cannot
                        act as a bot yet. Set <code>DISCORD_BOT_TOKEN</code> and
                        restart. Sign in and announcements are unaffected.
                    </p>
                )}

                <Form
                    {...discord.form({ game: gameId })}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="discord_guild_id">
                                    Server ID
                                </Label>
                                <Input
                                    id="discord_guild_id"
                                    name="discord_guild_id"
                                    inputMode="numeric"
                                    placeholder="e.g. 1129384756123456789"
                                    defaultValue={discordState.guild_id ?? ''}
                                    className="max-w-sm font-mono text-xs"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Turn on Developer Mode in Discord, then
                                    right-click the server and choose Copy
                                    Server ID.
                                </p>
                                <InputError message={errors.discord_guild_id} />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="discord_invite_url">
                                    Invite link
                                </Label>
                                <Input
                                    id="discord_invite_url"
                                    name="discord_invite_url"
                                    type="url"
                                    placeholder="https://discord.gg/…"
                                    defaultValue={discordState.invite_url ?? ''}
                                    className="max-w-sm font-mono text-xs"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Shown to a player who has signed in but is
                                    not in the server. Provisioning fills this
                                    in for you.
                                </p>
                                <InputError
                                    message={errors.discord_invite_url}
                                />
                            </div>

                            <div>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="flex flex-col gap-3 border-t pt-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant={
                                discordState.provision_status === 'failed'
                                    ? 'destructive'
                                    : 'secondary'
                            }
                        >
                            {discordState.provision_status_label}
                        </Badge>

                        {isProvisioned && built !== '' && (
                            <span className="text-sm text-muted-foreground">
                                {built}
                            </span>
                        )}
                    </div>

                    {discordState.provision_message && (
                        <p
                            className={
                                discordState.provision_status === 'failed'
                                    ? 'text-sm text-red-600 dark:text-red-500'
                                    : 'text-sm text-muted-foreground'
                            }
                        >
                            {discordState.provision_message}
                        </p>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button
                            onClick={() =>
                                router.post(
                                    provision.url({ game: gameId }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            disabled={
                                !discordState.guild_id ||
                                !discordState.bot_configured ||
                                discordState.provision_in_progress
                            }
                        >
                            {isProvisioned
                                ? 'Reconcile server'
                                : 'Provision server'}
                        </Button>

                        <Button
                            variant="secondary"
                            onClick={() =>
                                router.post(
                                    syncRoles.url({ game: gameId }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            disabled={
                                !discordState.guild_id ||
                                !discordState.bot_configured
                            }
                        >
                            Re-sync everyone's roles
                        </Button>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Reconciling is safe mid-game: it creates what is
                        missing, renames what has drifted, and never deletes
                        anything. Players also get their roles automatically
                        each time they sign in.
                    </p>
                </div>

                {syncs.length > 0 && (
                    <div className="flex flex-col gap-2 border-t pt-4">
                        <h3 className="text-sm font-medium">
                            Role sync
                            {notJoined.length > 0 && (
                                <span className="ml-2 font-normal text-amber-600 dark:text-amber-500">
                                    {notJoined.length} not in the server
                                </span>
                            )}
                            {failed.length > 0 && (
                                <span className="ml-2 font-normal text-red-600 dark:text-red-500">
                                    {failed.length} failed
                                </span>
                            )}
                        </h3>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th className="py-1 pr-4 font-medium">
                                            Player
                                        </th>
                                        <th className="py-1 pr-4 font-medium">
                                            Discord
                                        </th>
                                        <th className="py-1 pr-4 font-medium">
                                            Status
                                        </th>
                                        <th className="py-1 font-medium">
                                            Roles
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {syncs.map((sync) => (
                                        <tr key={sync.id} className="border-t">
                                            <td className="py-1 pr-4">
                                                {sync.user}
                                            </td>
                                            <td className="py-1 pr-4 font-mono text-xs text-muted-foreground">
                                                {sync.discord_username ?? '—'}
                                            </td>
                                            <td
                                                className={`py-1 pr-4 ${STATUS_STYLES[sync.status]}`}
                                            >
                                                {sync.status_label}
                                            </td>
                                            <td className="py-1">
                                                {sync.role_count}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
