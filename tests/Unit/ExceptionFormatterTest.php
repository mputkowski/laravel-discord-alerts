<?php

namespace mputkowski\DiscordAlerts\Tests\Unit;

use mputkowski\DiscordAlerts\Formatting\ExceptionFormatter;
use PHPUnit\Framework\TestCase;

class ExceptionFormatterTest extends TestCase
{
    public function test_namespaced_exception_layout(): void
    {
        $formatter = new ExceptionFormatter(dirname(__DIR__, 2), 2);
        $embed = $formatter->format(new \InvalidArgumentException('Bad *value*'), '15:13 19.04.2026', ['**Command:** `php artisan x`']);

        $this->assertSame('🚨 InvalidArgumentException', $embed['title']);
        $this->assertSame('15:13 19.04.2026', $embed['footer']['text']);
        $this->assertArrayNotHasKey('timestamp', $embed);
        $this->assertStringContainsString('**Message:** Bad \*value\*', $embed['description']);
        $this->assertStringContainsString('**Location:** `tests/Unit/ExceptionFormatterTest.php:', $embed['description']);
        $this->assertStringContainsString('**Command:** `php artisan x`', $embed['description']);
        $this->assertStringContainsString('```php', $embed['description']);
    }

    public function test_global_class_skips_class_line_and_namespaced_shows_it(): void
    {
        $formatter = new ExceptionFormatter(__DIR__, 5);

        $this->assertStringNotContainsString('**Class:**', $formatter->format(new \Exception('x'), 'f')['description']);
        $this->assertStringContainsString('**Class:** `mputkowski\\', $formatter->format(new FakeException('x'), 'f')['description']);
    }

    public function test_secrets_in_message_are_redacted(): void
    {
        $formatter = new ExceptionFormatter(__DIR__, 1);
        $message = 'SQLSTATE[HY000] mysql://root:hunter2@db/app password=hunter3 (Connection: mysql, SQL: select * from users where email = a@b.c)';
        $description = $formatter->format(new \Exception($message), 'f')['description'];

        $this->assertStringNotContainsString('hunter', $description);
        $this->assertStringNotContainsString('a@b.c', $description);
        $this->assertStringContainsString('SQL redacted', $description);
    }

    public function test_trace_is_cut_with_more_frames_line(): void
    {
        $formatter = new ExceptionFormatter(__DIR__, 1);
        $embed = $formatter->format($this->deep(3), 'f');

        $this->assertSame(1, preg_match('/… \d+ more frames/u', $embed['description']));
    }

    private function deep(int $n): \Exception
    {
        return $n === 0 ? new \Exception('deep') : $this->deep($n - 1);
    }
}

class FakeException extends \Exception
{
}
