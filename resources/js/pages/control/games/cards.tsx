import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CardFace } from '@/components/card-face';
import Heading from '@/components/heading';
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
import { index, show } from '@/routes/control/games';
import type {
    EquipmentCardSummary,
    GameSummary,
    TechnologySummary,
} from '@/types/game';

type Props = {
    game: GameSummary;
    equipment: EquipmentCardSummary[];
    technologies: TechnologySummary[];
    hasArtwork: boolean;
};

const SUITS = ['cog', 'brain', 'leaf', 'maths'] as const;

/**
 * The Equipment and technology card lists.
 *
 * Read-only. The market that sells equipment and the research game that spends
 * Research Points are both unbuilt, so there is nothing here to enforce - what
 * this replaces is Control leafing through a printed card list while ruling on
 * something at the table.
 */
export default function ControlCards({
    game,
    equipment,
    technologies,
    hasArtwork,
}: Props) {
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();

    const matches = (haystack: Array<string | null>) =>
        needle === '' ||
        haystack.some((value) => value?.toLowerCase().includes(needle));

    const shownEquipment = equipment.filter((card) =>
        matches([card.name, card.code, card.effect, card.category_label]),
    );

    const shownTechnologies = technologies.filter((technology) =>
        matches([
            technology.name,
            technology.code,
            technology.effect,
            technology.description,
            technology.corporation,
            technology.tree,
        ]),
    );

    return (
        <>
            <Head title={`Cards — ${game.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Card lists"
                        description="The Equipment Runners carry, and the technologies on the tech trees."
                    />
                    <Button
                        variant="ghost"
                        onClick={() => router.get(show.url({ game: game.id }))}
                    >
                        Back to the game
                    </Button>
                </div>

                {!hasArtwork && (
                    <p className="text-sm text-muted-foreground">
                        No card artwork is on record, so every card below is
                        shown as its text. Adding the images under{' '}
                        <code className="font-mono">
                            public/images/cards/&lt;CODE&gt;
                        </code>{' '}
                        is all it takes for them to appear.
                    </p>
                )}

                <Input
                    aria-label="Search the card lists"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search by name, code or effect…"
                    className="max-w-md"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Equipment
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                {shownEquipment.length} of {equipment.length}
                            </span>
                        </CardTitle>
                        <CardDescription>
                            Permanent items are equipped before a Run and only
                            three at a time; the other two are played as
                            Protection Cards are met and then go back to
                            Control. A card with no price is granted by a
                            technology or a Facility's access effect rather than
                            bought.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3">
                        {shownEquipment.map((card) => (
                            <CardFace
                                key={card.id}
                                name={card.name}
                                code={card.code}
                                imagePath={card.image_path}
                                lines={[
                                    {
                                        label: 'Type',
                                        value: card.category_label,
                                    },
                                    { label: '', value: card.effect },
                                ]}
                                footer={
                                    card.cost === null
                                        ? 'Not sold on the market'
                                        : `${card.cost} Credits`
                                }
                            />
                        ))}
                        {shownEquipment.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No Equipment card matches that.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Technologies
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                {shownTechnologies.length} of{' '}
                                {technologies.length}
                            </span>
                        </CardTitle>
                        <CardDescription>
                            Priced in the four Research Point suits. A
                            technology naming a Facility type can only be housed
                            there, and one with no cost at all is a starting
                            technology rather than a free one.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-muted-foreground">
                                        <th className="py-2 pr-4 font-medium">
                                            Technology
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Tree
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Cost
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Effect
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Needs
                                        </th>
                                        <th className="py-2 pr-4 font-medium">
                                            Housed in
                                        </th>
                                        <th className="py-2 font-medium">
                                            Copy / destroy
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {shownTechnologies.map((technology) => (
                                        <tr
                                            key={technology.id}
                                            className="border-b align-top last:border-0"
                                        >
                                            <td className="py-2 pr-4 font-medium">
                                                {technology.name}
                                                {technology.code ? (
                                                    <span className="ml-2 font-mono text-xs font-normal text-muted-foreground">
                                                        {technology.code}
                                                    </span>
                                                ) : null}
                                                {technology.description ? (
                                                    <p className="mt-1 max-w-64 text-xs font-normal text-muted-foreground">
                                                        {technology.description}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="py-2 pr-4">
                                                <Badge
                                                    variant={
                                                        technology.corporation
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {technology.corporation ??
                                                        technology.tree}
                                                </Badge>
                                            </td>
                                            <td className="py-2 pr-4 font-mono text-xs whitespace-nowrap tabular-nums">
                                                {technology.is_free
                                                    ? '—'
                                                    : SUITS.filter(
                                                          (suit) =>
                                                              technology.cost[
                                                                  suit
                                                              ] > 0,
                                                      )
                                                          .map(
                                                              (suit) =>
                                                                  `${technology.cost[suit]} ${suit}`,
                                                          )
                                                          .join(', ')}
                                            </td>
                                            <td className="max-w-72 py-2 pr-4 text-muted-foreground">
                                                {technology.effect ?? '—'}
                                            </td>
                                            <td className="max-w-48 py-2 pr-4 text-muted-foreground">
                                                {technology.prerequisites
                                                    .length === 0
                                                    ? '—'
                                                    : technology.prerequisites.join(
                                                          ', ',
                                                      )}
                                            </td>
                                            <td className="py-2 pr-4 text-muted-foreground">
                                                {technology.required_facility_type ??
                                                    'Anywhere'}
                                            </td>
                                            <td className="py-2 font-mono text-xs text-muted-foreground tabular-nums">
                                                {technology.copy_strength ===
                                                null
                                                    ? '—'
                                                    : `${technology.copy_strength} / ${technology.destroy_strength}`}
                                            </td>
                                        </tr>
                                    ))}
                                    {shownTechnologies.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="py-4 text-muted-foreground"
                                            >
                                                No technology matches that.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ControlCards.layout = {
    breadcrumbs: [
        { title: 'Control', href: index() },
        { title: 'Cards', href: index() },
    ],
};
