<?php

namespace Rss\Vlog\Core\Reader;

use Generator;
use Rss\Vlog\Core\Entry\LogEntry;
use Rss\Vlog\Core\Exceptions\LogFileException;
use Rss\Vlog\Core\Parsers\Parser;

/**
 * Streams entries from a log file without loading the whole file.
 *
 * Memory use is limited to one entry at a time, and each entry's stored text
 * is capped, so very large files and very long lines are safe to read.
 */
final class LogReader
{
    /**
     * The bytes read from the file at a time.
     */
    private const CHUNK_BYTES = 8192;

    private Parser $parser;

    private int $maxEntryBytes;

    /**
     * @param  int  $maxEntryBytes  The most bytes of an entry's text to keep. Longer entries are truncated.
     */
    public function __construct(Parser $parser, int $maxEntryBytes = 1048576)
    {
        $this->parser = $parser;
        $this->maxEntryBytes = max(1, $maxEntryBytes);
    }

    /**
     * Read entries in file order, starting at a byte offset.
     *
     * The offset should be the start of an entry, such as an earlier entry's
     * offset(). Text before the first entry header becomes its own entry,
     * unless the parser treats it as noise.
     *
     * @return Generator<int, LogEntry, mixed, void>
     *
     * @throws LogFileException
     */
    public function entries(string $path, int $offset = 0): Generator
    {
        $handle = LogFile::open($path);

        try {
            if ($offset > 0) {
                fseek($handle, $offset);
            }

            $position = max(0, $offset);
            $entry = null;

            while (($line = $this->readLine($handle)) !== null) {
                [$text, $bytes, $lineTruncated] = $line;
                $lineStart = $position;
                $position += $bytes;

                if ($entry === null && $this->parser->isNoise($text)) {
                    continue;
                }

                if ($entry !== null && $this->parser->startsEntry($text)) {
                    yield $this->build($entry);
                    $entry = null;
                }

                if ($entry === null) {
                    $entry = ['offset' => $lineStart, 'bytes' => 0, 'text' => '', 'truncated' => false];
                } else {
                    $text = "\n".$text;
                }

                $entry['bytes'] += $bytes;
                $entry['truncated'] = $entry['truncated'] || $lineTruncated;
                $this->append($entry, $text);
            }

            if ($entry !== null) {
                yield $this->build($entry);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Read one line, keeping at most the maximum entry size of its text.
     *
     * @param  resource  $handle
     * @return array{0: string, 1: int, 2: bool}|null The line without its line break, its size in bytes, and whether it was truncated.
     */
    private function readLine($handle): ?array
    {
        $text = '';
        $bytes = 0;
        $truncated = false;

        while (($chunk = fgets($handle, self::CHUNK_BYTES)) !== false) {
            $bytes += strlen($chunk);

            if (! $truncated) {
                $room = $this->maxEntryBytes - strlen(rtrim($text, "\r\n"));
                $truncated = strlen(rtrim($chunk, "\r\n")) > $room;
                $text .= $truncated ? substr($chunk, 0, max(0, $room)) : $chunk;
            }

            if (substr($chunk, -1) === "\n") {
                break;
            }
        }

        if ($bytes === 0) {
            return null;
        }

        return [rtrim($text, "\r\n"), $bytes, $truncated];
    }

    /**
     * Add text to an entry without going over the maximum entry size.
     *
     * @param  array{offset: int, bytes: int, text: string, truncated: bool}  $entry
     */
    private function append(array &$entry, string $text): void
    {
        $room = $this->maxEntryBytes - strlen($entry['text']);

        if (strlen($text) > $room) {
            $entry['text'] .= substr($text, 0, max(0, $room));
            $entry['truncated'] = true;

            return;
        }

        $entry['text'] .= $text;
    }

    /**
     * @param  array{offset: int, bytes: int, text: string, truncated: bool}  $entry
     */
    private function build(array $entry): LogEntry
    {
        return $this->parser
            ->parse($entry['text'])
            ->withPosition($entry['offset'], $entry['bytes'], $entry['truncated']);
    }
}
