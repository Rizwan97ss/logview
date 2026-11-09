<?php

namespace Rss\Vlog\Core\Support;

/**
 * The log levels Vlog understands, based on the PSR-3 standard.
 *
 * Laravel (Monolog) and CodeIgniter 3 and 4 all write these names, usually
 * in upper case, so every parser normalizes a raw level through this class.
 */
final class LogLevel
{
    public const EMERGENCY = 'emergency';

    public const ALERT = 'alert';

    public const CRITICAL = 'critical';

    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const NOTICE = 'notice';

    public const INFO = 'info';

    public const DEBUG = 'debug';

    /**
     * The name used for entries whose level isn't recognized.
     */
    public const UNKNOWN = 'unknown';

    /**
     * Each level's severity, where a higher number is more severe.
     *
     * @var array<string, int>
     */
    private const SEVERITIES = [
        self::DEBUG => 100,
        self::INFO => 200,
        self::NOTICE => 250,
        self::WARNING => 300,
        self::ERROR => 400,
        self::CRITICAL => 500,
        self::ALERT => 550,
        self::EMERGENCY => 600,
    ];

    /**
     * Other spellings that loggers commonly write for a level.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'warn' => self::WARNING,
        'err' => self::ERROR,
        'crit' => self::CRITICAL,
        'emerg' => self::EMERGENCY,
    ];

    /**
     * Get every level, from least to most severe.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::SEVERITIES);
    }

    /**
     * Convert a level as written in a log file to its standard name.
     *
     * Returns null when the value isn't a level Vlog recognizes.
     */
    public static function normalize(string $level): ?string
    {
        $level = strtolower(trim($level));

        if (isset(self::SEVERITIES[$level])) {
            return $level;
        }

        return self::ALIASES[$level] ?? null;
    }

    /**
     * Get the level for a Monolog severity number, such as 400 for "error".
     *
     * Returns null when the number isn't an exact Monolog severity.
     */
    public static function fromSeverity(int $severity): ?string
    {
        $level = array_search($severity, self::SEVERITIES, true);

        return $level === false ? null : $level;
    }

    /**
     * Determine whether a level is at least as severe as a minimum level.
     *
     * Unrecognized levels never meet the minimum.
     */
    public static function isAtLeast(string $level, string $minimum): bool
    {
        $level = self::normalize($level);
        $minimum = self::normalize($minimum);

        if ($level === null || $minimum === null) {
            return false;
        }

        return self::SEVERITIES[$level] >= self::SEVERITIES[$minimum];
    }
}
