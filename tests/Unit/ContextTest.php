<?php

namespace mputkowski\DiscordAlerts\Tests\Unit;

use Illuminate\Http\Request;
use mputkowski\DiscordAlerts\Support\Context;
use mputkowski\DiscordAlerts\Tests\TestCase;

class ContextTest extends TestCase
{
    public function test_http_context_strips_query_string()
    {
        $this->app->instance('request', Request::create('https://app.test/orders/5?token=abc', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']));

        $lines = (new Context($this->app))->lines(false);

        $this->assertSame('**Request:** `GET https://app.test/orders/5`', $lines[0]);
        $this->assertContains('**IP:** 10.0.0.1', $lines);
    }

    public function test_console_context_lists_command()
    {
        $lines = (new Context($this->app))->lines(true);

        $this->assertStringStartsWith('**Command:** `php ', $lines[0]);
    }

    public function test_console_context_omits_options_and_non_artisan_arguments()
    {
        $original = $_SERVER['argv'] ?? null;

        try {
            $_SERVER['argv'] = ['artisan', '--env=production', 'migrate', '--token=abc'];
            $this->assertSame('**Command:** `php artisan migrate`', (new Context($this->app))->lines(true)[0]);

            $_SERVER['argv'] = ['tool', '--token=abc'];
            $this->assertSame('**Command:** `php tool`', (new Context($this->app))->lines(true)[0]);
        } finally {
            $_SERVER['argv'] = $original;
        }
    }
}
