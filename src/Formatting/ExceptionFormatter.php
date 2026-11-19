<?php

namespace mputkowski\DiscordAlerts\Formatting;

use Throwable;

class ExceptionFormatter
{
    const MESSAGE_LIMIT = 1000;

    private $basePath;
    private $frames;

    public function __construct($basePath, $frames = 5)
    {
        $this->basePath = rtrim((string) $basePath, '/\\');
        $this->frames = max(0, (int) $frames);
    }

    /**
     * @param string[] $contextLines pre-formatted markdown lines (request / console context)
     */
    public function format(Throwable $e, $footer, array $contextLines = [])
    {
        $class = explode("\0", get_class($e))[0];
        $short = class_basename($class);

        $lines = [];
        if (strpos($class, '\\') !== false) {
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
    private function redact($message)
    {
        // Illuminate QueryException appends the SQL with bound values.
        $message = preg_replace('/\s*\((?:Connection: [^,)]*, )?SQL: .*$/s', ' (SQL redacted)', $message);
        // Credentials in URLs / DSNs.
        $message = preg_replace('#(://)[^/\s:@]+:[^/\s@]+@#', '$1[redacted]@', $message);
        // key=value or key: value for sensitive names, and bearer tokens.
        $message = preg_replace('/\b((?:password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key)\w*\s*[=:]\s*)[^\s,;&)]+/i', '$1[redacted]', $message);

        return preg_replace('/\b(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i', '$1[redacted]', $message);
    }

    private function trace(Throwable $e)
    {
        $frames = $e->getTrace();
        $out = [];

        foreach (array_slice($frames, 0, $this->frames) as $i => $frame) {
            $call = (isset($frame['class']) ? $frame['class'] . $frame['type'] : '') . $frame['function'] . '()';
            $where = isset($frame['file']) ? $this->relative($frame['file']) . ':' . (isset($frame['line']) ? $frame['line'] : '?') : '[internal function]';
            $out[] = str_replace('```', "'''", '#' . $i . ' ' . $where . ' ' . $call);
        }

        if (count($frames) > $this->frames) {
            $out[] = '… ' . (count($frames) - $this->frames) . ' more frames';
        }

        return implode("\n", $out);
    }

    private function relative($path)
    {
        if ($this->basePath !== '' && strpos($path, $this->basePath . DIRECTORY_SEPARATOR) === 0) {
            return substr($path, strlen($this->basePath) + 1);
        }

        return $path;
    }
}
