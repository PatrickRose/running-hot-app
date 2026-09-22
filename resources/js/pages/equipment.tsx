import { Head } from '@inertiajs/react';
import { CardFace } from '@/components/card-face';
import { FactionBadge } from '@/components/faction-badge';
import { GameIcon } from '@/components/game-icon';
import { GameStateNotice } from '@/components/game-state-notice';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    EquipmentHolding,
    GangEquipmentHoldings,
    GameSummary,
    RunnerEquipment,
} from '@/types/game';

type Props = {
    game: GameSummary | null;
    holdings: GangEquipmentHoldings[] | null;
    is_control: boolean;
};

/**
 * The three categories in the order 3.4.1 introduces them, which is also the
 * order they matter in: what you are carrying before the run starts, then what
 * you can spend during it.
 */
const CATEGORY_ORDER = ['permanent', 'this-run', 'single-use'] as const;

/**
 * What a Runner is carrying (rulebook 3.4.1).
 *
 * Its own page rather than a corner of the dashboard, because a hand is what
 * you work from: choosing three permanent items to equip means laying the cards
 * out and reading them, so they are drawn as cards rather than listed as names.
 *
 * You see your own and nobody else's, and Control sees everybody. The server
 * decides which - `GamePresenter::equipmentHoldings()` takes the viewer - so
 * there is nothing here that filters, and a hand that is not yours never
 * reaches the browser.
 *
 * Read-only. Every way a card changes hands is a conversation with Control, who
 * sets the count, which is the division a Corporation's Protection Card
 * holdings already live under.
 */
export default function Equipment({ game, holdings, is_control }: Props) {
    if (game === null || holdings === null) {
        return (
            <>
                <Head title="Equipment" />
                <div className="p-4">
                    <Heading
                        title="Equipment"
                        description="No game has been set up yet."
                    />
                </div>
            </>
        );
    }

    const runners = holdings.flatMap((gang) => gang.runners);

    return (
        <>
            <Head title="Equipment" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Equipment"
                    description={
                        is_control
                            ? 'Every Runner in the game, because you are Control.'
                            : 'What you are carrying. A card changes hands by talking to Control.'
                    }
                />

                <GameStateNotice game={game} />

                {runners.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Nothing to show</CardTitle>
                            <CardDescription>
                                {is_control
                                    ? 'This game has no Runners or Freelancers yet.'
                                    : 'You are not holding a Runner or Freelancer in this game. Equipment is carried by the side that runs — a Corporate seat has none.'}
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    holdings.map((gang) => (
                        <GangHands
                            key={gang.gang_id ?? 'freelancers'}
                            gang={gang}
                            showGangHeading={is_control}
                        />
                    ))
                )}
            </div>
        </>
    );
}

function GangHands({
    gang,
    showGangHeading,
}: {
    gang: GangEquipmentHoldings;
    showGangHeading: boolean;
}) {
    return (
        <section className="flex flex-col gap-4">
            {/* A player holding one Runner already knows which gang they are
                in, so the band is Control's: it is what makes twenty-one hands
                readable. */}
            {showGangHeading ? (
                <h2 className="flex items-center gap-2 text-sm font-medium">
                    {gang.has_badge ? (
                        <FactionBadge faction={gang} size="small" />
                    ) : null}
                    {gang.name}
                </h2>
            ) : null}

            {gang.runners.map((runner) => (
                <RunnerHand key={runner.character_id} runner={runner} />
            ))}
        </section>
    );
}

function RunnerHand({ runner }: { runner: RunnerEquipment }) {
    const held = runner.cards.reduce((total, card) => total + card.copies, 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>{runner.name}</CardTitle>
                <CardDescription>
                    {held === 0
                        ? 'Carrying nothing.'
                        : `Carrying ${held} ${held === 1 ? 'card' : 'cards'}.`}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                {runner.cards.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Your briefing named no Equipment, or Control has not
                        given you any yet.
                    </p>
                ) : (
                    CATEGORY_ORDER.map((category) => {
                        const cards = runner.cards.filter(
                            (card) => card.category === category,
                        );

                        if (cards.length === 0) {
                            return null;
                        }

                        return (
                            <CategoryRow
                                key={category}
                                cards={cards}
                                label={cards[0].category_label}
                                glyph={cards[0].category_glyph}
                            />
                        );
                    })
                )}
            </CardContent>
        </Card>
    );
}

/**
 * One category's cards.
 *
 * Grouped because the categories are the one thing about a card that decides
 * when it may be used: a Permanent item is equipped before the run and capped
 * at three, and the other two are played as Protection Cards are met and then
 * go back to Control.
 */
function CategoryRow({
    cards,
    label,
    glyph,
}: {
    cards: EquipmentHolding[];
    label: string;
    glyph: string;
}) {
    return (
        <div className="flex flex-col gap-2">
            <h3 className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <GameIcon glyph={glyph} label={label} />
                <span aria-hidden="true">{label}</span>
            </h3>

            <div className="flex flex-wrap gap-3">
                {cards.map((card) => (
                    <HeldCard key={card.card_type_id} card={card} />
                ))}
            </div>
        </div>
    );
}

function HeldCard({ card }: { card: EquipmentHolding }) {
    return (
        <div className="relative">
            <CardFace
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
            />

            {/* Drawn on top of CardFace rather than inside it, because it has
                to be legible over artwork as well as over the text box — the
                same reason the defence board's copy count sits outside. */}
            <span className="pointer-events-none absolute top-1.5 left-1.5 rounded-md bg-background/90 px-1.5 py-0.5 text-xs font-medium tabular-nums shadow-sm ring-1 ring-border">
                <span aria-hidden="true">&times;{card.copies}</span>
                <span className="sr-only">{card.copies} in hand</span>
            </span>
        </div>
    );
}
