import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ResearchSuitIcon } from '@/components/research-suit-cost';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cost as costRoute } from '@/routes/control/technologies';
import type { ResearchSuitSummary, TechnologySummary } from '@/types/game';

/**
 * Control repricing a technology already on the tree (rulebook 3.2.2).
 *
 * The four suits and nothing else, posted to a route that takes nothing else:
 * the whole-row update rebuilds the prerequisites and the deck grant from what
 * it is sent, and a price correction is not the moment to re-type the card.
 *
 * Each dialog is its own `Form`, so a refusal is drawn under the dialog that
 * was refused rather than read off the page, where every row's four boxes
 * report against the same four keys.
 */
export function TechnologyCostDialog({
    gameId,
    technology,
    suits,
}: {
    gameId: number;
    technology: TechnologySummary;
    suits: ResearchSuitSummary[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="ghost" className="h-7 px-2 text-xs">
                    Edit
                    <span className="sr-only">
                        {' '}
                        the cost of {technology.name}
                    </span>
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cost of {technology.name}</DialogTitle>
                    <DialogDescription>
                        What the next Corporation to research it pays, in each
                        suit. Nothing already researched is charged or refunded.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...costRoute.form({
                        game: gameId,
                        technology: technology.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {suits.map((suit) => {
                                    const field = `${suit.value}_cost`;
                                    const id = `technology-${technology.id}-${field}`;

                                    return (
                                        <div
                                            key={suit.value}
                                            className="grid gap-1"
                                        >
                                            <Label
                                                htmlFor={id}
                                                className="flex items-center gap-1"
                                            >
                                                <ResearchSuitIcon suit={suit} />
                                                <span aria-hidden="true">
                                                    {suit.label}
                                                </span>
                                            </Label>
                                            <Input
                                                id={id}
                                                name={field}
                                                type="number"
                                                min={0}
                                                required
                                                defaultValue={
                                                    technology.cost[
                                                        suit.value
                                                    ] ?? 0
                                                }
                                            />
                                            <InputError
                                                message={errors[field]}
                                            />
                                        </div>
                                    );
                                })}
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    Save cost
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
