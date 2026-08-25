<?php

namespace App\Enums;

/**
 * The kinds of Discord object the application creates and then owns.
 */
enum DiscordResourceKind: string
{
    case Role = 'role';
    case Category = 'category';
    case TextChannel = 'text_channel';
    case VoiceChannel = 'voice_channel';
    case Webhook = 'webhook';
    case Message = 'message';

    public function label(): string
    {
        return match ($this) {
            self::Role => 'Role',
            self::Category => 'Category',
            self::TextChannel => 'Text channel',
            self::VoiceChannel => 'Voice channel',
            self::Webhook => 'Webhook',
            self::Message => 'Message',
        };
    }

    /**
     * Discord's numeric channel type, for the kinds that are channels.
     *
     * @see https://discord.com/developers/docs/resources/channel#channel-object-channel-types
     */
    public function channelType(): ?int
    {
        return match ($this) {
            self::TextChannel => 0,
            self::VoiceChannel => 2,
            self::Category => 4,
            default => null,
        };
    }
}
