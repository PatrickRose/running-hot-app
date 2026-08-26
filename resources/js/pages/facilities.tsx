import { Head } from '@inertiajs/react';
import { CardFace } from '@/components/card-face';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { FacilityBoard, GameSummary } from '@/types/game';

/**
 * Where in the stack a card sits, in words.
 *
 * Position 1 is the card Runners meet first (rulebook 3.3.4), so the stack reads
 * as an order of encounter rather than as a list of numbers.
 */
function ordinal(position: number): string {
    const suffixes = ['th', 'st', 'nd', 'rd'];
    const remainder = position % 100;

    return (
        position +
        (suffixes[(remainder - 20) % 10] ?? suffixes[remainder] ?? suffixes[0])
    );
}

type Props = {
    game: GameSummary | null;
    board: FacilityBoard | null;
};

export default function Facilities({ game, board }: Props) {
    if (game === null || board === null) {
        return (
            <>
                <Head title="Facilities" />
                <div className="p-4">
                    <Heading
                        title="Facilities"
                        description="No game is running."
                    />
                </div>
            </>
        );
    }

    const own = board.own;

    return (
        <>
            <Head title="Facilities" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Facilities"
                    description={
                        board.turn === null
                            ? 'The game has not started.'
                            : `Turn ${board.turn}`
                    }
                />

                {own && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{own.name} — your defences</CardTitle>
                            <CardDescription>
                                {own.credits} Credits &middot;{' '}
                                {own.physical_slots} physical and{' '}
                                {own.cyber_slots} cyber slots per Facility
                                &middot; {own.technology_capacity_per_facility}{' '}
                                technologies storable per Facility
                                {own.card_move_discount > 0 &&
                                    ` · ${own.card_move_discount} Credit discount on moving cards`}
                                <br />
                                Position 1 is the card Runners meet first. Ask
                                Control to install, reorder or remove a card.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {own.facilities.map((facility) => (
                                <div
                                    key={facility.id}
                                    className="rounded-md border p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-medium">
                                            {facility.name}{' '}
                                            <span className="text-muted-foreground">
                                                &middot;{' '}
                                                {facility.facility_type}
                                            </span>
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {facility.available ? (
                                                <Badge variant="outline">
                                                    Open
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Building &middot; opens turn{' '}
                                                    {
                                                        facility.available_from_turn
                                                    }
                                                </Badge>
                                            )}
                                            {facility.security.directed && (
                                                <Badge>
                                                    Security directed here
                                                </Badge>
                                            )}
                                            {facility.security.budget > 0 && (
                                                <Badge variant="outline">
                                                    {facility.security.budget -
                                                        facility.security
                                                            .budget_spent}{' '}
                                                    of{' '}
                                                    {facility.security.budget}
                                                    cr left
                                                </Badge>
                                            )}
                                        </div>
                                    </div>

                                    <div className="mt-3 grid gap-4 lg:grid-cols-2">
                                        {facility.stacks.map((stack) => (
                                            <div
                                                key={stack.kind}
                                                className="flex flex-col gap-1"
                                            >
                                                <p className="text-sm font-medium">
                                                    {stack.kind_label}{' '}
                                                    <span className="text-muted-foreground">
                                                        {stack.cards.length}/
                                                        {stack.slots}
                                                    </span>
                                                </p>
                                                {/* Left to right in the
                                                    order Runners meet them, so
                                                    the stack reads the way it
                                                    sits on the table. */}
                                                <ol className="flex gap-3 overflow-x-auto pb-2">
                                                    {stack.cards.map((card) => (
                                                        <li key={card.id}>
                                                            <CardFace
                                                                name={card.name}
                                                                code={card.code}
                                                                imagePath={
                                                                    card.image_path
                                                                }
                                                                lines={[
                                                                    {
                                                                        label: 'Challenge',
                                                                        value: card.challenge,
                                                                    },
                                                                    {
                                                                        label: '',
                                                                        value: card.consequence,
                                                                    },
                                                                    {
                                                                        label: 'Charge',
                                                                        value: card.charge_consequence
                                                                            ? `${card.charge_cost}cr — ${card.charge_consequence}`
                                                                            : null,
                                                                    },
                                                                ]}
                                                                footer={`Met ${ordinal(
                                                                    card.position,
                                                                )}`}
                                                            />
                                                        </li>
                                                    ))}
                                                    {stack.cards.length ===
                                                        0 && (
                                                        <li className="text-sm text-muted-foreground">
                                                            Undefended.
                                                        </li>
                                                    )}
                                                </ol>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                            {own.facilities.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    You have no Facilities.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Every Facility in Procatorion</CardTitle>
                        <CardDescription>
                            Who owns what. What is installed in another
                            Corporation's Facility is Secret — reconnaissance is
                            how you find out.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {board.public.map((corporation) => (
                            <div key={corporation.name}>
                                <p className="font-medium">
                                    {corporation.name}
                                    {corporation.is_yours && (
                                        <Badge
                                            variant="outline"
                                            className="ml-2 align-middle"
                                        >
                                            You
                                        </Badge>
                                    )}
                                </p>
                                <ul className="mt-1 flex flex-col gap-0.5 text-sm">
                                    {corporation.facilities.map((facility) => (
                                        <li key={facility.id}>
                                            {facility.name}
                                            <span className="text-muted-foreground">
                                                {' '}
                                                — {facility.facility_type}
                                                {!facility.available &&
                                                    ' (building)'}
                                            </span>
                                        </li>
                                    ))}
                                    {corporation.facilities.length === 0 && (
                                        <li className="text-muted-foreground">
                                            No Facilities.
                                        </li>
                                    )}
                                </ul>
                            </div>
                        ))}
                        {board.public.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Nothing built yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Facilities.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Facilities', href: dashboard() },
    ],
};
