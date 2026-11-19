<?php

namespace mputkowski\DiscordAlerts;

use DateTime;
use DateTimeZone;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
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
    public const DEFAULT_IGNORE = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
    ];

    private readonly ChannelResolver $resolver;
    private bool $reporting = false;
    private ?object $currentJob = null;
    private ?string $error = null;

    public function __construct(
        private readonly Application $app,
        private readonly Client $client,
        private readonly array $config,
        private readonly Dedupe $dedupe,
    ) {
        $this->resolver = new ChannelResolver(
            (string) $this->config('channel_id'),
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

            $formatter = new ExceptionFormatter($this->app->basePath(), (int) $this->config('trace_frames', 5));
            $context = (new Context($this->app, $this->currentJob))->lines();

            $delivered = $this->post($channel, $formatter->format($exception, $this->footer(), $context), $this->config('mention'));

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
    public function send(mixed $message, array $options = []): bool
    {
        $this->error = null;

        try {
            $channel = $this->resolver->resolve($options['channel'] ?? null);

            if (!$this->configured($channel)) {
                $this->error = (string) $this->config('bot_token') === ''
                    ? 'DISCORD_ALERTS_BOT_TOKEN is empty.'
                    : 'No channel ID configured (DISCORD_ALERTS_CHANNEL_ID).';

                return false;
            }

            $embed = (new MessageFormatter())->format((string) $message, $options, $this->footer());
            $delivered = $this->post($channel, $embed, $options['mention'] ?? null);

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
    public function lastError(): ?string
    {
        return $this->error;
    }

    /**
     * Remember the queue job currently failing so reports can include it.
     */
    public function setCurrentJob(?object $job): void
    {
        $this->currentJob = $job;
    }

    private function post(string $channel, array $embed, mixed $mention): bool
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

    private function configured(string $channel): bool
    {
        return (string) $this->config('bot_token') !== '' && (string) $channel !== '';
    }

    private function ignored(Throwable $e): bool
    {
        foreach (array_merge(self::DEFAULT_IGNORE, (array) $this->config('ignore', [])) as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        if ($e instanceof HttpExceptionInterface) {
            // The app handler's dont-report list covers every HttpException, so 5xx
            // (which we do want) must not be passed to it. 503 is skipped as well: it is
            // what maintenance mode throws, and it is expected rather than a failure.
            return $e->getStatusCode() < 500 || $e->getStatusCode() === 503;
        }

        try {
            $handler = $this->app->make(ExceptionHandler::class);

            return !$handler->shouldReport($e);
        } catch (Throwable $handlerError) {
            return false;
        }
    }

    private function footer(): string
    {
        $timezone = $this->app->make('config')->get('app.timezone') ?: 'UTC';

        return (new DateTime('now', new DateTimeZone($timezone)))
            ->format($this->config('date_format', 'H:i d.m.Y'));
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
