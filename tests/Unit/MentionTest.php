<?php

namespace mputkowski\DiscordAlerts\Tests\Unit;

use mputkowski\DiscordAlerts\Support\ChannelResolver;
use mputkowski\DiscordAlerts\Support\Mention;
use PHPUnit\Framework\TestCase;

class MentionTest extends TestCase
{
    public function test_role_user_and_bare_id()
    {
        $this->assertSame('<@&123>', Mention::parse('role:123')['content']);
        $this->assertSame('<@&123>', Mention::parse('123')['content']);
        $this->assertSame(['parse' => [], 'roles' => ['123']], Mention::parse('123')['allowed_mentions']);
        $this->assertSame('<@456>', Mention::parse('user:456')['content']);
        $this->assertSame(['parse' => [], 'users' => ['456']], Mention::parse('user:456')['allowed_mentions']);
    }

    public function test_everyone_and_here()
    {
        $this->assertSame('@everyone', Mention::parse('everyone')['content']);
        $this->assertSame('@here', Mention::parse('here')['content']);
        $this->assertSame(['parse' => ['everyone']], Mention::parse('here')['allowed_mentions']);
    }

    public function test_invalid_values_yield_null()
    {
        $this->assertNull(Mention::parse(null));
        $this->assertNull(Mention::parse(''));
        $this->assertNull(Mention::parse('role:abc'));
    }

    public function test_channel_resolver()
    {
        $resolver = new ChannelResolver('111', ['billing' => '222']);

        $this->assertSame('111', $resolver->resolve());
        $this->assertSame('222', $resolver->resolve('billing'));
        $this->assertSame('333', $resolver->resolve('333'));
    }
}
