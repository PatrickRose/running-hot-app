import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
import { destroy, store } from '@/routes/control/control-members';
import type { ControlMember } from '@/types/game';

/**
 * Who is running this game.
 *
 * A seat is named by Discord handle and claimed the first time that person
 * signs in, so the badge shows the account once there is one and the handle it
 * is still waiting on before that.
 */
export function ControlTeam({
    gameId,
    members,
}: {
    gameId: number;
    members: ControlMember[];
}) {
    const form = useForm({ discord_username: '' });

    const add = (event: FormEvent) => {
        event.preventDefault();

        form.post(store.url({ game: gameId }), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Control team</CardTitle>
                <CardDescription>
                    Everyone here runs this game: the clock, the trackers and
                    the Facilities, and they hold the Control role in its
                    Discord server. Add them by Discord handle and they become
                    Control the next time they sign in — no console, and nothing
                    to do with any other game.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {members.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nobody yet. Only accounts granted Control from the
                        console can run this game.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {members.map((member) => (
                            <li
                                key={member.id}
                                className="flex flex-wrap items-center justify-between gap-2 border-b pb-2 text-sm last:border-0 last:pb-0"
                            >
                                <span className="flex flex-wrap items-center gap-2">
                                    {member.claimed_by ? (
                                        <Badge variant="secondary">
                                            {member.claimed_by}
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            Not signed in yet
                                        </Badge>
                                    )}
                                    <span className="text-muted-foreground">
                                        @{member.discord_username}
                                    </span>
                                    {member.is_you && (
                                        <span className="text-muted-foreground">
                                            (you)
                                        </span>
                                    )}
                                </span>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        if (
                                            member.is_you &&
                                            !window.confirm(
                                                'Remove your own seat? You will lose the Control panel for this game.',
                                            )
                                        ) {
                                            return;
                                        }

                                        router.delete(
                                            destroy.url({
                                                game: gameId,
                                                controlMember: member.id,
                                            }),
                                            { preserveScroll: true },
                                        );
                                    }}
                                >
                                    Remove
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}

                <form
                    onSubmit={add}
                    className="flex flex-wrap items-start gap-2"
                >
                    <div className="flex flex-col gap-1">
                        <Input
                            value={form.data.discord_username}
                            onChange={(event) =>
                                form.setData(
                                    'discord_username',
                                    event.target.value,
                                )
                            }
                            placeholder="discord handle"
                            aria-label="Discord handle of a Control member"
                            className="h-8 w-56"
                        />
                        {form.errors.discord_username && (
                            <p className="text-sm text-destructive">
                                {form.errors.discord_username}
                            </p>
                        )}
                    </div>
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        disabled={
                            form.processing ||
                            form.data.discord_username.trim() === ''
                        }
                    >
                        Add to Control
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
