<?php

namespace Rss\Vlog\Core\Exceptions;

use RuntimeException;

/**
 * Thrown when a log file can't be opened for reading.
 */
final class LogFileException extends RuntimeException
{
    public static function notReadable(string $path): self
    {
        return new self("The log file [{$path}] does not exist or is not readable.");
    }
}
