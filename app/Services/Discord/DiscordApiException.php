<?php

namespace App\Services\Discord;

use RuntimeException;

/**
 * A Discord REST call that did not succeed.
 *
 * Carries the HTTP status so callers can react to the cases that mean something
 * in particular — a 404 on a guild member is "they have not joined yet", not a
 * failure — rather than string matching on messages.
 */
class DiscordApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?int $discordCode = null,
    ) {
        parent::__construct($message, $status);
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    /**
     * Discord answers "the bot cannot touch this" with 403, which almost always
     * means a missing permission or a role above the bot in the hierarchy.
     */
    public function isForbidden(): bool
    {
        return $this->status === 403;
    }
}
