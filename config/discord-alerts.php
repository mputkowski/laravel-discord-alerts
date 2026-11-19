<?php

return [
    'bot_token' => env('DISCORD_ALERTS_BOT_TOKEN'),
    'channel_id' => env('DISCORD_ALERTS_CHANNEL_ID'), // default channel

    // Named channels usable as send(..., ['channel' => 'billing'])
    'channels' => [
        // 'billing' => env('DISCORD_ALERTS_CHANNEL_BILLING'),
    ],

    // Applied to exception reports only. send() never pings unless asked.
    // Accepts role:123, user:123, everyone, here (a bare ID is treated as a role).
    'mention' => env('DISCORD_ALERTS_MENTION'),

    // Exceptions that are never reported, in addition to the built-in defaults
    // (authentication, authorization, validation, model not found, 4xx HTTP errors,
    // token mismatch) and the application's exception handler rules.
    'ignore' => [
        // App\Exceptions\SomethingHarmless::class,
    ],

    'dedupe' => [
        'enabled' => true,
        'ttl' => 300, // seconds; same exception (class + file + line + message) is sent once per ttl
        'store' => null, // null = default cache store
    ],

    'timeout' => 3, // seconds, connect + request
    'trace_frames' => 5, // frames included in the embed
    'date_format' => 'H:i d.m.Y', // PHP date() format of the embed footer, app timezone
];
