<?php

namespace App\Enums;

/**
 * Where a game's guild provisioning run has got to.
 *
 * Provisioning is a queued job because it is dozens of rate-limited API calls,
 * so Control watches it through this rather than through the response to the
 * request that started it. A reset is the same shape of work in reverse, and
 * reports itself here for the same reason.
 */
enum DiscordProvisionStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Resetting = 'resetting';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Never provisioned',
            self::Queued => 'Queued',
            self::Running => 'Provisioning',
            self::Completed => 'Provisioned',
            self::Failed => 'Provisioning failed',
            self::Resetting => 'Clearing the server',
        };
    }

    public function isInProgress(): bool
    {
        return in_array($this, [self::Queued, self::Running, self::Resetting], true);
    }
}
