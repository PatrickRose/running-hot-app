import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { CharacterLogo } from '@/components/character-logo';
import { ControlTeam } from '@/components/control-team';
import { DiscordHandle } from '@/components/discord-handle';
import { FactionBadge } from '@/components/faction-badge';
import { GameDiscordPanel } from '@/components/game-discord';
import { GameWebhook } from '@/components/game-webhook';
import Heading from '@/components/heading';
import { PhaseClock } from '@/components/phase-clock';
import { TrackerValue } from '@/components/tracker-value';
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
import { removeTag } from '@/routes/control/characters';
import { index as facilitiesIndex } from '@/routes/control/facilities';
import { finish, index } from '@/routes/control/games';
import { advance, extend, pause, resume, start } from '@/routes/control/phase';
import { index as researchIndex } from '@/routes/control/research';
import type {
    CharacterSubject,
    ControlMember,
    DiscordBotGuilds,
    DiscordMemberSync,
    GameSummary,
    GameTrackers,
    NamedSubject,
    TrackerAdjustment,
} from '@/types/game';

type Props = {
    game: GameSummary;
    trackers: GameTrackers;
    adjustments: TrackerAdjustment[];
    controlMembers: ControlMember[];
    discordSyncs: DiscordMemberSync[];
    discordGuilds?: DiscordBotGuilds;
};

const CORPORATION_TRACKERS: Array<[string, string]> = [
    ['income', 'Income'],
    ['political_will', 'Political Will'],
    ['corporation_credits', 'Credits'],
    // The four Research Point suits (rulebook 3.2.1). They are Trackers like
    // the three above, so Control moves them from the same dialog and every
    // change lands in the same ledger — which is what makes a research score
    // overridable without a back door.
    ['research_cog', 'Cog'],
    ['research_brain', 'Brain'],
    ['research_leaf', 'Leaf'],
    ['research_maths', 'Maths'],
];

