<?php

namespace mputkowski\DiscordAlerts;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\ServiceProvider;
use mputkowski\DiscordAlerts\Console\TestCommand;
use mputkowski\DiscordAlerts\Discord\Client;
use mputkowski\DiscordAlerts\Support\Dedupe;

class DiscordAlertsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/discord-alerts.php', 'discord-alerts');

        $this->app->singleton(Client::class, function ($app) {
            $config = $app['config']->get('discord-alerts');

            return new Client(new GuzzleClient(), $config['bot_token'], $config['timeout']);
        });

        $this->app->singleton(DiscordAlerts::class, function ($app) {
            $config = $app['config']->get('discord-alerts');

            return new DiscordAlerts(
                $app,
                $app->make(Client::class),
                $config,
                new Dedupe($app->make(CacheFactory::class), (array) $config['dedupe'])
            );
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/discord-alerts.php' => $this->app->configPath('discord-alerts.php'),
        ], 'discord-alerts-config');

        if ($this->app->runningInConsole()) {
            $this->commands([TestCommand::class]);
        }

        // Exceptions from queue jobs are reported after JobExceptionOccurred fires,
        // so remember the job to include it in the report. Only failing jobs pay
        // for resolving the manager.
        $this->app['events']->listen(JobExceptionOccurred::class, function ($event) {
            $this->app->make(DiscordAlerts::class)->setCurrentJob($event->job);
        });

        // A failing job that is never reported must not leak into later reports of a
        // long-lived worker, so drop it when the worker moves on.
        $forgetJob = function () {
            if ($this->app->resolved(DiscordAlerts::class)) {
                $this->app->make(DiscordAlerts::class)->setCurrentJob(null);
            }
        };
        $this->app['events']->listen(JobProcessing::class, $forgetJob);
        $this->app['events']->listen(Looping::class, $forgetJob);
    }
}
