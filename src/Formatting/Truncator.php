<?php

namespace mputkowski\DiscordAlerts\Formatting;

class Truncator
{
    public const CONTENT = 2000;
    public const TITLE = 256;
    public const DESCRIPTION = 4096;
    public const FOOTER = 2048;
    public const FIELD_NAME = 256;
    public const FIELD_VALUE = 1024;
    public const FIELDS = 25;
    public const EMBED_TOTAL = 6000;

    public const MARKER = '… truncated';

    /**
     * Cut text to $max characters, appending a marker. An unclosed code fence is re-closed.
     */
    public static function limit(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        if ($max <= mb_strlen(self::MARKER)) {
            return mb_substr($text, 0, max(0, $max));
        }

        $fence = "\n```";
        $room = $max - mb_strlen(self::MARKER) - mb_strlen($fence) - 1;
        $cut = rtrim(mb_substr($text, 0, max(0, $room)));

        if (substr_count($cut, '```') % 2 === 1) {
            return $cut . "\n" . self::MARKER . $fence;
        }

        return $cut . ' ' . self::MARKER;
    }

    /**
     * Escape Discord markdown characters in text that is not inside a code block.
     */
    public static function escape(string $text): string
    {
        return preg_replace('/([\\\\*_`~|>])/', '\\\\$1', $text);
    }

    /**
     * Enforce Discord's per-part and total embed limits.
     */
    public static function embed(array $embed): array
    {
        if (isset($embed['title'])) {
            $embed['title'] = self::limit($embed['title'], self::TITLE);
        }
        if (isset($embed['description'])) {
            $embed['description'] = self::limit($embed['description'], self::DESCRIPTION);
        }
        if (isset($embed['footer']['text'])) {
            $embed['footer']['text'] = self::limit($embed['footer']['text'], self::FOOTER);
        }

        if (isset($embed['fields'])) {
            $fields = [];
            foreach (array_slice($embed['fields'], 0, self::FIELDS) as $field) {
                $field['name'] = self::limit($field['name'], self::FIELD_NAME);
                $field['value'] = self::limit($field['value'], self::FIELD_VALUE);
                $fields[] = $field;
            }
            $embed['fields'] = $fields;
        }

        while (self::size($embed) > self::EMBED_TOTAL && !empty($embed['fields'])) {
            array_pop($embed['fields']);
        }

        $excess = self::size($embed) - self::EMBED_TOTAL;
        if ($excess > 0 && isset($embed['description'])) {
            $embed['description'] = self::limit($embed['description'], max(0, mb_strlen($embed['description']) - $excess));
        }

        if (empty($embed['fields'])) {
            unset($embed['fields']);
        }

        return $embed;
    }

    private static function size(array $embed): int
    {
        $size = mb_strlen($embed['title'] ?? '')
            + mb_strlen($embed['description'] ?? '')
            + mb_strlen($embed['footer']['text'] ?? '');

        foreach ($embed['fields'] ?? [] as $field) {
            $size += mb_strlen($field['name']) + mb_strlen($field['value']);
        }

        return $size;
    }
}
