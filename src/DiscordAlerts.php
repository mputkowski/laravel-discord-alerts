<?php

namespace mputkowski\DiscordAlerts;

use DateTime;
use DateTimeZone;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use mputkowski\DiscordAlerts\Discord\Client;
use mputkowski\DiscordAlerts\Formatting\ExceptionFormatter;
use mputkowski\DiscordAlerts\Formatting\MessageFormatter;
use mputkowski\DiscordAlerts\Support\ChannelResolver;
use mputkowski\DiscordAlerts\Support\Context;
use mputkowski\DiscordAlerts\Support\Dedupe;
use mputkowski\DiscordAlerts\Support\Mention;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class DiscordAlerts
{
    const DEFAULT_IGNORE = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
    ];

    private $app;
    private $client;
    private $config;
    private $resolver;
    private $dedupe;
    private $reporting = false;
    private $currentJob;
    private $error;

    public function __construct(Container $app, Client $client, array $config, Dedupe $dedupe)
    {
        $this->app = $app;
        $this->client = $client;
        $this->config = $config;
        $this->dedupe = $dedupe;
        $this->resolver = new ChannelResolver(
            $this->config('channel_id'),
            (array) $this->config('channels', [])
        );
    }

    /**
     * Report an exception. Never throws; returns whether a message was delivered.
     */
    public function report(Throwable $exception): bool
    {
        if ($this->reporting) {
            return false;
        }

        $this->reporting = true;
        $claimed = false;
        $delivered = false;

        try {
            $channel = $this->resolver->resolve();

            if (!$this->configured($channel) || $this->ignored($exception) || !$this->dedupe->shouldSend($exception)) {
                return false;
            }

            $claimed = true;

            $formatter = new ExceptionFormatter($this->app->basePath(), $this->config('trace_frames', 5));
            $context = (new Context($this->app, $this->currentJob))->lines();

            $delivered = (bool) $this->post($channel, $formatter->format($exception, $this->footer(), $context), $this->config('mention'));

            return $delivered;
        } catch (Throwable $e) {
            return false;
        } finally {
            // Release the claim so a failed delivery does not suppress the next attempt.
            if ($claimed && !$delivered) {
                $this->dedupe->forget($exception);
            }

            $this->reporting = false;
            $this->currentJob = null;
        }
    }

    /**
     * Send a custom message. Never throws; returns whether a message was delivered.
     *
     * Options: channel, level (info|warning|error), title, fields, mention.
     */
    public function send($message, array $options = []): bool
    {
        $this->error = null;

        try {
            $channel = $this->resolver->resolve(isset($options['channel']) ? $options['channel'] : null);

            if (!$this->configured($channel)) {
                $this->error = (string) $this->config('bot_token') === ''
                    ? 'DISCORD_ALERTS_BOT_TOKEN is empty.'
                    : 'No channel ID configured (DISCORD_ALERTS_CHANNEL_ID).';

                return false;
            }

            $embed = (new MessageFormatter())->format($message, $options, $this->footer());
            $delivered = $this->post($channel, $embed, isset($options['mention']) ? $options['mention'] : null);

            if (!$delivered) {
                $this->error = $this->client->lastError();
            }

            return $delivered;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();

            return false;
        }
    }

    /**
     * Why the last send() was not delivered, or null if it was.
     */
    public function lastError()
    {
        return $this->error;
    }

    /**
     * Remember the queue job currently failing so reports can include it.
     */
    public function setCurrentJob($job)
    {
        $this->currentJob = $job;
    }

    private function post($channel, array $embed, $mention)
    {
        $mention = Mention::parse($mention);

        $payload = [
            'embeds' => [$embed],
            'allowed_mentions' => $mention ? $mention['allowed_mentions'] : ['parse' => []],
        ];

        if ($mention) {
            $payload['content'] = $mention['content'];
        }

        return $this->client->postMessage($channel, $payload);
    }

    private function configured($channel)
    {
        return (string) $this->config('bot_token') !== '' && (string) $channel !== '';
    }

    private function ignored(Throwable $e)
    {
        foreach (array_merge(self::DEFAULT_IGNORE, (array) $this->config('ignore', [])) as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        if ($e instanceof HttpExceptionInterface) {
            // The app handler's dont-report list covers every HttpException, so 5xx
            // (which we do want) must not be passed to it. 503 is skipped as well: it is
            // what maintenance mode throws (MaintenanceModeException on Laravel 6-9, a plain
            // HttpException on 10+), and it is expected rather than a failure.
            return $e->getStatusCode() < 500 || $e->getStatusCode() === 503;
        }

        try {
            $handler = $this->app->make(ExceptionHandler::class);

            return is_callable([$handler, 'shouldReport']) && !$handler->shouldReport($e);
        } catch (Throwable $handlerError) {
            return false;
        }
    }

    private function footer()
    {
        $timezone = $this->app->make('config')->get('app.timezone') ?: 'UTC';

        return (new DateTime('now', new DateTimeZone($timezone)))
            ->format($this->config('date_format', 'H:i d.m.Y'));
    }

    private function config($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
}
