<?php

namespace Rss\Vlog\Core\Entry;

use DateTimeImmutable;
use Rss\Vlog\Core\Support\LogLevel;

/**
 * A single entry read from a log file.
 */
final class LogEntry
{
    private string $format;

    private string $raw;

    private string $message;

    private ?string $rawLevel;

    private ?string $level;

    private ?DateTimeImmutable $time;

    private ?string $channel;

    /**
     * @var array<array-key, mixed>|null
     */
    private ?array $context;

    /**
     * @var list<StackFrame>
     */
    private array $stackFrames;

    private int $offset = 0;

    private int $length = 0;

    private bool $truncated = false;

    /**
     * @param  string  $format  The name of the parser that read the entry.
     * @param  string  $raw  The entry's text exactly as written in the file.
     * @param  string|null  $rawLevel  The level exactly as written in the file.
     * @param  array<array-key, mixed>|null  $context  Structured data logged with the message.
     * @param  list<StackFrame>  $stackFrames
     */
    public function __construct(
        string $format,
        string $raw,
        string $message,
        ?string $rawLevel = null,
        ?DateTimeImmutable $time = null,
        ?string $channel = null,
        ?array $context = null,
        array $stackFrames = []
    ) {
        $this->format = $format;
        $this->raw = $raw;
        $this->message = $message;
        $this->rawLevel = $rawLevel;
        $this->level = $rawLevel === null ? null : LogLevel::normalize($rawLevel);
        $this->time = $time;
        $this->channel = $channel;
        $this->context = $context;
        $this->stackFrames = $stackFrames;
    }

    /**
     * Get a copy of the entry with its position in the log file.
     *
     * @param  int  $offset  The byte offset where the entry starts.
     * @param  int  $length  The entry's size in bytes, including line breaks.
     * @param  bool  $truncated  Whether the raw text was cut short for being too large.
     */
    public function withPosition(int $offset, int $length, bool $truncated): self
    {
        $entry = clone $this;
        $entry->offset = $offset;
        $entry->length = $length;
        $entry->truncated = $truncated;

        return $entry;
    }

    public function format(): string
    {
        return $this->format;
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * Get the standard level name, or null when the level isn't recognized.
     */
    public function level(): ?string
    {
        return $this->level;
    }

    public function rawLevel(): ?string
    {
        return $this->rawLevel;
    }

    public function time(): ?DateTimeImmutable
    {
        return $this->time;
    }

    /**
     * Get the logger channel. Laravel writes the environment name here.
     */
    public function channel(): ?string
    {
        return $this->channel;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function context(): ?array
    {
        return $this->context;
    }

    /**
     * @return list<StackFrame>
     */
    public function stackFrames(): array
    {
        return $this->stackFrames;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }
}
