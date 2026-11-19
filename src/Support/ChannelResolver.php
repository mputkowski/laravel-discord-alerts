<?php

namespace mputkowski\DiscordAlerts\Support;

class ChannelResolver
{
    public function __construct(
        private readonly string $default,
        private readonly array $channels = [],
    ) {
    }

    /**
     * Resolve an alias, a raw channel ID, or the default channel. Returns '' when unknown.
     */
    public function resolve(mixed $channel = null): string
    {
        if ($channel === null || $channel === '') {
            return $this->default;
        }

        $channel = (string) $channel;

        if (array_key_exists($channel, $this->channels)) {
            return (string) $this->channels[$channel];
        }

        return $channel;
    }
}
