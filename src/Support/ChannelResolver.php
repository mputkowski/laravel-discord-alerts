<?php

namespace mputkowski\DiscordAlerts\Support;

class ChannelResolver
{
    private $default;
    private $channels;

    public function __construct($default, array $channels = [])
    {
        $this->default = $default;
        $this->channels = $channels;
    }

    /**
     * Resolve an alias, a raw channel ID, or the default channel. Returns '' when unknown.
     */
    public function resolve($channel = null): string
    {
        if ($channel === null || $channel === '') {
            return (string) $this->default;
        }

        $channel = (string) $channel;

        if (array_key_exists($channel, $this->channels)) {
            return (string) $this->channels[$channel];
        }

        return $channel;
    }
}
