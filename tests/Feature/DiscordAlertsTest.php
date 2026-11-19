<?php

namespace mputkowski\DiscordAlerts\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Validation\ValidationException;
use mputkowski\DiscordAlerts\Facades\DiscordAlerts;
use mputkowski\DiscordAlerts\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DiscordAlertsTest extends TestCase
{
    public function test_send_posts_embed_with_bot_auth_to_default_channel()
    {
        $this->fakeDiscord();

        $this->assertTrue(DiscordAlerts::send('Deploy finished'));

        $request = $this->history[0]['request'];
        $this->assertSame('https://discord.com/api/v10/channels/111/messages', (string) $request->getUri());
        $this->assertSame('Bot secret-token', $request->getHeaderLine('Authorization'));

        $payload = $this->payload();
        $this->assertSame('Deploy finished', $payload['embeds'][0]['description']);
        $this->assertSame(['parse' => []], $payload['allowed_mentions']);
        $this->assertArrayNotHasKey('content', $payload);
        $this->assertSame(1, preg_match('/^\d{2}:\d{2} \d{2}\.\d{2}\.\d{4}$/', $payload['embeds'][0]['footer']['text']));
    }

    public function test_send_options()
    {
        $this->fakeDiscord();

        DiscordAlerts::send('Payment failed', [
            'channel' => 'billing',
            'level' => 'error',
            'title' => 'Stripe webhook',
            'fields' => ['Order' => '#1234', 'Amount' => '49.00 EUR'],
            'mention' => 'role:123456',
        ]);

        $this->assertStringContainsString('/channels/222/messages', (string) $this->history[0]['request']->getUri());

        $payload = $this->payload();
        $this->assertSame('<@&123456>', $payload['content']);
        $this->assertSame('🚨 Stripe webhook', $payload['embeds'][0]['title']);
        $this->assertCount(2, $payload['embeds'][0]['fields']);
        $this->assertTrue($payload['embeds'][0]['fields'][0]['inline']);
    }

    public function test_noop_when_unconfigured()
    {
        $this->app['config']->set('discord-alerts.bot_token', '');
        $this->fakeDiscord();

        $this->assertFalse(DiscordAlerts::send('x'));
        $this->assertFalse(DiscordAlerts::report(new \Exception('x')));
        $this->assertCount(0, $this->history);
    }

    public function test_noop_when_channel_empty()
    {
        $this->app['config']->set('discord-alerts.channel_id', '');
        $this->fakeDiscord();

        $this->assertFalse(DiscordAlerts::send('x'));
        $this->assertCount(0, $this->history);
    }

    public function test_never_throws_on_http_errors_or_timeouts()
    {
        $this->fakeDiscord([
            new Response(429),
            new Response(500),
            new Response(403),
            new ConnectException('timeout', new Request('POST', 'x')),
        ]);

        $this->assertFalse(DiscordAlerts::send('a'));
        $this->assertFalse(DiscordAlerts::send('b'));
        $this->assertFalse(DiscordAlerts::send('c'));
        $this->assertFalse(DiscordAlerts::send('d'));
    }

    public function test_last_error_explains_the_failure()
    {
        $this->fakeDiscord([
            new Response(403, [], '{"message": "Missing Access", "code": 50001}'),
            new ConnectException('timeout', new Request('POST', 'x')),
            new Response(200),
        ]);

        $this->assertFalse(DiscordAlerts::send('a'));
        $this->assertStringContainsString('HTTP 403: Missing Access (code 50001)', DiscordAlerts::lastError());

        $this->assertFalse(DiscordAlerts::send('b'));
        $this->assertStringContainsString('timeout', DiscordAlerts::lastError());

        $this->assertTrue(DiscordAlerts::send('c'));
        $this->assertNull(DiscordAlerts::lastError());
    }

    public function test_last_error_explains_missing_configuration()
    {
        $this->app['config']->set('discord-alerts.bot_token', '');
        $this->fakeDiscord();

        $this->assertFalse(DiscordAlerts::send('a'));
        $this->assertStringContainsString('DISCORD_ALERTS_BOT_TOKEN', DiscordAlerts::lastError());
    }

    public function test_report_sends_exception_embed_with_configured_mention()
    {
        $this->app['config']->set('discord-alerts.mention', 'role:999');
        $this->fakeDiscord();

        $this->assertTrue(DiscordAlerts::report(new \RuntimeException('Boom')));

        $payload = $this->payload();
        $this->assertSame('<@&999>', $payload['content']);
        $this->assertSame('🚨 RuntimeException', $payload['embeds'][0]['title']);
        $this->assertStringContainsString('**Message:** Boom', $payload['embeds'][0]['description']);
    }

    public function test_ignored_exceptions_are_skipped()
    {
        $this->app['config']->set('discord-alerts.ignore', [\DomainException::class]);
        $this->fakeDiscord();

        $this->assertFalse(DiscordAlerts::report(new NotFoundHttpException()));
        $this->assertFalse(DiscordAlerts::report(ValidationException::withMessages(['a' => 'b'])));
        $this->assertFalse(DiscordAlerts::report(new \DomainException('ignored by config')));
        $this->assertCount(0, $this->history);
    }

    public function test_dedupe_suppresses_second_identical_report()
    {
        $this->fakeDiscord();

        $make = function () {
            return new \RuntimeException('same');
        };

        $first = $make();
        $this->assertTrue(DiscordAlerts::report($first));
        $this->assertFalse(DiscordAlerts::report($first));
        $this->assertCount(1, $this->history);
    }

    public function test_failed_delivery_does_not_suppress_the_next_report()
    {
        $this->fakeDiscord([new Response(500), new Response(200)]);

        $e = new \RuntimeException('same');
        $this->assertFalse(DiscordAlerts::report($e));
        $this->assertTrue(DiscordAlerts::report($e));
    }

    public function test_5xx_http_exceptions_are_reported_except_maintenance_503()
    {
        $this->fakeDiscord();

        $this->assertTrue(DiscordAlerts::report(new \Symfony\Component\HttpKernel\Exception\HttpException(500, 'boom')));
        $this->assertFalse(DiscordAlerts::report(new \Symfony\Component\HttpKernel\Exception\HttpException(503, 'maintenance')));
    }

    public function test_dedupe_ignores_dynamic_values_in_message()
    {
        $this->fakeDiscord();

        $make = function ($id) {
            return new \RuntimeException("No record 'abc$id' with id $id");
        };

        $this->assertTrue(DiscordAlerts::report($make(1)));
        $this->assertFalse(DiscordAlerts::report($make(2)));
    }

    public function test_zero_field_values_are_kept()
    {
        $this->fakeDiscord();

        DiscordAlerts::send('Queue', ['fields' => ['Failed jobs' => 0]]);

        $this->assertSame('0', $this->payload()['embeds'][0]['fields'][0]['value']);
    }

    public function test_dedupe_can_be_disabled()
    {
        $this->app['config']->set('discord-alerts.dedupe.enabled', false);
        $this->fakeDiscord();

        $e = new \RuntimeException('same');
        $this->assertTrue(DiscordAlerts::report($e));
        $this->assertTrue(DiscordAlerts::report($e));
        $this->assertCount(2, $this->history);
    }

    public function test_report_never_includes_secrets_from_the_request()
    {
        $this->fakeDiscord();
        $this->app->instance('request', \Illuminate\Http\Request::create('https://app.test/orders/5?token=abc', 'POST', ['password' => 'hunter2']));

        DiscordAlerts::report(new \RuntimeException('x'));

        $body = json_encode($this->payload());
        $this->assertStringNotContainsString('hunter2', $body);
        $this->assertStringNotContainsString('token=abc', $body);
    }
}
