<?php

namespace Rss\Vlog\Core\Entry;

use Rss\Vlog\Core\Support\LogLevel;
use Rss\Vlog\Core\Support\Time;

/**
 * The level and time read from an entry's first line, which is all the index
 * needs without parsing the whole entry.
 */
final class EntryHeader
{
    private ?string $level;

    private ?int $timestamp;

    /**
     * @param  string|null  $level  A standard level name from LogLevel.
     */
    public function __construct(?string $level = null, ?int $timestamp = null)
    {
        $this->level = $level;
        $this->timestamp = $timestamp;
    }

    /**
     * Create a header from a level and time as written in the log file.
     */
    public static function fromText(?string $rawLevel, ?string $time): self
    {
        $parsedTime = $time === null ? null : Time::parse($time);

        return new self(
            $rawLevel === null ? null : LogLevel::normalize($rawLevel),
            $parsedTime === null ? null : $parsedTime->getTimestamp()
        );
    }

    /**
     * Get the standard level name, or null when the level isn't recognized.
     */
    public function level(): ?string
    {
        return $this->level;
    }

    /**
     * Get the entry's time as a Unix timestamp.
     */
    public function timestamp(): ?int
    {
        return $this->timestamp;
    }
}
