import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { discord, release } from '@/routes/control/characters';

/**
 * Reserves a character for a Discord handle, and shows who has claimed it.
 *
 * The handle is only a claim ticket. Once a player has signed in and been bound
 * to the character, the badge reflects the account, not the handle, because a
 * player renaming themselves on Discord does not change who they are playing.
 */
export function DiscordHandle(props: {
    gameId: number;
    characterId: number;
    characterName: string;
    handle: string | null;
    claimedBy: string | null;
}) {
    // Keying on the server's values remounts the editor when polling brings a
    // change from another Control session, so the field re-syncs without the
    // component having to sync state inside an effect. Typing does not remount,
    // because the server value has not moved.
    return (
        <HandleEditor
            key={`${props.handle ?? ''}:${props.claimedBy ?? ''}`}
            {...props}
        />
    );
}

function HandleEditor({
    gameId,
    characterId,
    characterName,
    handle,
    claimedBy,
}: {
    gameId: number;
    characterId: number;
    characterName: string;
    handle: string | null;
    claimedBy: string | null;
}) {
    const [value, setValue] = useState(handle ?? '');

    const save = () => {
        const next = value.trim();

        if (next === (handle ?? '')) {
            return;
        }

        router.post(
            discord.url({ game: gameId, character: characterId }),
            { discord_username: next || null },
            { preserveScroll: true },
        );
    };

    if (claimedBy) {
        return (
            <div className="flex items-center gap-2">
                <Badge variant="secondary">{claimedBy}</Badge>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() =>
                        router.post(
                            release.url({
                                game: gameId,
                                character: characterId,
                            }),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Release
                </Button>
            </div>
        );
    }

    return (
        <Input
            value={value}
            onChange={(event) => setValue(event.target.value)}
            onBlur={save}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    event.currentTarget.blur();
                }
            }}
            placeholder="discord handle"
            aria-label={`Discord handle for ${characterName}`}
            className="h-8 w-40"
        />
    );
}
