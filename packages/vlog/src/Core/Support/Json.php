<?php

namespace Rss\Vlog\Core\Support;

/**
 * Decodes JSON written into log files.
 */
final class Json
{
    /**
     * Decode a JSON object or array, or return null when the text isn't one.
     *
     * Monolog's line formatter writes line breaks inside JSON strings as real
     * line breaks, so they're escaped again before a second attempt.
     *
     * @return array<array-key, mixed>|null
     */
    public static function decodeArray(string $json): ?array
    {
        $json = trim($json);

        if ($json === '' || ($json[0] !== '{' && $json[0] !== '[')) {
            return null;
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded) && strpbrk($json, "\r\n") !== false) {
            $decoded = json_decode(str_replace(["\r\n", "\r", "\n"], '\n', $json), true);
        }

        return is_array($decoded) ? $decoded : null;
    }
}
