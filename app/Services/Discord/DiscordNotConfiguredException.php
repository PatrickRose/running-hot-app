<?php

namespace App\Services\Discord;

use RuntimeException;

/**
 * Thrown when a bot-backed feature is used without DISCORD_BOT_TOKEN set.
 *
 * Provisioning surfaces this to Control as a plain message; the login-time role
 * sync treats an unconfigured bot as "nothing to do" instead, because a player
 * signing in must never be blocked on an integration they cannot fix.
 */
class DiscordNotConfiguredException extends RuntimeException {}
