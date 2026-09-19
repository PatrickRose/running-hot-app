import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { CharacterLogo } from '@/components/character-logo';
import { ControlTeam } from '@/components/control-team';
import { DiscordHandle } from '@/components/discord-handle';
import { GameDiscordPanel } from '@/components/game-discord';
import { GameWebhook } from '@/components/game-webhook';
import Heading from '@/components/heading';
import { PhaseClock } from '@/components/phase-clock';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as cardsIndex } from '@/routes/control/cards';
import { index as councilIndex } from '@/routes/control/council';
import { index as facilitiesIndex } from '@/routes/control/facilities';
import { finish, index } from '@/routes/control/games';
import { advance, extend, pause, resume, start } from '@/routes/control/phase';
import { index as researchIndex } from '@/routes/control/research';
import { index as shopIndex } from '@/routes/control/shop';
import { index as statsIndex } from '@/routes/control/stats';
import type {
    CharacterSubject,
    ControlMember,
    DiscordBotGuilds,
    DiscordMemberSync,
    GameSummary,
    GameTrackers,
} from '@/types/game';

type Props = {
    game: GameSummary;
    trackers: GameTrackers;
    controlMembers: ControlMember[];
    discordSyncs: DiscordMemberSync[];
    discordGuilds?: DiscordBotGuilds;
};

export default function ControlGameShow({
    game,
    trackers,
    controlMembers,
    discordSyncs,
    discordGuilds,
}: Props) {
    // Control is not the only person moving these numbers, so keep the panel
    // fresh without anyone having to reload during a live game. Provisioning
    // runs on the queue, so this poll is also how its progress arrives.
    usePoll(5000, {
        only: ['game', 'trackers', 'controlMembers', 'discordSyncs'],
    });

    const { props } = usePage<{ errors: Record<string, string> }>();
    const phase = game.phase;

    const post = (url: string, data: Record<string, number> = {}) =>
        router.post(url, data, { preserveScroll: true });

    return (
        <>
            <Head title={`Control — ${game.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={game.name}
                        description={`${game.status_label} · ${
                            game.auto_advance
                                ? 'Phases roll over automatically'
                                : 'Manual phase advance'
                        }`}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(statsIndex.url({ game: game.id }))
                            }
                        >
                            Stats
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(
                                    facilitiesIndex.url({ game: game.id }),
                                )
                            }
                        >
                            Facility Defence
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(councilIndex.url({ game: game.id }))
                            }
                        >
                            Council
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(researchIndex.url({ game: game.id }))
                            }
                        >
                            Research
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(cardsIndex.url({ game: game.id }))
                            }
                        >
                            Card lists
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(shopIndex.url({ game: game.id }))
                            }
                        >
                            Shop
                        </Button>
                        <Button
                            variant="ghost"
                            onClick={() => router.get(index().url)}
                        >
                            All games
                        </Button>
                    </div>
                </div>

                {props.errors?.phase && (
                    <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        {props.errors.phase}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Turn clock</CardTitle>
                        <CardDescription>
                            Phase changes are announced in this game's Discord
                            channel.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-end justify-between gap-6">
                        {phase ? (
                            <PhaseClock phase={phase} size="large" />
                        ) : (
                            <p className="text-muted-foreground">
                                The game has not started.
                            </p>
                        )}

                        <div className="flex flex-wrap gap-2">
                            {phase === null ? (
                                <Button
                                    onClick={() =>
                                        post(start.url({ game: game.id }))
                                    }
                                >
                                    Start turn 1
                                </Button>
                            ) : (
                                <>
                                    {phase.status === 'running' ? (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                post(
                                                    pause.url({
                                                        game: game.id,
                                                    }),
                                                )
                                            }
                                        >
                                            Pause
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                post(
                                                    resume.url({
                                                        game: game.id,
                                                    }),
                                                )
                                            }
                                        >
                                            Resume
                                        </Button>
                                    )}
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            post(
                                                extend.url({ game: game.id }),
                                                { seconds: 60 },
                                            )
                                        }
                                    >
                                        +1 min
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            post(
                                                extend.url({ game: game.id }),
                                                { seconds: 300 },
                                            )
                                        }
                                    >
                                        +5 min
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            post(
                                                extend.url({ game: game.id }),
                                                { seconds: -60 },
                                            )
                                        }
                                    >
                                        −1 min
                                    </Button>
                                    <Button
                                        onClick={() =>
                                            post(advance.url({ game: game.id }))
                                        }
                                    >
                                        End phase
                                    </Button>
                                </>
                            )}
                            {game.status !== 'finished' && (
                                <Button
                                    variant="destructive"
                                    onClick={() =>
                                        post(finish.url({ game: game.id }))
                                    }
                                >
                                    Finish game
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Announcements</CardTitle>
                        <CardDescription>
                            Each game announces to its own channel, so there is
                            no global default to post to by mistake.
                            Provisioning the server below fills this in.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <GameWebhook
                            gameId={game.id}
                            url={game.discord_webhook_url}
                        />
                    </CardContent>
                </Card>

                <ControlTeam gameId={game.id} members={controlMembers} />

                <GameDiscordPanel
                    gameId={game.id}
                    gameName={game.name}
                    discordState={game.discord}
                    syncs={discordSyncs}
                    botGuilds={discordGuilds}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Characters</CardTitle>
                        <CardDescription>
                            Put a player's Discord handle against their
                            character and they will be signed in to it
                            automatically. Once claimed, the link survives a
                            Discord rename.
                            <br />
                            Their numbers — Wounds, Tags, Credits and the four
                            stats on the character sheet — are on the Stats
                            screen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">
                                        Name
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Role
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Team
                                    </th>
                                    <th className="py-2 font-medium">Player</th>
                                </tr>
                            </thead>
                            <tbody>
                                {trackers.characters.map(
                                    (character: CharacterSubject) => (
                                        <tr
                                            key={character.subject_id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2 pr-4">
                                                <span className="flex items-center gap-2">
                                                    <CharacterLogo
                                                        logoPath={
                                                            character.logo_path
                                                        }
                                                    />
                                                    {character.name}
                                                </span>
                                                {character.incapacitated && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="ml-2"
                                                    >
                                                        Incapacitated
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="py-2 pr-4 text-muted-foreground">
                                                {character.role_label}
                                            </td>
                                            <td className="py-2 pr-4 text-muted-foreground">
                                                {character.team ?? '—'}
                                            </td>
                                            <td className="py-2">
                                                <DiscordHandle
                                                    gameId={game.id}
                                                    characterId={
                                                        character.subject_id
                                                    }
                                                    characterName={
                                                        character.name
                                                    }
                                                    handle={
                                                        character.discord_username
                                                    }
                                                    claimedBy={
                                                        character.claimed_by
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    ),
                                )}
                                {trackers.characters.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="py-4 text-muted-foreground"
                                        >
                                            No characters yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ControlGameShow.layout = {
    breadcrumbs: [
        { title: 'Control', href: index() },
        { title: 'Game', href: index() },
    ],
};
