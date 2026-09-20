import { router } from '@inertiajs/react';
import { useState } from 'react';
import { CardFace } from '@/components/card-face';
import { FactionBadge } from '@/components/faction-badge';
import { GameIcon } from '@/components/game-icon';
import { SearchPicker } from '@/components/search-picker';
import type { PickerOption } from '@/components/search-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { give, update } from '@/routes/control/equipment-holdings';
import type {
    CharacterEquipment,
    EquipmentCardSummary,
    EquipmentHoldingGroup,
    EquipmentRecipient,
} from '@/types/game';

/**
 * Who is carrying which Equipment, and Control handing cards out (3.4.1).
 *
 * Per Character rather than per team, because that is what the rulebook caps
 * and what it takes away: three equipped permanent items are *yours*, and
 * *your* permanent Equipment goes to the Security player when you are carried
 * out.
 *
 * Everybody on the roster is here, not only the side that runs. 2.1 has Runners
 * buying equipment "from other players", so a card reaches the Facility by way
 * of whoever was holding it - which may well be a CEO who bought it to hand
 * over.
 *
 * Two controls, and they answer different questions. Giving *adds* copies and
 * is the one Control reaches for at the table: it knows what it is handing over
 * and not what the player already has. Setting a count replaces it, which is
 * the correction - a card spent, a haul split, a number typed wrong.
 */
export function EquipmentHoldings({
    gameId,
    holdings,
    cards,
    recipients,
}: {
    gameId: number;
    holdings: EquipmentHoldingGroup[];
    cards: EquipmentCardSummary[];
    recipients: EquipmentRecipient[];
}) {
    return (
        <div className="flex flex-col gap-10">
            <GiveACard gameId={gameId} cards={cards} recipients={recipients} />

            {holdings.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No characters yet, so nobody is carrying anything.
                </p>
            ) : (
                holdings.map((group) => (
                    <section key={group.key} className="flex flex-col gap-4">
                        <h3 className="flex items-center gap-2 text-sm font-medium">
                            {group.has_badge ? (
                                <FactionBadge faction={group} size="small" />
                            ) : null}
                            {group.name}
                        </h3>

                        <div className="flex flex-col gap-6 sm:pl-2">
                            {group.members.map((member) => (
                                <Hand
                                    key={member.character_id}
                                    gameId={gameId}
                                    character={member}
                                />
                            ))}
                        </div>
                    </section>
                ))
            )}
        </div>
    );
}

