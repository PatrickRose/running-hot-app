import { Head, Link, usePoll } from '@inertiajs/react';
import { CharacterLogo } from '@/components/character-logo';
import { FactionBadge } from '@/components/faction-badge';
import Heading from '@/components/heading';
import { PhaseClock } from '@/components/phase-clock';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard, facilities } from '@/routes';
import { index } from '@/routes/control/games';
import type { Faction, GameSummary } from '@/types/game';

type PlayerCharacter = {
    id: number;
    name: string;
    role_label: string;
    team: string | null;
    /** Set only for a character that is an organisation; see CharacterLogo. */
    logo_path: string | null;
    credits: number;
    wounds: number;
    tags: number;
    body: number;
    brawn: number;
    hack: number;
    incapacitated: boolean;
    gang: (Faction & { notoriety: number }) | null;
    corporation:
        | (Faction & {
              income: number;
              political_will: number;
          })
        | null;
};

/**
 * A character's role and team, with the team's badge against it.
 *
 * Gang before Corporation, matching how the server picks which of the two fills
 * in `team`. A character Control has not put on a team yet gets the role alone.
 */
function CharacterTeam({ character }: { character: PlayerCharacter }) {
    const faction: Faction | null = character.gang ?? character.corporation;

    return (
        <span className="flex items-center gap-2">
            {faction ? (
                <FactionBadge faction={faction} size="small" />
            ) : (
                // A Press outlet or HM Government is on no team and has a logo
                // of its own; a Freelancer has neither and gets nothing.
                <CharacterLogo logoPath={character.logo_path} />
            )}
            <span>
                {character.role_label}
                {character.team ? ` · ${character.team}` : ''}
            </span>
        </span>
    );
}

type DiscordJoin = {
    invite_url: string | null;
    game_name: string;
};

type Props = {
    game: GameSummary | null;
    characters: PlayerCharacter[];
    isControl: boolean;
    discordJoin: DiscordJoin | null;
};

export default function Dashboard({
    game,
    characters,
    isControl,
    discordJoin,
}: Props) {
    usePoll(5000, { only: ['game', 'characters'] });

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title={game?.name ?? 'Running Hot'}
                    description={
                        game
                            ? 'The clock below is the one that counts.'
                            : 'No game is running right now.'
                    }
                />

                <div className="flex flex-wrap gap-4">
                    {game && (
                        <Link
                            href={facilities()}
                            className="text-sm text-primary underline-offset-4 hover:underline"
                        >
                            See the Facilities →
                        </Link>
                    )}

                    {isControl && (
                        <Link
                            href={index()}
                            className="text-sm text-primary underline-offset-4 hover:underline"
                        >
                            Open the Control panel →
                        </Link>
                    )}
                </div>

                {discordJoin && (
                    <Card className="border-amber-500/50">
                        <CardHeader>
                            <CardTitle>Join the Discord server</CardTitle>
                            <CardDescription>
                                The game is played in Discord, and you are not
                                in the server for {discordJoin.game_name} yet.
                                Until you join you will not see any of your
                                team's channels.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {discordJoin.invite_url ? (
                                <a
                                    href={discordJoin.invite_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-sm text-primary underline-offset-4 hover:underline"
                                >
                                    Open the invite →
                                </a>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Ask Control for an invite link. Your roles
                                    are handed out automatically the next time
                                    you sign in after joining.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {game?.phase && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Current phase</CardTitle>
                            <CardDescription>
                                Procatorion stability {game.stability} · civil
                                unrest {game.civil_unrest}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PhaseClock phase={game.phase} size="large" />
                        </CardContent>
                    </Card>
                )}

                {characters.map((character) => (
                    <Card key={character.id}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {character.name}
                                {character.incapacitated && (
                                    <Badge variant="destructive">
                                        Incapacitated
                                    </Badge>
                                )}
                            </CardTitle>
                            <CardDescription>
                                <CharacterTeam character={character} />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <Stat label="Credits" value={character.credits} />
                            <Stat
                                label="Wounds"
                                value={`${character.wounds} / ${character.body}`}
                                warn={character.wounds > 0}
                            />
                            <Stat
                                label="Tags"
                                value={character.tags}
                                warn={character.tags > 0}
                            />
                            <Stat label="Brawn" value={character.brawn} />
                            <Stat label="Hack" value={character.hack} />
                            {character.gang && (
                                <Stat
                                    label="Gang notoriety"
                                    value={character.gang.notoriety}
                                />
                            )}
                            {character.corporation && (
                                <>
                                    <Stat
                                        label="Income"
                                        value={character.corporation.income}
                                    />
                                    <Stat
                                        label="Political will"
                                        value={
                                            character.corporation.political_will
                                        }
                                    />
                                </>
                            )}
                        </CardContent>
                    </Card>
                ))}

                {game !== null && characters.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        You have no character in this game yet. Speak to
                        Control.
                    </p>
                )}
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    warn = false,
}: {
    label: string;
    value: string | number;
    warn?: boolean;
}) {
    return (
        <div className="rounded-lg border p-3">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p
                className={`font-mono text-2xl tabular-nums ${
                    warn ? 'text-amber-600 dark:text-amber-500' : ''
                }`}
            >
                {value}
            </p>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
