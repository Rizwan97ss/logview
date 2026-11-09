<?php

namespace Rss\Vlog\Core\Security;

use InvalidArgumentException;
use Rss\Vlog\Core\Support\Regex;

/**
 * Hides secrets in log entries before they're sent to the browser.
 *
 * Context values are hidden when their key looks sensitive, such as
 * "password" or "api_key". Text is checked for common secret formats, such
 * as "password=...", bearer tokens, JWTs and well-known API key prefixes.
 *
 * Hiding is a safeguard against showing secrets on screen. It can't find
 * every secret, so logs should still not contain them.
 */
final class Redactor
{
    public const PLACEHOLDER = '[redacted]';

    /**
     * Key fragments that mark a context value as secret wherever they appear
     * in the key, compared without case, spaces, dashes or underscores.
     */
    private const SECRET_KEY_FRAGMENTS = [
        'password',
        'passwd',
        'secret',
        'token',
        'apikey',
        'authorization',
        'cookie',
        'privatekey',
        'creditcard',
        'cardnumber',
        'sessionid',
    ];

    /**
     * Short keys that are only secret when they're the whole key, since they
     * also appear inside unrelated words, such as "auth" in "author".
     */
    private const SECRET_KEYS = ['pwd', 'pass', 'auth', 'cvv', 'cvc', 'ssn', 'pin', 'session'];

    /**
     * Patterns for secrets written in text, with their replacements.
     */
    private const TEXT_PATTERNS = [
        // Private key blocks.
        '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s' => self::PLACEHOLDER,
        // Passwords in URLs, such as "mysql://user:password@host".
        '#\b([a-z][a-z0-9+.-]*://[^:/\s@]+):[^@/\s]+@#i' => '$1:'.self::PLACEHOLDER.'@',
        // Authorization header values.
        '/\b(Bearer|Basic)\s+[A-Za-z0-9\-._~+\/]+=*/i' => '$1 '.self::PLACEHOLDER,
        // JSON Web Tokens.
        '/\beyJ[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]+/' => self::PLACEHOLDER,
        // Secrets written as "key=value" or "key": "value", skipping values
        // already hidden and authorization schemes, which the pattern above
        // has handled.
        '/(?<![A-Za-z])((?:password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key|client[_-]?secret|authorization)["\']?\s*[:=]\s*["\']?)(?!\[redacted\])(?!(?:Bearer|Basic)\s)([^\s"\'&,;}]+)/i' => '$1'.self::PLACEHOLDER,
        // AWS access key IDs.
        '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/' => self::PLACEHOLDER,
        // GitHub tokens.
        '/\bgh[pousr]_[A-Za-z0-9]{36,}\b/' => self::PLACEHOLDER,
        // Stripe secret and restricted keys.
        '/\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{10,}\b/' => self::PLACEHOLDER,
        // Slack tokens.
        '/\bxox[abposr]-[A-Za-z0-9-]{10,}/' => self::PLACEHOLDER,
    ];

    private bool $enabled;

    /**
     * @var list<string>
     */
    private array $keyFragments;

    /**
     * @var array<string, string>
     */
    private array $patterns;

    /**
     * @param  list<string>  $extraKeys  More context keys to hide, matched like the built-in key fragments.
     * @param  list<string>  $extraPatterns  More regular expressions, with delimiters, whose matches are hidden.
     *
     * @throws InvalidArgumentException When an extra pattern isn't a valid regular expression.
     */
    public function __construct(bool $enabled = true, array $extraKeys = [], array $extraPatterns = [])
    {
        $this->enabled = $enabled;
        $this->keyFragments = array_merge(self::SECRET_KEY_FRAGMENTS, array_map([self::class, 'normalizeKey'], $extraKeys));
        $this->patterns = self::TEXT_PATTERNS;

        foreach ($extraPatterns as $pattern) {
            if (Regex::compileError($pattern) !== null) {
                throw new InvalidArgumentException("The redaction pattern [{$pattern}] is not a valid regular expression.");
            }

            $this->patterns[$pattern] = self::PLACEHOLDER;
        }
    }

    /**
     * Hide secrets written in text.
     */
    public function text(string $text): string
    {
        if (! $this->enabled || $text === '') {
            return $text;
        }

        foreach ($this->patterns as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $text);

            // A pattern can fail on invalid UTF-8 or a very large text. Keep
            // going with the other patterns rather than losing the text.
            if (is_string($result)) {
                $text = $result;
            }
        }

        return $text;
    }

    /**
     * Hide secrets in context data: values under secret keys, and secrets
     * written in any text.
     *
     * @param  array<array-key, mixed>|null  $context
     * @return array<array-key, mixed>|null
     */
    public function context(?array $context): ?array
    {
        if (! $this->enabled || $context === null) {
            return $context;
        }

        $redacted = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSecretKey($key)) {
                $redacted[$key] = self::PLACEHOLDER;
            } elseif (is_array($value)) {
                $redacted[$key] = $this->context($value);
            } elseif (is_string($value)) {
                $redacted[$key] = $this->text($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    private function isSecretKey(string $key): bool
    {
        $normalized = self::normalizeKey($key);

        if (in_array($normalized, self::SECRET_KEYS, true)) {
            return true;
        }

        foreach ($this->keyFragments as $fragment) {
            if ($fragment !== '' && strpos($normalized, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeKey(string $key): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $key));
    }
}
