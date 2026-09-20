import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { CharacterLogo } from '@/components/character-logo';
import { CharacterStatsForm } from '@/components/character-stats-form';
import { FactionBadge } from '@/components/faction-badge';
import Heading from '@/components/heading';
import { SubjectTrackerTable } from '@/components/subject-tracker-table';
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
import { removeTag } from '@/routes/control/characters';
import { index, show } from '@/routes/control/games';
import type {
    CharacterSubject,
    GameSummary,
    GameTrackers,
    TrackerAdjustment,
} from '@/types/game';

type Props = {
    game: GameSummary;
    trackers: GameTrackers;
    adjustments: TrackerAdjustment[];
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

/**
 * Every number in the game, in one place.
 *
 * The rulebook defers to Control on almost every page, so all of this has to
 * stay editable mid-game — and it was spread across the panel a card at a time,
 * with a character's four printed stats editable nowhere at all.
 *
 * Two kinds of number sit side by side on the character rows and they behave
 * differently on purpose. Wounds, Tags and Credits are Trackers: clicking one
 * opens the dialog, the change goes through TrackerService, and the ledger at
 * the bottom of this page is what explains it three turns later. Brawn, Hack,
 * Charisma and Body are what a character *is* — nothing in the game spends
 * them, so they are typed in and saved, and no ledger row is written.
 */
export default function ControlGameStats({
    game,
    trackers,
    adjustments,
}: Props) {
    // Control is not the only person moving these numbers — upkeep pays Income
    // in, a Run takes Wounds out, a scored equation pays Research Points — so
    // keep the page fresh without anyone having to reload during a live game.
    usePoll(5000, { only: ['game', 'trackers', 'adjustments'] });

    const { props } = usePage<{ errors: Record<string, string> }>();
    const phase = game.phase;

    return (
        <>
            <Head title={`Stats — ${game.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Stats"
                        description={`${game.name} · every tracker in the game, and the four printed stats on each character sheet.`}
                    />
                    <Button
                        variant="ghost"
                        onClick={() => router.get(show.url({ game: game.id }))}
                    >
                        Back to the panel
                    </Button>
                </div>

                {props.errors?.tags && (
                    <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        {props.errors.tags}
                    </p>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Procatorion</CardTitle>
                        <CardDescription>
                            Stability reaching zero starts the shutdown.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid max-w-xs grid-cols-2 gap-4">
                        {[
                            ['stability', 'Stability'],
                            ['civil_unrest', 'Civil Unrest'],
                        ].map(([key, label]) => (
                            <div key={key} className="rounded-lg border p-3">
                                <p className="text-sm text-muted-foreground">
                                    {label}
                                </p>
                                <TrackerValue
                                    gameId={game.id}
                                    subjectType={trackers.global.subject_type}
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
                        <CardTitle>Corporations</CardTitle>
                        <CardDescription>
                            Income is paid into Credits when Team Time opens. It
                            is the abstraction of a corporation's stock price,
                            so move it as the fiction demands.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SubjectTrackerTable
                            gameId={game.id}
                            subjects={trackers.corporations}
                            columns={CORPORATION_TRACKERS}
                            emptyMessage="No corporations yet."
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Gangs</CardTitle>
                        <CardDescription>
                            Notoriety is the Runner's, so a gang's figure is the
                            total of its members' and is read here rather than
                            edited. Move it on the Runner below and this
                            follows.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {trackers.gangs.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No gangs yet.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4 font-medium">
                                                Name
                                            </th>
                                            <th className="w-24 py-2 pr-4 text-right font-medium">
                                                Runners
                                            </th>
                                            <th className="w-24 py-2 text-right font-medium">
                                                Notoriety
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {trackers.gangs.map((gang) => (
                                            <tr
                                                key={gang.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-2 pr-4">
                                                    <span className="flex items-center gap-2">
                                                        <FactionBadge
                                                            faction={gang}
                                                            size="small"
                                                        />
                                                        {gang.name}
                                                    </span>
                                                </td>
                                                <td className="w-24 py-2 pr-4 text-right font-mono text-muted-foreground tabular-nums">
                                                    {gang.members}
                                                </td>
                                                <td className="w-24 py-2 text-right font-mono tabular-nums">
                                                    {gang.notoriety}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Characters</CardTitle>
                        <CardDescription>
                            Click a number to move it — every change is written
                            to the log below with whatever reason you give it.
                            Notoriety is the Runner's own, and what each gang's
                            total above is made of.
                            <br />
                            Brawn, Hack, Charisma and Body are the character
                            sheet rather than the game's running total, so they
                            are typed in and saved, and nothing is logged.
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
                                        Team
                                    </th>
                                    <th className="w-20 py-2 pr-4 text-right font-medium">
                                        Wounds
                                    </th>
                                    <th className="w-20 py-2 pr-4 text-right font-medium">
                                        Tags
                                    </th>
                                    <th className="w-20 py-2 pr-4 text-right font-medium">
                                        Credits
                                    </th>
                                    <th className="w-20 py-2 pr-4 text-right font-medium">
                                        Notoriety
                                    </th>
                                    <th className="py-2 pr-4 font-medium">
                                        Character sheet
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
                                                    <span>
                                                        {character.name}
                                                        <span className="block text-xs text-muted-foreground">
                                                            {
                                                                character.role_label
                                                            }
                                                        </span>
                                                    </span>
                                                </span>
                                                {character.incapacitated && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="mt-1"
                                                    >
                                                        Incapacitated
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="py-2 pr-4 text-muted-foreground">
                                                {character.team ?? '—'}
                                            </td>
                                            <td className="w-20 py-2 pr-4">
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
                                            <td className="w-20 py-2 pr-4">
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
                                            <td className="w-20 py-2 pr-4">
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
                                            <td className="w-20 py-2 pr-4">
                                                <TrackerValue
                                                    gameId={game.id}
                                                    subjectType={
                                                        character.subject_type
                                                    }
                                                    subjectId={
                                                        character.subject_id
                                                    }
                                                    subjectName={character.name}
                                                    tracker="notoriety"
                                                    trackerLabel="Notoriety"
                                                    value={
                                                        character.values
                                                            .notoriety ?? 0
                                                    }
                                                />
                                            </td>
                                            <td className="py-2 pr-4">
                                                <CharacterStatsForm
                                                    gameId={game.id}
                                                    character={character}
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
                                                        router.post(
                                                            removeTag.url({
                                                                game: game.id,
                                                                character:
                                                                    character.subject_id,
                                                            }),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
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
                            This is how Control answers "why did that number
                            change?" three turns later.
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

ControlGameStats.layout = {
    breadcrumbs: [
        { title: 'Control', href: index() },
        { title: 'Stats', href: index() },
    ],
};
