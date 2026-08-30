<?php

namespace App\Support;

/**
 * The one place a Discord handle is reduced to the form claims are matched on.
 *
 * Characters and Control seats are both reserved by handle, so both normalise
 * the same way: a handle typed onto one and then onto the other has to match
 * the same account.
 */
class DiscordHandle
{
    /**
     * Control types these off a sign-up sheet, so they arrive with stray
     * whitespace, a leading "@", and inconsistent case. Modern Discord handles
     * are lowercase, but the legacy "Name#1234" form is preserved as typed
     * beyond the case fold, since the discriminator is part of the handle.
     */
    public static function normalise(?string $handle): ?string
    {
        $handle = mb_strtolower(trim((string) $handle));
        $handle = ltrim($handle, '@');

        return $handle === '' ? null : $handle;
    }
}
