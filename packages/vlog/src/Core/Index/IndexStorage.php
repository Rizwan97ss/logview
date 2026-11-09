<?php

namespace Rss\Vlog\Core\Index;

use Rss\Vlog\Core\Exceptions\IndexException;
use Rss\Vlog\Core\Support\LogLevel;

/**
 * Stores one log file's index on disk.
 *
 * The index is two files: a binary file with a fixed-size record for each
 * entry, and a small JSON file with the scan's progress. The JSON file is
 * replaced atomically after records are written, and only the records it
 * counts are read, so an interrupted write never corrupts the index.
 *
 * @phpstan-type IndexState array{
 *     version: int,
 *     format: string,
 *     fingerprint: string,
 *     fingerprintBytes: int,
 *     indexedBytes: int,
 *     entries: int,
 *     levels: array<string, int>
 * }
 * @phpstan-type IndexRecord array{offset: int, timestamp: int|null, level: string|null}
 */
final class IndexStorage
{
    /**
     * The index layout version. Indexes from another version are rebuilt.
     */
    public const VERSION = 1;

    /**
     * Each record is the entry's byte offset (8 bytes), its Unix timestamp
     * (4 bytes, 0 when unknown), its level code (1 byte, 0 when unknown) and
     * 3 bytes of padding.
     */
    private const RECORD_BYTES = 16;

    private const RECORD_FORMAT = 'PVCx3';

    private string $directory;

    private string $basePath;

    public function __construct(string $directory, string $logPath)
    {
        $this->directory = rtrim($directory, '/\\');
        $realPath = realpath($logPath);
        $this->basePath = $this->directory.DIRECTORY_SEPARATOR.sha1($realPath === false ? $logPath : $realPath);
    }

    /**
     * Run a callback while holding this index's write lock.
     *
     * Returns false without running the callback when another process holds
     * the lock and $wait is false.
     *
     * @param  callable(): void  $callback
     *
     * @throws IndexException
     */
    public function withLock(callable $callback, bool $wait = false): bool
    {
        $this->ensureDirectory();
        $handle = @fopen($this->basePath.'.lock', 'c');

        if ($handle === false) {
            throw IndexException::fileNotWritable($this->basePath.'.lock');
        }

        try {
            if (! flock($handle, $wait ? LOCK_EX : LOCK_EX | LOCK_NB)) {
                return false;
            }

            try {
                $callback();
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }

        return true;
    }

    /**
     * Read the index state, or return null when there's no valid index.
     *
     * @return IndexState|null
     */
    public function readState(): ?array
    {
        $json = is_file($this->basePath.'.json') ? @file_get_contents($this->basePath.'.json') : false;
        $state = $json === false ? null : json_decode($json, true);

        if (! is_array($state)
            || ($state['version'] ?? null) !== self::VERSION
            || ! is_string($state['format'] ?? null)
            || ! is_string($state['fingerprint'] ?? null)
            || ! is_int($state['fingerprintBytes'] ?? null)
            || ! is_int($state['indexedBytes'] ?? null)
            || ! is_int($state['entries'] ?? null)
            || ! is_array($state['levels'] ?? null)) {
            return null;
        }

        $levels = [];

        foreach ($state['levels'] as $level => $count) {
            if (is_string($level) && is_int($count)) {
                $levels[$level] = $count;
            }
        }

        return [
            'version' => self::VERSION,
            'format' => $state['format'],
            'fingerprint' => $state['fingerprint'],
            'fingerprintBytes' => $state['fingerprintBytes'],
            'indexedBytes' => $state['indexedBytes'],
            'entries' => $state['entries'],
            'levels' => $levels,
        ];
    }

    /**
     * Replace the index state atomically.
     *
     * @param  IndexState  $state
     *
     * @throws IndexException
     */
    public function writeState(array $state): void
    {
        $temporaryPath = $this->basePath.'.json.'.getmypid().'.tmp';

        if (@file_put_contents($temporaryPath, (string) json_encode($state)) === false
            || ! @rename($temporaryPath, $this->basePath.'.json')) {
            @unlink($temporaryPath);

            throw IndexException::fileNotWritable($this->basePath.'.json');
        }
    }

    /**
     * Encode one entry's record.
     */
    public static function encodeRecord(int $offset, ?int $timestamp, ?string $level): string
    {
        $levelCode = $level === null ? false : array_search($level, LogLevel::all(), true);
        $storableTime = $timestamp !== null && $timestamp > 0 && $timestamp <= 0xFFFFFFFF ? $timestamp : 0;

        return pack(self::RECORD_FORMAT, $offset, $storableTime, $levelCode === false ? 0 : $levelCode + 1);
    }

    /**
     * Write encoded records, starting at a record number.
     *
     * @throws IndexException
     */
    public function writeRecords(int $firstRecord, string $records): void
    {
        $path = $this->basePath.'.idx';
        $handle = @fopen($path, 'c+b');

        if ($handle === false) {
            throw IndexException::fileNotWritable($path);
        }

        try {
            fseek($handle, $firstRecord * self::RECORD_BYTES);

            if ($records !== '' && fwrite($handle, $records) !== strlen($records)) {
                throw IndexException::fileNotWritable($path);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Read records, starting at a record number. Fewer records are returned
     * when the index file is shorter than expected.
     *
     * @return list<IndexRecord>
     */
    public function readRecords(int $firstRecord, int $count): array
    {
        if ($count < 1 || ! is_file($this->basePath.'.idx')) {
            return [];
        }

        $handle = @fopen($this->basePath.'.idx', 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            fseek($handle, $firstRecord * self::RECORD_BYTES);
            $data = (string) fread($handle, $count * self::RECORD_BYTES);
        } finally {
            fclose($handle);
        }

        $levels = LogLevel::all();
        $records = [];

        for ($start = 0; $start + self::RECORD_BYTES <= strlen($data); $start += self::RECORD_BYTES) {
            /** @var array{offset: int, timestamp: int, level: int} $record */
            $record = unpack('Poffset/Vtimestamp/Clevel', substr($data, $start, self::RECORD_BYTES));

            $records[] = [
                'offset' => $record['offset'],
                'timestamp' => $record['timestamp'] === 0 ? null : $record['timestamp'],
                'level' => $levels[$record['level'] - 1] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @throws IndexException
     */
    private function ensureDirectory(): void
    {
        $existing = $this->directory;

        while (! file_exists($existing) && dirname($existing) !== $existing) {
            $existing = dirname($existing);
        }

        if (! is_dir($existing) || ! is_writable($existing)) {
            throw IndexException::directoryNotWritable($this->directory);
        }

        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw IndexException::directoryNotWritable($this->directory);
        }

        if (! is_writable($this->directory)) {
            throw IndexException::directoryNotWritable($this->directory);
        }

        // Keep index files out of the application's version control.
        $gitignore = $this->directory.DIRECTORY_SEPARATOR.'.gitignore';

        if (! is_file($gitignore)) {
            @file_put_contents($gitignore, "*\n");
        }
    }
}
