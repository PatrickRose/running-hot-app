import { Head, router } from '@inertiajs/react';
import { CardFace } from '@/components/card-face';
import { FactionBadge } from '@/components/faction-badge';
import { GameIcon } from '@/components/game-icon';
import { GameStateNotice } from '@/components/game-state-notice';
import { GiveCardDialog, peopleToGiveTo } from '@/components/give-card-dialog';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { give } from '@/routes/equipment';
import type {
    CharacterEquipment,
    EquipmentHolding,
    EquipmentHoldingGroup,
    EquipmentRecipient,
    GameSummary,
} from '@/types/game';

type Props = {
    game: GameSummary | null;
    holdings: EquipmentHoldingGroup[] | null;
    recipients: EquipmentRecipient[];
    is_control: boolean;
};

/**
 * The three categories in the order 3.4.1 introduces them, which is also the
 * order they matter in: what you are carrying before the run starts, then what
 * you can spend during it.
 */
const CATEGORY_ORDER = ['permanent', 'this-run', 'single-use'] as const;

/**
 * What you are carrying (rulebook 3.4.1).
 *
 * Its own page rather than a corner of the dashboard, because a hand is what
 * you work from: choosing three permanent items to equip means laying the cards
 * out and reading them, so they are drawn as cards rather than listed as names.
 *
 * Not only a Runner's. 2.1 has Runners buying equipment "from other players",
 * so a card may be sitting with whoever bought it to hand over - which is as
 * likely to be a CEO as a gangmate, and they need to read it too.
 *
 * You see your own and nobody else's, and Control sees everybody. The server
 * decides which - `GamePresenter::equipmentHoldings()` takes the viewer - so
 * there is nothing here that filters, and a hand that is not yours never
 * reaches the browser.
 *
 * Handing a card to another player happens here, and it is the one thing on
 * this page that is not read-only: 2.1 has Runners buying equipment "from other
 * players", and that half of the sentence had nowhere to happen. Only the card
 * moves - what was agreed in exchange is settled at the table, as a research
 * point trade is. Every other way a count moves is still a conversation with
 * Control, who sets it on their own panel.
 */
export default function Equipment({
    game,
    holdings,
    recipients,
    is_control,
}: Props) {
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

    const hands = holdings.flatMap((group) => group.members);

    return (
        <>
            <Head title="Equipment" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Equipment"
                    description={
                        is_control
                            ? 'Everybody in the game, because you are Control.'
                            : 'What you are carrying. Click one of your cards to hand it to somebody else.'
                    }
                />

                <GameStateNotice game={game} />

                {hands.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Nothing to show</CardTitle>
                            <CardDescription>
                                {is_control
                                    ? 'This game has no characters yet.'
                                    : 'You are holding no character in this game, so there is no hand to read.'}
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    holdings.map((group) => (
                        <TeamHands
                            key={group.key}
                            group={group}
                            recipients={recipients}
                            showTeamHeading={is_control}
                        />
                    ))
                )}
            </div>
        </>
    );
}

function TeamHands({
    group,
    recipients,
    showTeamHeading,
}: {
    group: EquipmentHoldingGroup;
    recipients: EquipmentRecipient[];
    showTeamHeading: boolean;
}) {
    return (
        <section className="flex flex-col gap-4">
            {/* A player holding one Runner already knows which gang they are
                in, so the band is Control's: it is what makes forty hands
                readable. */}
            {showTeamHeading ? (
                <h2 className="flex items-center gap-2 text-sm font-medium">
                    {group.has_badge ? (
                        <FactionBadge faction={group} size="small" />
                    ) : null}
                    {group.name}
                </h2>
            ) : null}

            {group.members.map((member) => (
                <Hand
                    key={member.character_id}
                    character={member}
                    recipients={recipients}
                />
            ))}
        </section>
    );
}

function Hand({
    character,
    recipients,
}: {
    character: CharacterEquipment;
    recipients: EquipmentRecipient[];
}) {
    const held = character.cards.reduce(
        (total, card) => total + card.copies,
        0,
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>{character.name}</CardTitle>
                <CardDescription>
                    {held === 0
                        ? 'Carrying nothing.'
                        : `Carrying ${held} ${held === 1 ? 'card' : 'cards'}.`}
                    {character.can_give && held > 0
                        ? ' Click a card to hand it over.'
                        : ''}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
                {character.cards.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Your briefing named no Equipment, or Control has not
                        given you any yet.
                    </p>
                ) : (
                    CATEGORY_ORDER.map((category) => {
                        const cards = character.cards.filter(
                            (card) => card.category === category,
                        );

                        if (cards.length === 0) {
                            return null;
                        }

                        return (
                            <CategoryRow
                                key={category}
                                cards={cards}
                                character={character}
                                recipients={recipients}
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
    character,
    recipients,
    label,
    glyph,
}: {
    cards: EquipmentHolding[];
    character: CharacterEquipment;
    recipients: EquipmentRecipient[];
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
                    <HeldCard
                        key={card.card_type_id}
                        card={card}
                        character={character}
                        recipients={recipients}
                    />
                ))}
            </div>
        </div>
    );
}

/**
 * One card in a hand, and the way it leaves that hand (rulebook 2.1).
 *
 * The card is the control: clicking it is how it is handed over, because the
 * card is what the two players are talking about and it is already on screen.
 * A hand nobody may give out of - somebody else's, or your own before the game
 * is running - is the same card without the button around it, rather than a
 * control that does nothing when pressed.
 */
function HeldCard({
    card,
    character,
    recipients,
}: {
    card: EquipmentHolding;
    character: CharacterEquipment;
    recipients: EquipmentRecipient[];
}) {
    const face = (
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
                to be legible over artwork as well as over the text box - the
                same reason the defence board's copy count sits outside. */}
            <span className="pointer-events-none absolute top-1.5 left-1.5 rounded-md bg-background/90 px-1.5 py-0.5 text-xs font-medium tabular-nums shadow-sm ring-1 ring-border">
                <span aria-hidden="true">&times;{card.copies}</span>
                <span className="sr-only">{card.copies} in hand</span>
            </span>
        </div>
    );

    // Never yourself: the card is already in that hand, and the server refuses
    // it too rather than trusting this to have kept it off the list.
    const others = recipients.filter(
        (person) => person.character_id !== character.character_id,
    );

    if (!character.can_give || others.length === 0 || card.copies < 1) {
        return face;
    }

    return (
        <GiveCardDialog
            name={card.name}
            face={face}
            recipients={peopleToGiveTo(others)}
            title={`Hand ${card.name} over`}
            description={`Out of ${character.name}'s hand. Only the card moves — whatever was agreed for it is settled at the table.`}
            actionLabel="Hand it over"
            inHand={card.copies}
            submit={(to, copies, handlers) =>
                router.post(
                    give.url(),
                    {
                        from_character_id: character.character_id,
                        to_character_id: to,
                        equipment_card_type_id: card.card_type_id,
                        copies,
                    },
                    {
                        preserveScroll: true,
                        onSuccess: handlers.onSuccess,
                        onError: (errors) =>
                            handlers.onError(
                                Object.values(errors)[0] ??
                                    'That card could not be handed over.',
                            ),
                    },
                )
            }
        >
            {face}
        </GiveCardDialog>
    );
}
