<?php

namespace Rss\Vlog\Core\Parsers;

use Rss\Vlog\Core\Entry\StackFrame;

/**
 * Extracts stack frames from the trace formats PHP frameworks write.
 */
final class StackTraceParser
{
    /**
     * Matches PHP's "#0 /path/file.php(12): call()" frames and CodeIgniter 4's
     * " 1 APPPATH/file.php(12): call()" frames.
     */
    private const FRAME = '/^\s*#?(\d+)\s+(?:(\{main\})|\[internal function\]|(.+?)\((\d+)\))(?::\s+(.*))?$/';

    /**
     * Matches a "file:line" location, as Monolog's JSON formatter writes them.
     */
    private const LOCATION = '/^(.+):(\d+)$/';

    /**
     * Extract frames from trace lines, ignoring lines that aren't frames.
     *
     * @return list<StackFrame>
     */
    public static function fromText(string $text): array
    {
        $frames = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (preg_match(self::FRAME, $line, $matches) !== 1) {
                continue;
            }

            $index = (int) $matches[1];
            $call = isset($matches[5]) && $matches[5] !== '' ? $matches[5] : null;

            if (($matches[2] ?? '') !== '') {
                $frames[] = new StackFrame($index, null, null, '{main}');
            } elseif (($matches[3] ?? '') !== '' && isset($matches[4])) {
                $frames[] = new StackFrame($index, $matches[3], (int) $matches[4], $call);
            } else {
                $frames[] = new StackFrame($index, null, null, $call);
            }
        }

        return $frames;
    }

    /**
     * Build frames from a list of "file:line" strings.
     *
     * @param  array<array-key, mixed>  $trace
     * @return list<StackFrame>
     */
    public static function fromLocations(array $trace): array
    {
        $frames = [];

        foreach (array_values($trace) as $index => $location) {
            if (! is_string($location)) {
                continue;
            }

            if (preg_match(self::LOCATION, $location, $matches) === 1) {
                $frames[] = new StackFrame($index, $matches[1], (int) $matches[2], null);
            } else {
                $frames[] = new StackFrame($index, $location, null, null);
            }
        }

        return $frames;
    }
}
