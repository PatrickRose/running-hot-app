<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discord' => [
        // OAuth credentials from the Discord Developer Portal application.
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI', '/auth/discord/callback'),

        // Note: there is deliberately no global announcement webhook. Each game
        // carries its own, so a game can never post to a channel by accident.

        // Bot credentials, for provisioning a game's guild and assigning roles.
        // This is a separate integration from OAuth and from the announcement
        // webhook: it is the only one that needs a bot in the guild, with
        // Manage Roles, Manage Channels, Manage Webhooks and Create Instant
        // Invite. Leave the token unset and every bot-backed feature simply
        // reports itself unconfigured rather than half working.
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'api_base' => env('DISCORD_API_BASE', 'https://discord.com/api/v10'),

        // Where Discord sends Control back after they pick a server to add the
        // bot to. Must be registered as a redirect in the Discord Developer
        // Portal, alongside the login one. Left unset it falls back to this
        // application's own route, which is right unless a proxy rewrites URLs.
        'bot_redirect' => env('DISCORD_BOT_REDIRECT_URI'),
    ],

];
