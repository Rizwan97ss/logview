<?php

namespace Rss\Vlog\Core\Reader;

use Rss\Vlog\Core\Exceptions\LogFileException;

/**
 * Opens log files for reading.
 */
final class LogFile
{
    /**
     * Open a file in binary read-only mode.
     *
     * @return resource
     *
     * @throws LogFileException
     */
    public static function open(string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw LogFileException::notReadable($path);
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw LogFileException::notReadable($path);
        }

        return $handle;
    }
}
