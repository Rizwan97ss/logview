<?php

namespace Rss\Vlog\Core\Support;

use DateTimeImmutable;
use Exception;

/**
 * Parses timestamps written into log files.
 */
final class Time
{
    /**
     * Parse a timestamp, or return null when it isn't a valid date.
     *
     * Timestamps without a time zone use PHP's default time zone.
     */
    public static function parse(string $value): ?DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            return null;
        }
    }
}
