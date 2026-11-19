# Laravel Discord Alerts

Send **exception reports** and **custom messages** from a Laravel application to a Discord text channel using an existing Discord bot. Each application posts to its own channel, configured entirely through environment variables.

- Rich embeds, colour-coded by level, with trace, request or console context
- Cache-based de-duplication of repeated exceptions
- Optional role, user, `@here` or `@everyone` mentions with tightly scoped `allowed_mentions`
- Safe by design: never throws into your application, never sends request bodies, headers, cookies or query strings
- Silent no-op when unconfigured, so local and CI environments need no changes

## Requirements

| | Supported |
|---|---|
| PHP | 8.2 – 8.5 |
| Laravel | 11 – 13 |

> **Upgrading from 0.x:** 1.0 requires PHP 8.2+ and Laravel 11+ (13 is supported). Stay on `^0.1` for older PHP or Laravel versions. The config file and environment variables are unchanged.

## Installation

This package is not published to Packagist. Add the repository to the consuming application's `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/mputkowski/laravel-discord-alerts.git" }
    ],
    "require": {
        "mputkowski/laravel-discord-alerts": "^1.0"
    }
}
```

```bash
composer update mputkowski/laravel-discord-alerts
```

Composer resolves versions from git tags.

The service provider and the `DiscordAlerts` facade alias are registered through package auto-discovery. To customise the configuration, publish it:

```bash
php artisan vendor:publish --tag=discord-alerts-config
```

## Discord setup

1. Use your existing bot and copy its token.
2. Create one text channel per application and grant the bot **View Channel**, **Send Messages** and **Embed Links** on it (permission integer `19456`, or `150528` with **Mention Everyone**).
3. Enable **Developer Mode** in Discord, right-click the channel and choose **Copy Channel ID**.
4. To ping a role, the role must be mentionable, or the bot needs the **Mention Everyone** permission (`131072`).

## Configuration

```dotenv
DISCORD_ALERTS_BOT_TOKEN=
DISCORD_ALERTS_CHANNEL_ID=
DISCORD_ALERTS_MENTION=        # optional, exception reports only
```

If the token or the channel ID is empty, nothing is sent.

`DISCORD_ALERTS_MENTION` accepts `role:123`, `user:123`, `everyone` or `here`. A bare ID is treated as a role.

| Option | Default | Description |
|---|---|---|
| `bot_token` | `env('DISCORD_ALERTS_BOT_TOKEN')` | Discord bot token |
| `channel_id` | `env('DISCORD_ALERTS_CHANNEL_ID')` | Default channel |
| `channels` | `[]` | Named channels, e.g. `'billing' => env('DISCORD_ALERTS_CHANNEL_BILLING')` |
| `mention` | `env('DISCORD_ALERTS_MENTION')` | Ping added to exception reports |
| `ignore` | `[]` | Extra exception classes that are never reported |
| `dedupe.enabled` | `true` | Suppress identical exceptions |
| `dedupe.ttl` | `300` | Seconds an identical exception (class, file, line, message with numbers and quoted values ignored) is suppressed |
| `dedupe.store` | `null` | Cache store, `null` for the default |
| `timeout` | `3` | Connect and request timeout in seconds |
| `trace_frames` | `5` | Trace frames included in the embed |
| `date_format` | `H:i d.m.Y` | PHP date format of the embed footer, in the app timezone |

## Usage

### Reporting exceptions

In `bootstrap/app.php`:

```php
use mputkowski\DiscordAlerts\Facades\DiscordAlerts;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->report(function (Throwable $e) {
        DiscordAlerts::report($e);
    });
})
```

The following are skipped automatically: exceptions your handler does not report (`$dontReport`), those listed in the `ignore` option, authentication, authorization, validation, model-not-found and token-mismatch exceptions, and HTTP exceptions with a 4xx status (other 5xx HTTP exceptions are reported, but 503, which is what maintenance mode throws, is skipped).

Each report contains the exception class, message, location, a trimmed stack trace and context: for HTTP requests the method, URL without query string, user ID and IP; for console commands the command name (arguments and options are omitted); for queue jobs the job class and queue.

### Custom messages

```php
DiscordAlerts::send('Deploy finished');

DiscordAlerts::send('Payment failed', [
    'channel' => 'billing',        // named channel or raw channel ID
    'level'   => 'error',          // info (default) | warning | error
    'title'   => 'Stripe webhook',
    'fields'  => [
        'Order'  => '#1234',
        'Amount' => '49.00 EUR',
    ],
    'mention' => 'role:123456',    // send() only pings when asked
]);
```

| Level | Colour | Title prefix |
|---|---|---|
| `info` | blue | none |
| `warning` | orange | 🟠 |
| `error` | red | 🚨 |

Message text, titles and fields are markdown-escaped. Both `report()` and `send()` return a boolean indicating whether Discord accepted the message and never throw.

### Test command

```bash
php artisan discord-alerts:test
```

Sends a test message to the default channel and reports whether it was delivered.

## Behaviour and limits

- Delivery is synchronous with a short timeout. Failed calls (including rate limits) are dropped, not retried.
- If the cache store is unavailable, de-duplication is skipped so the alert is still sent.
- Content is truncated to Discord's limits (4096-character description, 25 fields, 6000 characters per embed) without breaking code blocks.

## Testing

```bash
composer install
vendor/bin/phpunit
```

CI runs the suite against PHP 8.2 – 8.5 and Laravel 11 – 13.
