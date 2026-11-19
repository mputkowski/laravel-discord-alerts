<?php

namespace mputkowski\DiscordAlerts\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use mputkowski\DiscordAlerts\Discord\Client;
use mputkowski\DiscordAlerts\DiscordAlertsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @var array requests sent through the fake Discord client */
    protected array $history = [];

    protected function getPackageProviders($app): array
    {
        return [DiscordAlertsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('discord-alerts.bot_token', 'secret-token');
        $app['config']->set('discord-alerts.channel_id', '111');
        $app['config']->set('discord-alerts.channels', ['billing' => '222']);
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('cache.default', 'array');
    }

    /**
     * Replace the Discord client with one backed by a Guzzle MockHandler.
     * Must be called before the first use of the facade.
     */
    protected function fakeDiscord(?array $responses = null): void
    {
        $mock = new MockHandler($responses === null ? array_fill(0, 20, new \GuzzleHttp\Psr7\Response(200)) : $responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        $this->app->singleton(Client::class, function () use ($stack) {
            return new Client(new GuzzleClient(['handler' => $stack]), 'secret-token', 3.0);
        });
    }

    protected function payload(int $index = 0): array
    {
        return json_decode((string) $this->history[$index]['request']->getBody(), true);
    }
}
