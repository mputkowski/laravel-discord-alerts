<?php

namespace mputkowski\DiscordAlerts\Formatting;

class MessageFormatter
{
    public const LEVELS = [
        'info' => ['color' => 0x3498DB, 'emoji' => ''],
        'warning' => ['color' => 0xE67E22, 'emoji' => '🟠'],
        'error' => ['color' => 0xE74C3C, 'emoji' => '🚨'],
    ];

    /**
     * Build an embed from send() arguments.
     */
    public function format(string $message, array $options, string $footer): array
    {
        $level = $options['level'] ?? 'info';
        if (!is_string($level) || !isset(self::LEVELS[$level])) {
            $level = 'info';
        }

        $emoji = self::LEVELS[$level]['emoji'];
        $title = trim((string) ($options['title'] ?? ''));
        $description = Truncator::escape($message);

        $embed = ['color' => self::LEVELS[$level]['color']];

        if ($title !== '') {
            $embed['title'] = trim($emoji . ' ' . Truncator::escape($title));
        } elseif ($emoji !== '') {
            $description = $emoji . ' ' . $description;
        }

        $embed['description'] = $description;

        $fields = [];
        foreach ((array) ($options['fields'] ?? []) as $name => $value) {
            $name = Truncator::escape((string) $name);
            $value = Truncator::escape($this->stringify($value));

            $fields[] = [
                'name' => $name === '' ? "\u{200B}" : $name,
                'value' => $value === '' ? "\u{200B}" : $value,
                'inline' => true,
            ];
        }
        if ($fields) {
            $embed['fields'] = $fields;
        }

        $embed['footer'] = ['text' => $footer];

        return Truncator::embed($embed);
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return (string) json_encode($value);
    }
}