function Hand({
    gameId,
    character,
}: {
    gameId: number;
    character: CharacterEquipment;
}) {
    return (
        <div className="flex flex-col gap-2 border-l-2 pl-4">
            <h4 className="text-sm font-medium">
                {character.name}
                <span className="ml-2 text-xs font-normal text-muted-foreground">
                    {character.role_label}
                </span>
            </h4>

            {character.cards.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Carrying nothing. Their briefing named no Equipment, or
                    Control has not given them any yet.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="py-2 pr-4 font-medium">Card</th>
                                <th className="py-2 pr-4 font-medium">
                                    Category
                                </th>
                                <th className="py-2 pr-4 text-right font-medium">
                                    Carrying
                                </th>
                                <th className="py-2 font-medium">Set to</th>
                            </tr>
                        </thead>
                        <tbody>
                            {character.cards.map((card) => (
                                <tr
                                    key={card.card_type_id}
                                    className="border-b last:border-0"
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
                                                glyph={card.category_glyph}
                                                label={card.category_label}
                                            />
                                            <span aria-hidden="true">
                                                {card.category_label}
                                            </span>
                                        </span>
                                    </td>
                                    <td className="py-2 pr-4 text-right font-mono tabular-nums">
                                        {card.copies}
                                    </td>
                                    <td className="py-2">
                                        <SetCopies
                                            gameId={gameId}
                                            characterId={character.character_id}
                                            cardTypeId={card.card_type_id}
                                            current={card.copies}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function SetCopies({
    gameId,
    characterId,
    cardTypeId,
    current,
}: {
    gameId: number;
    characterId: number;
    cardTypeId: number;
    current: number;
}) {
    const [copies, setCopies] = useState(String(current));
    const changed = copies !== String(current);

    return (
        <div className="flex items-center justify-end gap-2">
            <Input
                aria-label="Copies carried"
                type="number"
                min={0}
                value={copies}
                onChange={(event) => setCopies(event.target.value)}
                className="w-20"
            />
            <Button
                size="sm"
                variant="outline"
                disabled={!changed}
                onClick={() =>
                    router.patch(
                        update.url({ game: gameId }),
                        {
                            character_id: characterId,
                            equipment_card_type_id: cardTypeId,
                            copies: Number(copies),
                        },
                        { preserveScroll: true },
                    )
                }
            >
                Set
            </Button>
        </div>
    );
}

/**
 * Hand somebody a card.
 *
 * Which is how a card bought from the market, taken off another Runner or given
 * out by Control reaches a hand: Control is told, and writes it down.
 *
 * Both pickers search, for the reason the shop's do: seventy-four cards and a
 * roster of forty are two lists nobody finds anything in when they are a native
 * `select`, and worst of all on a phone. The card is drawn before it is given,
 * because the thing somebody at the table is holding is the artwork.
 */
function GiveACard({
    gameId,
    cards,
    recipients,
}: {
    gameId: number;
    cards: EquipmentCardSummary[];
    recipients: EquipmentRecipient[];
}) {
    const [characterId, setCharacterId] = useState<number | null>(null);
    const [cardId, setCardId] = useState<number | null>(null);
    const [copies, setCopies] = useState('1');
    const [error, setError] = useState<string | null>(null);

    const card = cards.find((option) => option.id === cardId) ?? null;

    if (recipients.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 rounded-md border p-4">
            <p className="text-sm font-medium">Give somebody a card</p>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="grid gap-1">
                    <Label htmlFor="give-equipment-character">To</Label>
                    <SearchPicker
                        id="give-equipment-character"
                        options={recipientOptions(recipients)}
                        value={characterId}
                        onChange={setCharacterId}
                        placeholder={`Search ${recipients.length} people…`}
                        searchPlaceholder="Name, role or team…"
                        emptyMessage="Nobody of that name is in this game."
                    />
                </div>

                <div className="grid gap-1 lg:col-span-2">
                    <Label htmlFor="give-equipment-card">Card</Label>
                    <SearchPicker
                        id="give-equipment-card"
                        options={cardOptions(cards)}
                        value={cardId}
                        onChange={setCardId}
                        placeholder={`Search ${cards.length} cards…`}
                        searchPlaceholder="Name, code, category or effect…"
                        emptyMessage="No card matches that."
                    />
                </div>

                <div className="grid gap-1">
                    <Label htmlFor="give-equipment-copies">Copies</Label>
                    <Input
                        id="give-equipment-copies"
                        type="number"
                        min={1}
                        value={copies}
                        onChange={(event) => setCopies(event.target.value)}
                    />
                </div>
            </div>

            {card ? (
                <CardFace
                    shape="portrait"
                    name={card.name}
                    code={card.code}
                    imagePath={card.image_path}
                    lines={[{ label: '', value: card.effect }]}
                    footer={card.category_label}
                />
            ) : null}

            {/* Kept on the form rather than read off the page: it is the only
                refusal this control can meet, and a give that silently did
                nothing is the bug every other panel here has already had. */}
            {error ? <p className="text-sm text-destructive">{error}</p> : null}

            <Button
                size="sm"
                className="self-start"
                disabled={characterId === null || cardId === null}
                onClick={() =>
                    router.post(
                        give.url({ game: gameId }),
                        {
                            character_id: characterId,
                            equipment_card_type_id: cardId,
                            copies: Number(copies),
                        },
                        {
                            preserveScroll: true,
                            onSuccess: () => {
                                setError(null);
                                setCardId(null);
                                setCopies('1');
                            },
                            onError: (errors) =>
                                setError(
                                    Object.values(errors)[0] ??
                                        'That card could not be given.',
                                ),
                        },
                    )
                }
            >
                Give
            </Button>
        </div>
    );
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

function cardOptions(cards: EquipmentCardSummary[]): PickerOption[] {
    return cards.map((card) => ({
        value: card.id,
        label: card.name,
        hint: [card.code, card.category_label].filter(Boolean).join(' · '),
        search: [card.name, card.code, card.category_label, card.effect]
            .filter(Boolean)
            .join(' '),
    }));
}
