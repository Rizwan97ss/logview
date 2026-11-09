<?php

namespace Rss\Vlog\Core\Parsers;

use Rss\Vlog\Core\Entry\EntryHeader;
use Rss\Vlog\Core\Entry\LogEntry;

/**
 * Reads entries written in one log format.
 *
 * An entry starts on a line the parser recognizes as a header and continues
 * until the next header line, so stack traces stay with their entry.
 */
interface Parser
{
    /**
     * Get the format's name, such as "laravel".
     */
    public function name(): string;

    /**
     * Determine whether a line starts a new entry.
     */
    public function startsEntry(string $line): bool;

    /**
     * Read the level and time from a line that starts an entry, or return
     * null when the line doesn't start one.
     */
    public function headerOf(string $line): ?EntryHeader;

    /**
     * Determine whether a line outside an entry should be skipped, such as a
     * blank line or CodeIgniter 3's PHP guard line.
     */
    public function isNoise(string $line): bool;

    /**
     * Build an entry from its full text, which may span several lines.
     *
     * Text without a recognized header becomes an entry with no level.
     */
    public function parse(string $text): LogEntry;
}
