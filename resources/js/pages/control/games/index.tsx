import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PhaseClock } from '@/components/phase-clock';
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
import { index, show, store } from '@/routes/control/games';
import type { GameSummary } from '@/types/game';

export default function ControlGamesIndex({
    games,
    botConfigured,
}: {
    games: GameSummary[];
    botConfigured: boolean;
}) {
    return (
        <>
            <Head title="Control — Games" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Games"
                    description="Run the turn clock and keep the trackers honest."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>New game</CardTitle>
                        <CardDescription>
                            Phase lengths default to the rulebook's 15 / 15 / 5
                            minutes. Everything here can be changed afterwards.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form {...store.form()} className="flex flex-col gap-4">
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            placeholder="Running Hot — Sheffield"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-3">
                                        {[
                                            [
                                                'setup_seconds',
                                                'Setup (seconds)',
                                                900,
                                            ],
                                            [
                                                'action_seconds',
                                                'Action (seconds)',
                                                900,
                                            ],
                                            [
                                                'team_time_seconds',
                                                'Team Time (seconds)',
                                                300,
                                            ],
                                        ].map(([name, label, value]) => (
                                            <div
                                                key={name as string}
                                                className="grid gap-2"
                                            >
                                                <Label htmlFor={name as string}>
                                                    {label}
                                                </Label>
                                                <Input
                                                    id={name as string}
                                                    name={name as string}
                                                    type="number"
                                                    defaultValue={
                                                        value as number
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            name as keyof typeof errors
                                                        ]
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </div>

                                    <label className="flex items-start gap-2">
                                        <input
                                            type="checkbox"
                                            name="connect_discord"
                                            value="1"
                                            defaultChecked={botConfigured}
                                            disabled={!botConfigured}
                                            className="mt-1"
                                        />
                                        <span className="grid gap-1">
                                            <span className="text-sm font-medium">
                                                Set up its Discord server next
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {botConfigured
                                                    ? 'Takes you to Discord to pick a server, then builds the roles and channels and creates the announcement webhook.'
                                                    : 'Needs DISCORD_BOT_TOKEN. Without it you can paste a webhook below instead.'}
                                            </span>
                                        </span>
                                    </label>

                                    <details className="text-sm">
                                        <summary className="cursor-pointer text-muted-foreground">
                                            Announcement webhook (optional)
                                        </summary>
                                        <div className="grid gap-2 pt-3">
                                            <p className="text-muted-foreground">
                                                Only needed if the application
                                                is not building this game's
                                                Discord server. Provisioning
                                                creates one otherwise.
                                            </p>
                                            <Input
                                                id="discord_webhook_url"
                                                name="discord_webhook_url"
                                                type="url"
                                                placeholder="https://discord.com/api/webhooks/…"
                                                className="font-mono text-xs"
                                            />
                                            <InputError
                                                message={
                                                    errors.discord_webhook_url
                                                }
                                            />
                                        </div>
                                    </details>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="self-start"
                                    >
                                        Create game
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2">
                    {games.map((game) => (
                        <Card key={game.id}>
                            <CardHeader>
                                <CardTitle>
                                    <Link
                                        href={show(game.id)}
                                        className="hover:underline"
                                    >
                                        {game.name}
                                    </Link>
                                </CardTitle>
                                <CardDescription>
                                    {game.status_label}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {game.phase ? (
                                    <PhaseClock phase={game.phase} />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Not started.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                    {games.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No games yet. Create one above.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

ControlGamesIndex.layout = {
    breadcrumbs: [{ title: 'Control', href: index() }],
};