export default function ControlGameShow({
    game,
    trackers,
    adjustments,
    controlMembers,
    discordSyncs,
    discordGuilds,
}: Props) {
    // Control is not the only person moving these numbers, so keep the panel
    // fresh without anyone having to reload during a live game. Provisioning
    // runs on the queue, so this poll is also how its progress arrives.
    usePoll(5000, {
        only: [
            'game',
            'trackers',
            'adjustments',
            'controlMembers',
            'discordSyncs',
        ],
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
                {props.errors?.tags && (
                    <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        {props.errors.tags}
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

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Procatorion</CardTitle>
                            <CardDescription>
                                Stability reaching zero starts the shutdown.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4">
                            {[
                                ['stability', 'Stability'],
                                ['civil_unrest', 'Civil Unrest'],
                            ].map(([key, label]) => (
                                <div
                                    key={key}
                                    className="rounded-lg border p-3"
                                >
                                    <p className="text-sm text-muted-foreground">
                                        {label}
                                    </p>
                                    <TrackerValue
                                        gameId={game.id}
                                        subjectType={
                                            trackers.global.subject_type
                                        }
                                        subjectId={trackers.global.subject_id}
                                        subjectName="Procatorion"
                                        tracker={key}
                                        trackerLabel={label}
                                        value={trackers.global.values[key] ?? 0}
                                    />
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Gangs</CardTitle>
                            <CardDescription>
                                Notoriety per gang.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SubjectTable
                                gameId={game.id}
                                subjects={trackers.gangs}
                                columns={[['notoriety', 'Notoriety']]}
                                emptyMessage="No gangs yet."
                            />
                        </CardContent>
                    </Card>
                </div>

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
                        <CardTitle>Corporations</CardTitle>
                        <CardDescription>
                            Income is paid into Credits when Team Time opens. It
                            is the abstraction of a corporation's stock price,
                            so move it as the fiction demands.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SubjectTable
                            gameId={game.id}
                            subjects={trackers.corporations}
                            columns={CORPORATION_TRACKERS}
                            emptyMessage="No corporations yet."
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Characters</CardTitle>
                        <CardDescription>
                            Put a player's Discord handle against their
                            character and they will be signed in to it
                            automatically. Once claimed, the link survives a
                            Discord rename.
                            <br />
                            Runners and Freelancers heal one Wound automatically
                            each Team Time. Tags cost 3 Credits to buy off.
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
                                    <th className="py-2 pr-4 font-medium">
                                        Player
                                    </th>
                                    <th className="py-2 pr-4 text-right font-medium">
                                        Wounds
                                    </th>
                                    <th className="py-2 pr-4 text-right font-medium">
                                        Tags
                                    </th>
                                    <th className="py-2 pr-4 text-right font-medium">
                                        Credits
                                    </th>
                                    <th className="py-2 font-medium" />
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
                                            <td className="py-2 pr-4">
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
                                            <td className="py-2 pr-4">
                                                <TrackerValue
                                                    gameId={game.id}
                                                    subjectType={
                                                        character.subject_type
                                                    }
                                                    subjectId={
                                                        character.subject_id
                                                    }
                                                    subjectName={character.name}
                                                    tracker="wounds"
                                                    trackerLabel={`Wounds (Body ${character.body})`}
                                                    value={
                                                        character.values
                                                            .wounds ?? 0
                                                    }
                                                    tone="warn"
                                                />
                                            </td>
                                            <td className="py-2 pr-4">
                                                <TrackerValue
                                                    gameId={game.id}
                                                    subjectType={
                                                        character.subject_type
                                                    }
                                                    subjectId={
                                                        character.subject_id
                                                    }
                                                    subjectName={character.name}
                                                    tracker="tags"
                                                    trackerLabel="Tags"
                                                    value={
                                                        character.values.tags ??
                                                        0
                                                    }
                                                    tone="warn"
                                                />
                                            </td>
                                            <td className="py-2 pr-4">
                                                <TrackerValue
                                                    gameId={game.id}
                                                    subjectType={
                                                        character.subject_type
                                                    }
                                                    subjectId={
                                                        character.subject_id
                                                    }
                                                    subjectName={character.name}
                                                    tracker="character_credits"
                                                    trackerLabel="Credits"
                                                    value={
                                                        character.values
                                                            .character_credits ??
                                                        0
                                                    }
                                                />
                                            </td>
                                            <td className="py-2 text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        (character.values
                                                            .tags ?? 0) < 1 ||
                                                        (character.values
                                                            .character_credits ??
                                                            0) < 3 ||
                                                        phase?.type !==
                                                            'team_time'
                                                    }
                                                    onClick={() =>
                                                        post(
                                                            removeTag.url({
                                                                game: game.id,
                                                                character:
                                                                    character.subject_id,
                                                            }),
                                                        )
                                                    }
                                                >
                                                    Buy off Tag (3cr)
                                                </Button>
                                            </td>
                                        </tr>
                                    ),
                                )}
                                {trackers.characters.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
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

                <Card>
                    <CardHeader>
                        <CardTitle>Game log</CardTitle>
                        <CardDescription>
                            The last {adjustments.length} tracker movements.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">
                                        Subject
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Tracker
                                    </th>
                                    <th className="py-2 pr-4 text-right font-medium">
                                        Change
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Reason
                                    </th>
                                    <th className="py-2 font-medium">By</th>
                                </tr>
                            </thead>
                            <tbody>
                                {adjustments.map((adjustment) => (
                                    <tr
                                        key={adjustment.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-2 pr-4">
                                            {adjustment.subject}
                                        </td>
                                        <td className="py-2 pr-4 text-muted-foreground">
                                            {adjustment.tracker_label}
                                        </td>
                                        <td className="py-2 pr-4 text-right font-mono tabular-nums">
                                            {adjustment.value_before} →{' '}
                                            {adjustment.value_after}
                                        </td>
                                        <td className="py-2 pr-4 text-muted-foreground">
                                            {adjustment.reason ?? '—'}
                                        </td>
                                        <td className="py-2 text-muted-foreground">
                                            {adjustment.automated
                                                ? 'Upkeep'
                                                : (adjustment.actor ?? '—')}
                                        </td>
                                    </tr>
                                ))}
                                {adjustments.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="py-4 text-muted-foreground"
                                        >
                                            Nothing has moved yet.
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

function SubjectTable({
    gameId,
    subjects,
    columns,
    emptyMessage,
}: {
    gameId: number;
    subjects: NamedSubject[];
    columns: Array<[string, string]>;
    emptyMessage: string;
}) {
    if (subjects.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyMessage}</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-2 pr-4 font-medium">Name</th>
                        {columns.map(([key, label]) => (
                            <th
                                key={key}
                                className="py-2 pr-4 text-right font-medium"
                            >
                                {label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {subjects.map((subject) => (
                        <tr
                            key={subject.subject_id}
                            className="border-b last:border-0"
                        >
                            <td className="py-2 pr-4">
                                <span className="flex items-center gap-2">
                                    <FactionBadge
                                        faction={subject}
                                        size="small"
                                    />
                                    {subject.name}
                                </span>
                            </td>
                            {columns.map(([key, label]) => (
                                <td key={key} className="py-2 pr-4">
                                    <TrackerValue
                                        gameId={gameId}
                                        subjectType={subject.subject_type}
                                        subjectId={subject.subject_id}
                                        subjectName={subject.name}
                                        tracker={key}
                                        trackerLabel={label}
                                        value={subject.values[key] ?? 0}
                                    />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

ControlGameShow.layout = {
    breadcrumbs: [
        { title: 'Control', href: index() },
        { title: 'Game', href: index() },
    ],
};
