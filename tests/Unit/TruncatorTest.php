<?php

namespace mputkowski\DiscordAlerts\Tests\Unit;

use mputkowski\DiscordAlerts\Formatting\Truncator;
use PHPUnit\Framework\TestCase;

class TruncatorTest extends TestCase
{
    public function test_short_text_is_untouched()
    {
        $this->assertSame('hello', Truncator::limit('hello', 10));
    }

    public function test_long_text_is_cut_with_marker_within_limit()
    {
        $result = Truncator::limit(str_repeat('a', 100), 50);

        $this->assertLessThanOrEqual(50, mb_strlen($result));
        $this->assertStringEndsWith('… truncated', $result);
    }

    public function test_open_code_fence_is_reclosed()
    {
        $result = Truncator::limit("```php\n" . str_repeat('x', 200), 80);

        $this->assertLessThanOrEqual(80, mb_strlen($result));
        $this->assertSame(0, substr_count($result, '```') % 2);
    }

    public function test_markdown_is_escaped()
    {
        $this->assertSame('\*a\* \_b\_ \`c\` \~d\~ \|e\| \>f', Truncator::escape('*a* _b_ `c` ~d~ |e| >f'));
    }

    public function test_embed_limits_are_enforced()
    {
        $fields = [];
        for ($i = 0; $i < 30; $i++) {
            $fields[] = ['name' => 'n', 'value' => str_repeat('v', 2000), 'inline' => true];
        }

        $embed = Truncator::embed([
            'title' => str_repeat('t', 500),
            'description' => str_repeat('d', 5000),
            'fields' => $fields,
        ]);

        $total = mb_strlen($embed['title']) + mb_strlen($embed['description']);
        foreach (isset($embed['fields']) ? $embed['fields'] : [] as $field) {
            $this->assertLessThanOrEqual(1024, mb_strlen($field['value']));
            $total += mb_strlen($field['name']) + mb_strlen($field['value']);
        }

        $this->assertLessThanOrEqual(256, mb_strlen($embed['title']));
        $this->assertLessThanOrEqual(25, count(isset($embed['fields']) ? $embed['fields'] : []));
        $this->assertLessThanOrEqual(6000, $total);
    }
}
