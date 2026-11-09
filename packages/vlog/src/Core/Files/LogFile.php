<?php

namespace Rss\Vlog\Core\Files;

/**
 * A log file found in one of the allowed log folders.
 */
final class LogFile
{
    private string $id;

    private string $path;

    private string $relativePath;

    private string $folder;

    private int $size;

    private int $modifiedAt;

    /**
     * @param  string  $id  An opaque identifier that's safe to put in URLs.
     * @param  string  $path  The file's absolute path.
     * @param  string  $relativePath  The path inside its log folder, using "/" separators.
     * @param  string  $folder  The name of the log folder the file is in.
     */
    public function __construct(string $id, string $path, string $relativePath, string $folder, int $size, int $modifiedAt)
    {
        $this->id = $id;
        $this->path = $path;
        $this->relativePath = $relativePath;
        $this->folder = $folder;
        $this->size = $size;
        $this->modifiedAt = $modifiedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get the absolute path. It's never sent to the browser.
     */
    public function path(): string
    {
        return $this->path;
    }

    public function relativePath(): string
    {
        return $this->relativePath;
    }

    public function name(): string
    {
        return basename($this->relativePath);
    }

    public function folder(): string
    {
        return $this->folder;
    }

    public function size(): int
    {
        return $this->size;
    }

    /**
     * Get when the file was last modified, as a Unix timestamp.
     */
    public function modifiedAt(): int
    {
        return $this->modifiedAt;
    }
}
