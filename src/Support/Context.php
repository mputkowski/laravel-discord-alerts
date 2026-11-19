<?php

namespace mputkowski\DiscordAlerts\Support;

use Illuminate\Contracts\Foundation\Application;
use mputkowski\DiscordAlerts\Formatting\Truncator;
use Throwable;

/**
 * Builds the request / console context lines of an exception report.
 * Never includes request bodies, headers, cookies or query strings.
 */
class Context
{
    public function __construct(
        private readonly Application $app,
        private readonly ?object $job = null,
    ) {
    }

    /**
     * @return string[]
     */
    public function lines(?bool $console = null): array
    {
        $console ??= $this->app->runningInConsole();

        return $console ? $this->consoleLines() : $this->httpLines();
    }

    private function httpLines(): array
    {
        if (!$this->app->bound('request')) {
            return [];
        }

        $request = $this->app->make('request');
        $lines = ['**Request:** `' . $request->method() . ' ' . str_replace('`', "'", $request->url()) . '`'];

        $user = $this->userId();
        if ($user !== null) {
            $lines[] = '**User:** ' . Truncator::escape($user);
        }

        if ($request->ip()) {
            $lines[] = '**IP:** ' . Truncator::escape($request->ip());
        }

        return $lines;
    }

    private function consoleLines(): array
    {
        $lines = [];
        $argv = $_SERVER['argv'] ?? [];

        // Only the script and, for artisan, the command name: other arguments and options can carry secrets.
        if ($argv) {
            $command = [basename($argv[0])];

            if ($command[0] === 'artisan') {
                foreach (array_slice($argv, 1) as $arg) {
                    if ($arg !== '' && $arg[0] !== '-') {
                        $command[] = $arg;
                        break;
                    }
                }
            }

            $lines[] = '**Command:** `' . str_replace('`', "'", 'php ' . implode(' ', $command)) . '`';
        }

        if ($this->job) {
            try {
                $lines[] = '**Job:** `' . $this->job->resolveName() . '`';
                $lines[] = '**Queue:** ' . Truncator::escape($this->job->getQueue());
            } catch (Throwable $e) {
                // job details are optional
            }
        }

        return $lines;
    }

    private function userId(): ?string
    {
        try {
            if (!$this->app->bound('auth')) {
                return null;
            }

            $id = $this->app->make('auth')->guard()->id();

            return $id === null ? null : (string) $id;
        } catch (Throwable $e) {
            return null;
        }
    }
}
