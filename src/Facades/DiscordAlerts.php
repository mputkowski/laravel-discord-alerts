<?php

namespace mputkowski\DiscordAlerts\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool report(\Throwable $exception)
 * @method static bool send(string $message, array $options = [])
 * @method static string|null lastError()
 *
 * @see \mputkowski\DiscordAlerts\DiscordAlerts
 */
class DiscordAlerts extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \mputkowski\DiscordAlerts\DiscordAlerts::class;
    }
}
