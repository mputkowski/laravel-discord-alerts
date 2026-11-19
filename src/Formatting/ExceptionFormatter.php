<?php

namespace mputkowski\DiscordAlerts\Formatting;

use Throwable;

class ExceptionFormatter
{
    public const MESSAGE_LIMIT = 1000;

    private readonly string $basePath;
    private readonly int $frames;

    public function __construct(string $basePath, int $frames = 5)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->frames = max(0, $frames);
    }

    /**
     * @param string[] $contextLines pre-formatted markdown lines (request / console context)
     */
    public function format(Throwable $e, string $footer, array $contextLines = []): array
    {
        $class = explode("\0", $e::class)[0];
        $short = class_basename($class);

        $lines = [];
        if (str_contains($class, '\\')) {
            $lines[] = '**Class:** `' . $class . '`';
        }

        $message = trim($e->getMessage());
        $lines[] = '**Message:** ' . ($message === '' ? '(none)' : Truncator::escape(Truncator::limit($this->redact($message), self::MESSAGE_LIMIT)));
        $lines[] = '**Location:** `' . $this->relative($e->getFile()) . ':' . $e->getLine() . '`';

        $description = implode("\n", array_merge($lines, $contextLines));

        $trace = $this->trace($e);
        if ($trace !== '') {
            $description .= "\n\n" . '```php' . "\n" . $trace . "\n" . '```';
        }

        return Truncator::embed([
            'title' => '🚨 ' . $short,
            'description' => $description,
            'color' => MessageFormatter::LEVELS['error']['color'],
            'footer' => ['text' => $footer],
        ]);
    }

    /**
     * Best-effort scrub of values that commonly end up in exception messages.
     */
    private function redact(string $message): string
    {
        // Illuminate QueryException appends the SQL with bound values.
        $message = preg_replace('/\s*\((?:Connection: [^,)]*, )?SQL: .*$/s', ' (SQL redacted)', $message);
        // Credentials in URLs / DSNs.
        $message = preg_replace('#(://)[^/\s:@]+:[^/\s@]+@#', '$1[redacted]@', $message);
        // key=value or key: value for sensitive names, and bearer tokens.
        $message = preg_replace('/\b((?:password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key)\w*\s*[=:]\s*)[^\s,;&)]+/i', '$1[redacted]', $message);

        return preg_replace('/\b(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i', '$1[redacted]', $message);
    }

    private function trace(Throwable $e): string
    {
        $frames = $e->getTrace();
        $out = [];

        foreach (array_slice($frames, 0, $this->frames) as $i => $frame) {
            $call = (isset($frame['class']) ? $frame['class'] . $frame['type'] : '') . $frame['function'] . '()';
            $where = isset($frame['file']) ? $this->relative($frame['file']) . ':' . ($frame['line'] ?? '?') : '[internal function]';
            $out[] = str_replace('```', "'''", '#' . $i . ' ' . $where . ' ' . $call);
        }

        if (count($frames) > $this->frames) {
            $out[] = '… ' . (count($frames) - $this->frames) . ' more frames';
        }

        return implode("\n", $out);
    }

    private function relative(string $path): string
    {
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($this->basePath) + 1);
        }

        return $path;
    }
}
