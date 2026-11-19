<?php

namespace mputkowski\DiscordAlerts\Console;

use Illuminate\Console\Command;
use mputkowski\DiscordAlerts\DiscordAlerts;

class TestCommand extends Command
{
    protected $signature = 'discord-alerts:test {message=Test message from laravel-discord-alerts}';

    protected $description = 'Send a test message to the default Discord channel';

    public function handle(DiscordAlerts $alerts): int
    {
        if ($alerts->send($this->argument('message'), ['title' => 'Discord alerts test'])) {
            $this->info('Message sent.');

            return 0;
        }

        $this->error('Message was not delivered.');

        if ($alerts->lastError()) {
            $this->line($alerts->lastError());
        }

        return 1;
    }
}
