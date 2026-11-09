<?php

namespace Rss\Vlog\Core\Entry;

/**
 * One frame of a stack trace written in a log entry.
 */
final class StackFrame
{
    private int $index;

    private ?string $file;

    private ?int $line;

    private ?string $call;

    public function __construct(int $index, ?string $file, ?int $line, ?string $call)
    {
        $this->index = $index;
        $this->file = $file;
        $this->line = $line;
        $this->call = $call;
    }

    /**
     * Get the frame's position in the trace, where 0 is the most recent call.
     */
    public function index(): int
    {
        return $this->index;
    }

    /**
     * Get the file path, or null for internal functions and the {main} frame.
     */
    public function file(): ?string
    {
        return $this->file;
    }

    public function line(): ?int
    {
        return $this->line;
    }

    /**
     * Get the function or method call, such as "App\Foo->bar()".
     */
    public function call(): ?string
    {
        return $this->call;
    }
}
