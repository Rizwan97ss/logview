<?php

namespace Rss\Vlog\Core\Exceptions;

use RuntimeException;

/**
 * Thrown when a log file's index can't be stored.
 */
final class IndexException extends RuntimeException
{
    public static function directoryNotWritable(string $directory): self
    {
        return new self("The index directory [{$directory}] could not be created or is not writable.");
    }

    public static function fileNotWritable(string $path): self
    {
        return new self("The index file [{$path}] could not be written.");
    }
}
