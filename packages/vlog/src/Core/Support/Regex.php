<?php

namespace Rss\Vlog\Core\Support;

/**
 * Checks regular expressions without raising PHP warnings, which host
 * applications might report as errors.
 */
final class Regex
{
    /**
     * Get the compiler's error for a pattern, or null when it compiles.
     */
    public static function compileError(string $pattern): ?string
    {
        $error = null;

        set_error_handler(function (int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            $result = preg_match($pattern, '');
        } finally {
            restore_error_handler();
        }

        if ($result === false && $error === null) {
            $error = 'PCRE error code '.preg_last_error();
        }

        return $error;
    }
}
