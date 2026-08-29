import { Form, router } from '@inertiajs/react';
import { GameIcon } from '@/components/game-icon';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/control/protection-cards';
import type { ProtectionCardSummary } from '@/types/game';

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

/**
 * The game's Protection Card catalogue (rulebook 3.3.2, 3.3.3).
 *
 * Availability is Control's to move: the rulebook has cards that are on sale
 * now, rumoured, or research-only, and says others appear once game conditions
 * have passed. Nothing here promotes a card on its own.
 */
export function ProtectionCardCatalogue({
    gameId,
    cards,
}: {
    gameId: number;
    cards: ProtectionCardSummary[];
}) {
    return (
        <div className="flex flex-col gap-6">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left text-muted-foreground">
                            <th className="py-2 pr-4 font-medium">Card</th>
                            <th className="py-2 pr-4 font-medium">Kind</th>
                            <th className="py-2 pr-4 text-right font-medium">
                                Cost
                            </th>
                            <th className="py-2 pr-4 font-medium">Challenge</th>
                            <th className="py-2 pr-4 font-medium">
                                Consequence
                            </th>
                            <th className="py-2 pr-4 font-medium">Charge</th>
                            <th className="py-2 pr-4 font-medium">
                                Availability
                            </th>
                            <th className="py-2 font-medium" />
                        </tr>
                    </thead>
                    <tbody>
                        {cards.map((card) => (
                            <tr
                                key={card.id}
                                className="border-b align-top last:border-0"
                            >
                                <td className="py-2 pr-4 font-medium">
                                    {card.name}
                                    {card.code ? (
                                        <span className="ml-2 font-mono text-xs font-normal text-muted-foreground">
                                            {card.code}
                                        </span>
                                    ) : null}
                                </td>
                                <td className="py-2 pr-4 text-muted-foreground">
                                    <span className="flex items-center gap-1.5">
                                        <GameIcon
                                            glyph={card.kind_glyph}
                                            label={card.kind_label}
                                        />
                                        <span aria-hidden="true">
                                            {card.kind_label}
                                        </span>
                                    </span>
                                </td>
                                <td className="py-2 pr-4 text-right font-mono tabular-nums">
                                    {/* The seeded cards carry no price: the shop
                                        does not work the way the card sheet's
                                        cost column suggests. */}
                                    {card.cost ?? '—'}
                                </td>
                                <td className="max-w-48 py-2 pr-4 text-muted-foreground">
                                    {card.challenge}
                                </td>
                                <td className="max-w-64 py-2 pr-4 text-muted-foreground">
                                    {card.consequence}
                                </td>
                                <td className="max-w-64 py-2 pr-4 text-muted-foreground">
                                    {card.charge_consequence
                                        ? `${card.charge_cost}cr — ${card.charge_consequence}`
                                        : '—'}
                                </td>
                                <td className="py-2 pr-4">
                                    <Badge
                                        variant={
                                            card.availability === 'available'
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        {card.availability_label}
                                    </Badge>
                                </td>
                                <td className="py-2 text-right">
                                    {card.installed_count > 0 ? (
                                        <span className="text-muted-foreground">
                                            {card.installed_count} installed
                                        </span>
                                    ) : (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.delete(
                                                    destroy.url({
                                                        game: gameId,
                                                        cardType: card.id,
                                                    }),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Remove
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {cards.length === 0 && (
                            <tr>
                                <td
                                    colSpan={8}
                                    className="py-4 text-muted-foreground"
                                >
                                    The catalogue is empty. Security has nothing
                                    to buy yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <Form
                {...store.form({ game: gameId })}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="grid gap-3 rounded-md border p-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2 lg:col-span-2">
                            <Label htmlFor="card-name">New card title</Label>
                            <Input
                                id="card-name"
                                name="name"
                                required
                                placeholder="Reinforced Bulkhead"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="card-kind">Kind</Label>
                            <select
                                id="card-kind"
                                name="kind"
                                defaultValue="physical"
                                className={SELECT_CLASS}
                            >
                                <option value="physical">Physical</option>
                                <option value="cyber">Cyber</option>
                            </select>
                            <InputError message={errors.kind} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="card-cost">Cost to buy</Label>
                            <Input
                                id="card-cost"
                                name="cost"
                                type="number"
                                min={0}
                                placeholder="Unpriced"
                            />
                            <p className="text-xs text-muted-foreground">
                                Optional. The card list carries no prices — the
                                shop is a separate piece of work — so this is
                                only what Control chooses to charge.
                            </p>
                            <InputError message={errors.cost} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="card-code">Card code</Label>
                            <Input
                                id="card-code"
                                name="code"
                                placeholder="Leave blank for a new card"
                            />
                            {/* The code is how a card's artwork is found, so a
                                card invented here has none and is shown as its
                                text. */}
                            <InputError message={errors.code} />
                        </div>

                        <div className="grid gap-2 lg:col-span-2">
                            <Label htmlFor="card-challenge">Challenge</Label>
                            <Input
                                id="card-challenge"
                                name="challenge"
                                required
                                placeholder="Brute (6)"
                            />
                            <p className="text-xs text-muted-foreground">
                                As the card prints it. Anything the card says
                                works, including "Brute/Hack (2)" or a strength
                                that counts something.
                            </p>
                            <InputError message={errors.challenge} />
                        </div>

                        <div className="grid gap-2 lg:col-span-2">
                            <Label htmlFor="card-consequence">
                                Consequence
                            </Label>
                            <Input
                                id="card-consequence"
                                name="consequence"
                                required
                                placeholder="One Wound"
                            />
                            <InputError message={errors.consequence} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="card-charge-cost">
                                Charge cost (optional)
                            </Label>
                            <Input
                                id="card-charge-cost"
                                name="charge_cost"
                                type="number"
                                min={0}
                            />
                            <InputError message={errors.charge_cost} />
                        </div>

                        <div className="grid gap-2 lg:col-span-2">
                            <Label htmlFor="card-charge">
                                Charge consequence (optional)
                            </Label>
                            <Input
                                id="card-charge"
                                name="charge_consequence"
                                placeholder="One Tag"
                            />
                            <InputError message={errors.charge_consequence} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="card-availability">
                                Availability
                            </Label>
                            <select
                                id="card-availability"
                                name="availability"
                                defaultValue="available"
                                className={SELECT_CLASS}
                            >
                                <option value="available">On sale now</option>
                                <option value="rumoured">Rumoured</option>
                                <option value="research_only">
                                    Research only
                                </option>
                            </select>
                            <InputError message={errors.availability} />
                        </div>

                        <div className="flex items-end">
                            <Button type="submit" disabled={processing}>
                                Add card
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
