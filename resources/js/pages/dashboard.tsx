import { Head, Link, usePoll } from '@inertiajs/react';
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
import { dashboard } from '@/routes';
import { index } from '@/routes/control/games';
import type { GameSummary } from '@/types/game';

type PlayerCharacter = {
    id: number;
    name: string;
    role_label: string;
    team: string | null;
    credits: number;
    wounds: number;
    tags: number;
    body: number;
    brawn: number;
    hack: number;
    incapacitated: boolean;
    gang: { name: string; notoriety: number } | null;
    corporation: {
        name: string;
        stock_price: number;
        income: number;
        political_will: number;
    } | null;
};

type Props = {
    game: GameSummary | null;
    characters: PlayerCharacter[];
    isControl: boolean;
};

export default function Dashboard({ game, characters, isControl }: Props) {
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

                {isControl && (
                    <Link
                        href={index()}
                        className="self-start text-sm text-primary underline-offset-4 hover:underline"
                    >
                        Open the Control panel →
                    </Link>
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
                                {character.role_label}
                                {character.team ? ` · ${character.team}` : ''}
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
                                        label="Stock price"
                                        value={
                                            character.corporation.stock_price
                                        }
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
