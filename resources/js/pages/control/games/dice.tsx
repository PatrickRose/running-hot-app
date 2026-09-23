import { Head, router, usePoll } from '@inertiajs/react';
import {
    DiceFaces,
    poolLabel,
    successLabel,
} from '@/components/dice-roll-result';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show } from '@/routes/control/games';
import type { ControlGameSummary, DiceRoll } from '@/types/game';

type Props = {
    game: ControlGameSummary;
    rolls: DiceRoll[];
};

/**
 * Every roll players have made for Control, newest first.
 *
 * Polls, because the whole point is that a player rolls and Control reads it
 * without anybody reloading. Read-only: what a roll earns is a ruling, and a
 * ruling moves numbers through the tracker controls like any other.
 */
export default function ControlDice({ game, rolls }: Props) {
    usePoll(5000, { only: ['rolls'] });

    return (
        <>
            <Head title={`Dice — ${game.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Dice rolls"
                        description="What players have rolled for you. A 5 or better is a success on either die."
                    />
                    <Button
                        variant="ghost"
                        onClick={() => router.get(show.url({ game: game.id }))}
                    >
                        Back to {game.name}
                    </Button>
                </div>

                {rolls.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nobody has rolled yet.
                    </p>
                ) : (
                    rolls.map((roll) => (
                        <Card key={roll.id}>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {roll.character_name ??
                                        roll.user_name ??
                                        'Somebody'}
                                    : {successLabel(roll.successes)}
                                </CardTitle>
                                <CardDescription>
                                    {poolLabel(roll)}
                                    {roll.purpose ? ` — ${roll.purpose}` : ''}
                                    {roll.character_name && roll.user_name
                                        ? ` · rolled by ${roll.user_name}`
                                        : ''}
                                    {roll.rolled_at
                                        ? ` · ${new Date(roll.rolled_at).toLocaleTimeString()}`
                                        : ''}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <DiceFaces roll={roll} />
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </>
    );
}
