<?php

namespace Rss\Vlog\Core\Files;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Finds log files inside the allowed log folders.
 *
 * Files are looked up by ID rather than by a path from the request, so only
 * files this finder discovered can ever be opened. Files whose real path is
 * outside their folder, such as through a symbolic link, are ignored.
 */
final class LogFileFinder
{
    /**
     * @var list<string>
     */
    private array $directories;

    /**
     * @var list<string>
     */
    private array $patterns;

    private int $maxDepth;

    /**
     * @param  list<string>  $directories  The folders to search.
     * @param  list<string>  $patterns  File name patterns to include, such as "*.log".
     * @param  int  $maxDepth  How many levels of subfolders to search.
     */
    public function __construct(array $directories, array $patterns = ['*.log'], int $maxDepth = 3)
    {
        $this->directories = $directories;
        $this->patterns = $patterns;
        $this->maxDepth = max(0, $maxDepth);
    }

    /**
     * Get every log file, most recently modified first.
     *
     * @return list<LogFile>
     */
    public function all(): array
    {
        $files = [];

        foreach ($this->directories as $directory) {
            $root = realpath($directory);

            if ($root === false || ! is_dir($root)) {
                continue;
            }

            foreach ($this->filesIn($root) as $file) {
                $files[$file->path()] = $file;
            }
        }

        $files = array_values($files);

        usort($files, function (LogFile $first, LogFile $second): int {
            return [$second->modifiedAt(), $first->relativePath()] <=> [$first->modifiedAt(), $second->relativePath()];
        });

        return $files;
    }

    /**
     * Find a log file by its ID.
     */
    public function find(string $id): ?LogFile
    {
        foreach ($this->all() as $file) {
            if (hash_equals($file->id(), $id)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return list<LogFile>
     */
    private function filesIn(string $root): array
    {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            $iterator->setMaxDepth($this->maxDepth);
        } catch (UnexpectedValueException $exception) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $file = $this->toLogFile($root, $item);

            if ($file !== null) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function toLogFile(string $root, SplFileInfo $item): ?LogFile
    {
        if (! $item->isFile() || ! $item->isReadable() || ! $this->matchesPattern($item->getFilename())) {
            return null;
        }

        $path = $item->getRealPath();

        if ($path === false || strpos($path, $root.DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));

        return new LogFile(
            substr(sha1($path), 0, 20),
            $path,
            $relativePath,
            basename($root),
            (int) $item->getSize(),
            (int) $item->getMTime()
        );
    }

    private function matchesPattern(string $name): bool
    {
        foreach ($this->patterns as $pattern) {
            if (fnmatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
