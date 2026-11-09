<?php

namespace Rss\Vlog\Core\Search;

use Rss\Vlog\Core\Exceptions\InvalidSearchPatternException;

/**
 * Matches log text against a plain search term or a regular expression.
 *
 * Log files can contain invalid UTF-8, which Unicode patterns can't match,
 * so each pattern has a byte-wise fallback.
 */
final class TextMatcher
{
    /**
     * Wraps regular expressions. A control character is used so patterns can
     * contain "/", "#" and "~" without escaping.
     */
    private const DELIMITER = "\x01";

    private ?string $term = null;

    private bool $caseSensitive;

    private ?string $unicodePattern = null;

    private ?string $bytePattern = null;

    private function __construct(bool $caseSensitive)
    {
        $this->caseSensitive = $caseSensitive;
    }

    /**
     * Match text that contains a term.
     */
    public static function plain(string $term, bool $caseSensitive = false): self
    {
        $matcher = new self($caseSensitive);
        $matcher->term = $term;

        // Case-insensitive matching of non-ASCII terms needs Unicode case folding.
        if (! $caseSensitive && preg_match('/[^\x00-\x7F]/', $term) === 1) {
            $matcher->unicodePattern = self::DELIMITER.preg_quote($term, self::DELIMITER).self::DELIMITER.'iu';
        }

        return $matcher;
    }

    /**
     * Match text against a regular expression, written without delimiters.
     *
     * @throws InvalidSearchPatternException
     */
    public static function regex(string $pattern, bool $caseSensitive = false): self
    {
        $matcher = new self($caseSensitive);
        $wrapped = self::DELIMITER.$pattern.self::DELIMITER.($caseSensitive ? '' : 'i');

        $unicodeError = self::compileError($wrapped.'u');
        $byteError = self::compileError($wrapped);

        if ($unicodeError !== null && $byteError !== null) {
            throw InvalidSearchPatternException::fromCompilerError($unicodeError);
        }

        $matcher->unicodePattern = $unicodeError === null ? $wrapped.'u' : null;
        $matcher->bytePattern = $byteError === null ? $wrapped : null;

        return $matcher;
    }

    /**
     * Determine whether the text matches.
     *
     * A regular expression that fails while matching, such as by exceeding
     * PCRE's backtracking limit, doesn't match.
     */
    public function matches(string $text): bool
    {
        if ($this->unicodePattern !== null) {
            $result = preg_match($this->unicodePattern, $text);

            if ($result !== false) {
                return $result === 1;
            }
        }

        if ($this->bytePattern !== null) {
            return preg_match($this->bytePattern, $text) === 1;
        }

        if ($this->term === null) {
            return false;
        }

        return $this->caseSensitive
            ? strpos($text, $this->term) !== false
            : stripos($text, $this->term) !== false;
    }

    /**
     * Get the compiler's error for a pattern, or null when it compiles.
     */
    private static function compileError(string $pattern): ?string
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
