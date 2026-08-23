<?php

namespace App\Enums;

/**
 * How the last attempt to push a player's roles into a guild ended.
 */
enum DiscordSyncStatus: string
{
    case Synced = 'synced';
    case NotAMember = 'not_a_member';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Synced => 'Roles synced',
            self::NotAMember => 'Not in the server',
            self::Failed => 'Sync failed',
        };
    }
}
