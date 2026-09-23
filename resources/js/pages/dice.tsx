import { Head, router } from '@inertiajs/react';
import { Minus, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    DiceFaces,
    poolLabel,
    successLabel,
} from '@/components/dice-roll-result';
import { GameStateNotice } from '@/components/game-state-notice';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { store } from '@/routes/dice';
import type { DiceRoll, DiceSeat, GameSummary } from '@/types/game';

type Props = {
    game: GameSummary | null;
    seats: DiceSeat[];
    rolls: DiceRoll[];
    can_roll: boolean;
    is_control: boolean;
    max_per_size: number;
};

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * Roll some d6s and d8s for Control to read.
 *
 * The dice are thrown on the server, so nobody can decide they won, and the
 * result goes to Control and to nobody else in the game: it is evidence for a
 * ruling, and the ruling is Control's. A 5 or better is a success on either
 * die.
 */
export default function Dice({
    game,
    seats,
    rolls,
    can_roll,
    is_control,
    max_per_size,
}: Props) {
    if (game === null) {
        return (
            <>
                <Head title="Dice" />
                <div className="p-4">
                    <Heading
                        title="Dice"
                        description="No game has been set up yet."
                    />
                </div>
            </>
        );
    }

    const [latest, ...earlier] = rolls;

    return (
        <>
            <Head title="Dice" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Dice"
                    description={
                        is_control
                            ? 'Every roll in the game, because you are Control.'
                            : 'Roll for Control. A 5 or better is a success, and only Control sees the result.'
                    }
                />

                <GameStateNotice game={game} />

                {can_roll ? (
                    <RollForm
                        seats={seats}
                        isControl={is_control}
                        maxPerSize={max_per_size}
                    />
                ) : null}

                {latest === undefined ? (
                    <p className="text-sm text-muted-foreground">
                        Nothing rolled yet.
                    </p>
                ) : (
                    <>
                        <RollCard roll={latest} emphasis />
                        {earlier.length > 0 ? (
                            <section className="flex flex-col gap-3">
                                <h2 className="text-sm font-medium">
                                    Earlier rolls
                                </h2>
                                {earlier.map((roll) => (
                                    <RollCard key={roll.id} roll={roll} />
                                ))}
                            </section>
                        ) : null}
                    </>
                )}
            </div>
        </>
    );
}

function RollForm({
    seats,
    isControl,
    maxPerSize,
}: {
    seats: DiceSeat[];
    isControl: boolean;
    maxPerSize: number;
}) {
    const [characterId, setCharacterId] = useState<number | null>(
        seats[0]?.character_id ?? null,
    );
    const [d6, setD6] = useState(0);
    const [d8, setD8] = useState(0);
    const [purpose, setPurpose] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        router.post(
            store.url(),
            {
                character_id: characterId,
                d6,
                d8,
                purpose: purpose === '' ? null : purpose,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setErrors({});
                    setPurpose('');
                },
                onError: (next) => setErrors(next),
            },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Roll</CardTitle>
                <CardDescription>
                    Say what it is for, so Control knows which ruling it
                    answers.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    {seats.length > 1 || (isControl && seats.length > 0) ? (
                        <div className="grid gap-1">
                            <Label htmlFor="dice-seat">Rolling as</Label>
                            <select
                                id="dice-seat"
                                value={characterId ?? ''}
                                onChange={(event) =>
                                    setCharacterId(
                                        event.target.value === ''
                                            ? null
                                            : Number(event.target.value),
                                    )
                                }
                                className={SELECT_CLASS}
                            >
                                {isControl ? (
                                    <option value="">Control</option>
                                ) : null}
                                {seats.map((seat) => (
                                    <option
                                        key={seat.character_id}
                                        value={seat.character_id}
                                    >
                                        {seat.name} ({seat.role_label})
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.character_id} />
                        </div>
                    ) : null}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <DieCount
                            id="dice-d6"
                            label="d6"
                            value={d6}
                            max={maxPerSize}
                            onChange={setD6}
                        />
                        <DieCount
                            id="dice-d8"
                            label="d8"
                            value={d8}
                            max={maxPerSize}
                            onChange={setD8}
                        />
                    </div>

                    <div className="grid gap-1">
                        <Label htmlFor="dice-purpose">
                            What for (optional)
                        </Label>
                        <Input
                            id="dice-purpose"
                            value={purpose}
                            maxLength={255}
                            onChange={(event) => setPurpose(event.target.value)}
                            placeholder="Talking the guard round"
                        />
                        <InputError message={errors.purpose} />
                    </div>

                    <InputError message={errors.d6 ?? errors.d8} />

                    <div>
                        <Button
                            type="submit"
                            disabled={processing || d6 + d8 === 0}
                        >
                            Roll {d6 + d8 === 0 ? '' : poolLabel({ d6, d8 })}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

/**
 * How many of one die to throw.
 *
 * Stepper buttons beside a plain box, because on a phone the box is the fiddly
 * part. No `max` on the input itself: a browser-side constraint blocks the
 * submit without a word, and the server's refusal is the one that says why.
 */
function DieCount({
    id,
    label,
    value,
    max,
    onChange,
}: {
    id: string;
    label: string;
    value: number;
    max: number;
    onChange: (value: number) => void;
}) {
    const clamp = (next: number) =>
        Math.max(0, Math.min(max, Number.isNaN(next) ? 0 : next));

    return (
        <div className="grid gap-1">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`One fewer ${label}`}
                    disabled={value === 0}
                    onClick={() => onChange(clamp(value - 1))}
                >
                    <Minus />
                </Button>
                <Input
                    id={id}
                    type="number"
                    inputMode="numeric"
                    min={0}
                    value={value}
                    onChange={(event) =>
                        onChange(clamp(parseInt(event.target.value, 10)))
                    }
                    className="w-20 text-center tabular-nums"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`One more ${label}`}
                    disabled={value >= max}
                    onClick={() => onChange(clamp(value + 1))}
                >
                    <Plus />
                </Button>
            </div>
        </div>
    );
}

function RollCard({
    roll,
    emphasis = false,
}: {
    roll: DiceRoll;
    emphasis?: boolean;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className={emphasis ? 'text-xl' : 'text-base'}>
                    {successLabel(roll.successes)}
                </CardTitle>
                <CardDescription>
                    {poolLabel(roll)}
                    {roll.character_name ? ` as ${roll.character_name}` : ''}
                    {roll.purpose ? ` — ${roll.purpose}` : ''}
                    {roll.rolled_at
                        ? ` · ${new Date(roll.rolled_at).toLocaleTimeString()}`
                        : ''}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <DiceFaces roll={roll} />
            </CardContent>
        </Card>
    );
}
