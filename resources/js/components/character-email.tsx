import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { email as emailRoute } from '@/routes/control/characters';

/**
 * The address a player signed up with, against their seat.
 *
 * The second claim ticket beside the Discord handle, and the one that rescues
 * it: a handle Control mistyped leaves somebody signing in to a dashboard with
 * no characters on it, and this is what they name at /claim instead.
 *
 * Still editable after the seat is claimed, which is the difference from the
 * handle editor beside it. The handle is a reservation and stops meaning
 * anything once it has been redeemed; the address is a record of who this
 * player is, and Control correcting a typo in it should not need the seat
 * released first.
 */
export function CharacterEmail(props: {
    gameId: number;
    characterId: number;
    characterName: string;
    email: string | null;
}) {
    // Keyed on the server's value so polling from another Control session
    // remounts the field rather than fighting what is being typed here.
    return <EmailEditor key={props.email ?? ''} {...props} />;
}

function EmailEditor({
    gameId,
    characterId,
    characterName,
    email,
}: {
    gameId: number;
    characterId: number;
    characterName: string;
    email: string | null;
}) {
    const [value, setValue] = useState(email ?? '');

    const save = () => {
        const next = value.trim();

        if (next === (email ?? '')) {
            return;
        }

        router.post(
            emailRoute.url({ game: gameId, character: characterId }),
            { email: next || null },
            { preserveScroll: true },
        );
    };

    return (
        <Input
            type="email"
            value={value}
            onChange={(event) => setValue(event.target.value)}
            onBlur={save}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    event.currentTarget.blur();
                }
            }}
            placeholder="email address"
            aria-label={`Email address for ${characterName}`}
            className="h-8 w-56"
        />
    );
}
