<?php

namespace mputkowski\DiscordAlerts\Support;

class Mention
{
    /**
     * Turn 'role:123', 'user:123', '123' (role), 'everyone' or 'here' into message content
     * plus an allowed_mentions object that pings only that target. Returns null if invalid.
     */
    public static function parse(mixed $value): ?array
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = strtolower(trim((string) $value));

        if ($value === 'everyone' || $value === 'here') {
            return [
                'content' => '@' . $value,
                'allowed_mentions' => ['parse' => ['everyone']],
            ];
        }

        if (preg_match('/^(?:(role|user):)?(\d+)$/', $value, $m)) {
            $type = $m[1] === 'user' ? 'user' : 'role';
            $id = $m[2];

            return $type === 'user'
                ? ['content' => '<@' . $id . '>', 'allowed_mentions' => ['parse' => [], 'users' => [$id]]]
                : ['content' => '<@&' . $id . '>', 'allowed_mentions' => ['parse' => [], 'roles' => [$id]]];
        }

        return null;
    }
}
