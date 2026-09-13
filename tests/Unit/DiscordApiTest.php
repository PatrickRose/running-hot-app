<?php

namespace Tests\Unit;

use App\Services\Discord\DiscordApi;
use Tests\TestCase;

/**
 * The permissions the bot asks a server for.
 *
 * Held twice out of necessity - a bitfield for Discord's invite URL, a named
 * map for telling Control which box they unticked on the way through - and a
 * constant expression may not fold one out of the other, so this is what keeps
 * the two agreeing.
 */
class DiscordApiTest extends TestCase
{
    public function test_the_named_permissions_and_the_invite_bitfield_agree(): void
    {
        $folded = array_reduce(
            DiscordApi::REQUIRED_PERMISSIONS,
            fn (int $carry, int $bit): int => $carry | $bit,
            0,
        );

        $this->assertSame(
            DiscordApi::BOT_PERMISSIONS,
            $folded,
            'REQUIRED_PERMISSIONS and BOT_PERMISSIONS have drifted apart.',
        );
    }

    /**
     * The four that are nothing to do with managing a server, and are the whole
     * reason a private category works: Discord applies only the overwrite bits
     * the caller holds itself, so a bot that cannot speak cannot grant Speak.
     */
    public function test_the_bot_asks_for_the_permissions_it_has_to_hand_out(): void
    {
        foreach ([
            'View Channels' => DiscordApi::VIEW_CHANNEL,
            'Send Messages' => DiscordApi::SEND_MESSAGES,
            'Connect' => DiscordApi::CONNECT,
            'Speak' => DiscordApi::SPEAK,
        ] as $name => $bit) {
            $this->assertSame(
                $bit,
                DiscordApi::BOT_PERMISSIONS & $bit,
                "The blueprint grants {$name} in a channel overwrite, so the bot has to hold it.",
            );
        }
    }
}
