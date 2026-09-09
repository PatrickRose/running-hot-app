import { router } from '@inertiajs/react';
import { useState } from 'react';
import { GameIcon } from '@/components/game-icon';
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
import { score } from '@/routes/research/equations';
import type {
    ResearchEquationSummary,
    ResearchSuitSummary,
} from '@/types/game';

/**
 * The equations you have played and not yet taken the points for
 * (rulebook 3.2.1).
 *
 * A list rather than a step in playing, because the rulebook is explicit that
 * it should be: "Scoring can and should be done while other players are taking
 * their turns". So an equation is played, the turn passes, and this is where
 * the arithmetic waits — however many turns it waits for.
 *
 * Three answers per equation, and they are the rulebook's three. Which side you
 * take your points from, since you are paid that side's total. Which of that
 * side's suits, which matters only when the set was all wild and could be any of
 * the four. And how a balanced equation's bonus is split, which may go across
 * any suits the equation used.
 */
export function ResearchScoring({
    equations,
    suits,
    canScore,
}: {
    equations: ResearchEquationSummary[];
    suits: ResearchSuitSummary[];
    canScore: boolean;
}) {
    if (equations.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Waiting to be scored</CardTitle>
                <CardDescription>
                    Take these whenever you like — the table does not wait for
                    you, and neither do these.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {equations.map((equation) => (
                    <ScoreForm
                        key={equation.id}
                        equation={equation}
                        suits={suits}
                        canScore={canScore}
                    />
                ))}
            </CardContent>
        </Card>
    );
}

function ScoreForm({
    equation,
    suits,
    canScore,
}: {
    equation: ResearchEquationSummary;
    suits: ResearchSuitSummary[];
    canScore: boolean;
}) {
    const [side, setSide] = useState<'left' | 'right'>(
        equation.left_sum >= equation.right_sum ? 'left' : 'right',
    );

    const available =
        side === 'left' ? equation.left_suits : equation.right_suits;
    const [suit, setSuit] = useState<string>(available[0] ?? '');
    const [bonus, setBonus] = useState<Record<string, string>>(() =>
        openingBonus(equation),
    );

    const chooseSide = (next: 'left' | 'right') => {
        setSide(next);

        const nextSuits =
            next === 'left' ? equation.left_suits : equation.right_suits;

        if (!nextSuits.includes(suit)) {
            setSuit(nextSuits[0] ?? '');
        }
    };

    const allocated = Object.values(bonus).reduce(
        (sum, value) => sum + (Number(value) || 0),
        0,
    );

    const submit = () => {
        const allocation: Record<string, number> = {};

        for (const [key, value] of Object.entries(bonus)) {
            allocation[key] = Number(value) || 0;
        }

        router.post(
            score.url({ equation: equation.id }),
            { side, suit, bonus: allocation },
            { preserveScroll: true },
        );
    };

    const bonusSuits = suits.filter((entry) =>
        equation.bonus_suits.includes(entry.value),
    );

    return (
        <div className="rounded-md border p-4">
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-mono text-sm">
                    {equation.left_label} / {equation.right_label}
                </span>
                {equation.balanced ? (
                    <Badge>Balanced · {equation.bonus} bonus</Badge>
                ) : (
                    <Badge variant="secondary">
                        {equation.left_sum} against {equation.right_sum}
                    </Badge>
                )}
                <span className="text-xs text-muted-foreground">
                    #{equation.id}
                    {equation.turn !== null && ` · turn ${equation.turn}`}
                </span>
            </div>

            <fieldset className="mt-3" disabled={!canScore}>
                <legend className="text-sm font-medium">
                    Take your points from
                </legend>
                <div className="mt-2 flex flex-wrap gap-2">
                    <SideButton
                        label={`${equation.left_label} — ${equation.left_sum}`}
                        active={side === 'left'}
                        onClick={() => chooseSide('left')}
                    />
                    <SideButton
                        label={`${equation.right_label} — ${equation.right_sum}`}
                        active={side === 'right'}
                        onClick={() => chooseSide('right')}
                    />
                </div>

                {available.length > 1 && (
                    <div className="mt-3">
                        <p className="text-sm font-medium">
                            As which suit?
                            <span className="ml-2 font-normal text-muted-foreground">
                                that set is all wild, so it may be any of them
                            </span>
                        </p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {suits
                                .filter((entry) =>
                                    available.includes(entry.value),
                                )
                                .map((entry) => (
                                    <SideButton
                                        key={entry.value}
                                        label={entry.label}
                                        glyph={entry.glyph}
                                        active={suit === entry.value}
                                        onClick={() => setSuit(entry.value)}
                                    />
                                ))}
                        </div>
                    </div>
                )}

                {equation.bonus > 0 && (
                    <div className="mt-3">
                        <p className="text-sm font-medium">
                            Split the {equation.bonus} bonus point
                            {equation.bonus === 1 ? '' : 's'}
                            <span className="ml-2 font-normal text-muted-foreground">
                                across any suits this equation used
                            </span>
                        </p>
                        <div className="mt-2 flex flex-wrap gap-3">
                            {bonusSuits.map((entry) => (
                                <div key={entry.value} className="grid gap-1">
                                    <Label
                                        htmlFor={`bonus-${equation.id}-${entry.value}`}
                                        className="flex items-center gap-1 text-xs"
                                    >
                                        <GameIcon
                                            glyph={entry.glyph}
                                            label={entry.label}
                                        />
                                        <span aria-hidden="true">
                                            {entry.label}
                                        </span>
                                    </Label>
                                    <Input
                                        id={`bonus-${equation.id}-${entry.value}`}
                                        type="number"
                                        min={0}
                                        className="w-20"
                                        value={bonus[entry.value] ?? '0'}
                                        onChange={(event) =>
                                            setBonus((current) => ({
                                                ...current,
                                                [entry.value]:
                                                    event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {allocated} of {equation.bonus} allocated.
                        </p>
                    </div>
                )}

                <Button
                    type="button"
                    className="mt-4"
                    onClick={submit}
                    disabled={suit === '' || allocated !== equation.bonus}
                >
                    Take the points
                </Button>
            </fieldset>
        </div>
    );
}

/**
 * The whole bonus in the first suit the equation used, which is the answer most
 * players want and the one that needs no arithmetic to correct.
 */
function openingBonus(
    equation: ResearchEquationSummary,
): Record<string, string> {
    const allocation: Record<string, string> = {};

    equation.bonus_suits.forEach((suit, index) => {
        allocation[suit] = index === 0 ? String(equation.bonus) : '0';
    });

    return allocation;
}

function SideButton({
    label,
    glyph,
    active,
    onClick,
}: {
    label: string;
    glyph?: string;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={
                active
                    ? 'flex items-center gap-1.5 rounded-md border border-primary bg-primary/5 px-3 py-1.5 text-sm'
                    : 'flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm hover:border-primary/60'
            }
        >
            {glyph && <GameIcon glyph={glyph} label={label} />}
            <span aria-hidden={glyph ? 'true' : undefined}>{label}</span>
        </button>
    );
}
