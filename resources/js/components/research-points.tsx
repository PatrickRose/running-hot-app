import { Form } from '@inertiajs/react';
import { GameIcon } from '@/components/game-icon';
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
import { transfer } from '@/routes/research/points';
import type { Faction, ResearchSuitSummary } from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * A Corporation's Research Points, and the way they leave it (rulebook 3.2.1,
 * 3.2.5).
 *
 * Four totals, because Research Points are four currencies rather than one — a
 * technology is priced across all four, and having twenty Cog does not help
 * with a Brain cost. The rulebook never names the suits in its body text and
 * prints them as icons, so this does the same, with the name always beside the
 * icon for anyone who cannot see it.
 *
 * Trading is one-way and asks nobody's permission, which is what 3.2.5
 * describes: players trade "however they wish ... by passing over the requisite
 * tokens". What came back the other way — Credits, a favour, another suit — is
 * settled at the table, and the application records only the tokens moving.
 */
export function ResearchPoints({
    points,
    suits,
    corporations,
    canTrade,
}: {
    points: Record<string, number>;
    suits: ResearchSuitSummary[];
    corporations: Array<Faction & { id: number; is_yours: boolean }>;
    canTrade: boolean;
}) {
    const others = corporations.filter((corporation) => !corporation.is_yours);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Research Points</CardTitle>
                <CardDescription>
                    Earned at the research table during the Action phase, spent
                    on the tree during Setup. How many you have is semi-secret —
                    nobody else's page shows this.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {suits.map((suit) => (
                        <div
                            key={suit.value}
                            className="rounded-md border px-3 py-2"
                        >
                            <dt className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                <GameIcon
                                    glyph={suit.glyph}
                                    label={suit.label}
                                />
                                <span aria-hidden="true">{suit.label}</span>
                            </dt>
                            <dd className="font-mono text-2xl tabular-nums">
                                {points[suit.value] ?? 0}
                            </dd>
                        </div>
                    ))}
                </dl>

                {canTrade && others.length > 0 && (
                    <Form
                        {...transfer.form()}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="grid gap-3 border-t pt-4 sm:grid-cols-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="trade-corporation">
                                        Hand points to
                                    </Label>
                                    <select
                                        id="trade-corporation"
                                        name="corporation_id"
                                        className={SELECT_CLASS}
                                    >
                                        {others.map((corporation) => (
                                            <option
                                                key={corporation.id}
                                                value={corporation.id}
                                            >
                                                {corporation.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="trade-suit">Suit</Label>
                                    <select
                                        id="trade-suit"
                                        name="suit"
                                        className={SELECT_CLASS}
                                    >
                                        {suits.map((suit) => (
                                            <option
                                                key={suit.value}
                                                value={suit.value}
                                            >
                                                {suit.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="trade-amount">
                                        How many
                                    </Label>
                                    <Input
                                        id="trade-amount"
                                        name="amount"
                                        type="number"
                                        min={1}
                                        defaultValue={1}
                                    />
                                </div>

                                <div className="flex items-end">
                                    <Button type="submit" disabled={processing}>
                                        Hand over
                                    </Button>
                                </div>

                                <div className="sm:col-span-4">
                                    <InputError
                                        message={
                                            errors.amount ??
                                            errors.corporation_id
                                        }
                                    />
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </CardContent>
        </Card>
    );
}
