import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CardFace } from '@/components/card-face';
import { FactionBadge } from '@/components/faction-badge';
import { GameIcon } from '@/components/game-icon';
import { GameStateNotice } from '@/components/game-state-notice';
import Heading from '@/components/heading';
import { SearchPicker } from '@/components/search-picker';
import type { PickerOption } from '@/components/search-picker';
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
                            : 'What you are carrying. A card changes hands by talking to Control.'
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
                                label={cards[0].category_label}
                                glyph={cards[0].category_glyph}
                            />
                        );
                    })
                )}

                {character.can_give && character.cards.length > 0 ? (
                    <GiveACard character={character} recipients={recipients} />
                ) : null}
            </CardContent>
        </Card>
    );
}

/**
 * Handing one of your cards to somebody else (rulebook 2.1).
 *
 * On the hand it spends out of rather than once at the top of the page, because
 * a player may hold two seats and which of them is handing the card over is the
 * first thing the trade has to say - putting it on the hand answers that by
 * where the button is.
 *
 * Only the card moves. There is no price box, and that is deliberate: a
 * transfer that also took the other player's Credits would be one player
 * reaching into another's purse on the strength of a number only the giver had
 * typed. What was agreed in exchange is settled at the table, exactly as a
 * research point trade is.
 */
function GiveACard({
    character,
    recipients,
}: {
    character: CharacterEquipment;
    recipients: EquipmentRecipient[];
}) {
    const [cardId, setCardId] = useState<number | null>(null);
    const [toId, setToId] = useState<number | null>(null);
    const [copies, setCopies] = useState('1');
    const [error, setError] = useState<string | null>(null);

    // Never yourself: the card is already in that hand, and the server says so
    // too rather than trusting this to have kept it off the list.
    const others = recipients.filter(
        (person) => person.character_id !== character.character_id,
    );

    const held = character.cards.filter((card) => card.copies > 0);

    if (others.length === 0 || held.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 border-t pt-4">
            <p className="text-sm font-medium">Hand a card to somebody</p>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div className="grid gap-1">
                    <Label htmlFor={`give-card-${character.character_id}`}>
                        Card
                    </Label>
                    <SearchPicker
                        id={`give-card-${character.character_id}`}
                        options={heldOptions(held)}
                        value={cardId}
                        onChange={setCardId}
                        placeholder={`Search ${held.length} cards…`}
                        searchPlaceholder="Name, code or category…"
                        emptyMessage="You are carrying nothing of that name."
                    />
                </div>

                <div className="grid gap-1">
                    <Label htmlFor={`give-to-${character.character_id}`}>
                        To
                    </Label>
                    <SearchPicker
                        id={`give-to-${character.character_id}`}
                        options={recipientOptions(others)}
                        value={toId}
                        onChange={setToId}
                        placeholder={`Search ${others.length} people…`}
                        searchPlaceholder="Name, role or team…"
                        emptyMessage="Nobody of that name is in this game."
                    />
                </div>

                <div className="grid gap-1">
                    <Label htmlFor={`give-copies-${character.character_id}`}>
                        Copies
                    </Label>
                    <Input
                        id={`give-copies-${character.character_id}`}
                        type="number"
                        min={1}
                        value={copies}
                        onChange={(event) => setCopies(event.target.value)}
                    />
                </div>
            </div>

            {/* Kept on this hand rather than read off the page: a player may
                hold two seats and a game has a page of them, so a page-level
                `errors` would put one hand's refusal under every hand on
                screen. Same reasoning as the run screen's `useRunAction`. */}
            {error ? <p className="text-sm text-destructive">{error}</p> : null}

            <Button
                size="sm"
                className="self-start"
                disabled={cardId === null || toId === null}
                onClick={() =>
                    router.post(
                        give.url(),
                        {
                            from_character_id: character.character_id,
                            to_character_id: toId,
                            equipment_card_type_id: cardId,
                            copies: Number(copies),
                        },
                        {
                            preserveScroll: true,
                            onSuccess: () => {
                                setError(null);
                                setCardId(null);
                                setToId(null);
                                setCopies('1');
                            },
                            onError: (errors) =>
                                setError(
                                    Object.values(errors)[0] ??
                                        'That card could not be handed over.',
                                ),
                        },
                    )
                }
            >
                Hand it over
            </Button>
        </div>
    );
}

function heldOptions(cards: EquipmentHolding[]): PickerOption[] {
    return cards.map((card) => ({
        value: card.card_type_id,
        label: card.name,
        hint: [card.code, card.category_label, `×${card.copies}`]
            .filter(Boolean)
            .join(' · '),
        search: [card.name, card.code, card.category_label]
            .filter(Boolean)
            .join(' '),
    }));
}

function recipientOptions(recipients: EquipmentRecipient[]): PickerOption[] {
    return recipients.map((person) => ({
        value: person.character_id,
        label: person.name,
        hint: person.team
            ? `${person.role_label} — ${person.team}`
            : person.role_label,
        search: [person.name, person.role_label, person.team]
            .filter(Boolean)
            .join(' '),
    }));
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
