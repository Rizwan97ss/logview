<?php

namespace Rss\Vlog\Core\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a regular expression search pattern doesn't compile.
 */
final class InvalidSearchPatternException extends InvalidArgumentException
{
    public static function fromCompilerError(string $error): self
    {
        $reason = preg_replace('/^preg_match\(\):\s*/', '', $error);

        return new self('The search pattern is not a valid regular expression: '.($reason ?? $error));
    }
}
