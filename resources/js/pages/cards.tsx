import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { CardFace } from '@/components/card-face';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cards as cardsRoute, dashboard } from '@/routes';
import type { GameSummary, PublicCardList } from '@/types/game';

type Props = {
    game: GameSummary | null;
    cards: PublicCardList | null;
    hasArtwork: boolean;
};

/**
 * The game's Protection Cards and Equipment, as printed, for everybody.
 *
 * Read-only: a Runner deciding what to carry in, or a Security player deciding
 * what to buy, wants the whole list to read and nothing on it to press. The
 * server has already taken off what is Control's - see
 * `GamePresenter::publicCardList()` - so nothing here filters but the search.
 *
 * Research is deliberately not here. A tech tree is the Corporation's own, on
 * the research page.
 */
export default function Cards({ game, cards, hasArtwork }: Props) {
    const [query, setQuery] = useState('');

    if (game === null || cards === null) {
        return (
            <>
                <Head title="Cards" />
                <div className="p-4">
                    <Heading
                        title="Cards"
                        description="No game has been set up yet."
                    />
                </div>
            </>
        );
    }

    const needle = query.trim().toLowerCase();

    const matches = (haystack: Array<string | null>) =>
        needle === '' ||
        haystack.some((value) => value?.toLowerCase().includes(needle));

    const shownProtection = cards.protection.filter((card) =>
        matches([
            card.name,
            card.code,
            card.challenge,
            card.consequence,
            card.charge_consequence,
            card.kind_label,
        ]),
    );

    const shownEquipment = cards.equipment.filter((card) =>
        matches([card.name, card.code, card.effect, card.category_label]),
    );

    return (
        <>
            <Head title="Cards" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Cards"
                    description="Every Protection Card and Equipment card in the game, as printed."
                />

                {!hasArtwork && (
                    <p className="text-sm text-muted-foreground">
                        No card artwork is on record yet, so every card below is
                        shown as its text.
                    </p>
                )}

                <Input
                    aria-label="Search the cards"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search by name, code or effect…"
                    className="max-w-md"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Protection Cards
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                {shownProtection.length} of{' '}
                                {cards.protection.length}
                            </span>
                        </CardTitle>
                        <CardDescription>
                            What Security installs in a Facility, and what
                            Runners have to get past. Which of these are
                            standing in which Facility is Secret — that is what
                            reconnaissance is for.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3">
                        {shownProtection.map((card) => (
                            <CardFace
                                key={card.id}
                                name={card.name}
                                code={card.code}
                                imagePath={card.image_path}
                                shape="landscape"
                                lines={[
                                    {
                                        label: 'Kind',
                                        value: card.kind_label,
                                        glyph: card.kind_glyph,
                                    },
                                    {
                                        label: 'Challenge',
                                        value: card.challenge,
                                    },
                                    { label: '', value: card.consequence },
                                    {
                                        label: 'Charge',
                                        value: card.charge_consequence
                                            ? `${card.charge_cost}cr — ${card.charge_consequence}`
                                            : null,
                                    },
                                ]}
                                footer={card.availability_label}
                            />
                        ))}
                        {shownProtection.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No Protection Card matches that.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Equipment
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                {shownEquipment.length} of{' '}
                                {cards.equipment.length}
                            </span>
                        </CardTitle>
                        <CardDescription>
                            Permanent items are equipped before a Run, three at
                            a time; the other two kinds are played as Protection
                            Cards are met. What you are carrying yourself is on
                            your Equipment page.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3">
                        {shownEquipment.map((card) => (
                            <CardFace
                                key={card.id}
                                name={card.name}
                                code={card.code}
                                imagePath={card.image_path}
                                shape="portrait"
                                lines={[
                                    {
                                        label: 'Type',
                                        value: card.category_label,
                                        glyph: card.category_glyph,
                                    },
                                    { label: '', value: card.effect },
                                ]}
                                footer={
                                    card.cost === null
                                        ? null
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
            </div>
        </>
    );
}

Cards.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Cards', href: cardsRoute() },
    ],
};
