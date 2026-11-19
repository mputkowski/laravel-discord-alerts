<?php

namespace mputkowski\DiscordAlerts\Support;

use Illuminate\Contracts\Cache\Factory;
use Throwable;

class Dedupe
{
    private readonly bool $enabled;
    private readonly int $ttl;
    private readonly ?string $store;

    public function __construct(private readonly Factory $cache, array $config)
    {
        $this->enabled = (bool) ($config['enabled'] ?? true);
        $this->ttl = (int) ($config['ttl'] ?? 300);
        $this->store = $config['store'] ?? null;
    }

    /**
     * True when the exception has not been seen within the ttl and should be sent.
     * Fails open: if the cache is unavailable the alert is sent anyway.
     */
    public function shouldSend(Throwable $e): bool
    {
        if (!$this->enabled || $this->ttl <= 0) {
            return true;
        }

        try {
            return (bool) $this->cache->store($this->store)->add($this->key($e), 1, $this->ttl);
        } catch (Throwable $cacheError) {
            return true;
        }
    }

    /**
     * Release the exception so a failed delivery does not suppress the next attempt.
     */
    public function forget(Throwable $e): void
    {
        try {
            $this->cache->store($this->store)->forget($this->key($e));
        } catch (Throwable $cacheError) {
            // nothing to release if the cache is unavailable
        }
    }

    private function key(Throwable $e): string
    {
        // Quoted values and numbers are dynamic (ids, bindings, timestamps); drop them so
        // the same failure with different data still dedupes.
        $message = preg_replace(['/\'[^\']*\'|"[^"]*"/', '/\d+/'], '?', $e->getMessage());

        return 'discord-alerts:' . sha1($e::class . '|' . $e->getFile() . '|' . $e->getLine() . '|' . $message);
    }
}
