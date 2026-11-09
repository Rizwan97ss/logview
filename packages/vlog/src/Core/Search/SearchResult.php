<?php

namespace Rss\Vlog\Core\Search;

use Rss\Vlog\Core\Entry\LogEntry;

/**
 * One page of search results.
 */
final class SearchResult
{
    /**
     * @var list<LogEntry>
     */
    private array $entries;

    private ?int $nextCursor;

    private bool $reachedTimeLimit;

    /**
     * @param  list<LogEntry>  $entries
     */
    public function __construct(array $entries, ?int $nextCursor, bool $reachedTimeLimit)
    {
        $this->entries = $entries;
        $this->nextCursor = $nextCursor;
        $this->reachedTimeLimit = $reachedTimeLimit;
    }

    /**
     * @return list<LogEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Get the cursor that continues the search, or null when the whole index
     * has been searched.
     */
    public function nextCursor(): ?int
    {
        return $this->nextCursor;
    }

    /**
     * Determine whether there may be more matching entries.
     */
    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * Determine whether the search stopped at its time limit before filling
     * the page.
     */
    public function reachedTimeLimit(): bool
    {
        return $this->reachedTimeLimit;
    }
}
