<?php

namespace Rss\Vlog\Core\Http;

use InvalidArgumentException;
use Rss\Vlog\Core\Exceptions\IndexException;
use Rss\Vlog\Core\Exceptions\LogFileException;
use Rss\Vlog\Core\Files\LogFile;
use Rss\Vlog\Core\Files\LogFileFinder;
use Rss\Vlog\Core\Index\LogIndex;
use Rss\Vlog\Core\Search\SearchFilter;
use Rss\Vlog\Core\Security\Redactor;

/**
 * The log viewer's JSON API, independent of any framework.
 *
 * Each adapter routes requests to these methods and sends the returned
 * response. Errors become JSON responses instead of exceptions, so a broken
 * log file can never break the host application's error handling.
 */
final class LogViewerApi
{
    private const MAX_PER_PAGE = 100;

    private LogFileFinder $finder;

    private string $indexDirectory;

    private float $timeLimit;

    private int $maxEntryBytes;

    private Presenter $presenter;

    /**
     * @param  float  $timeLimit  The most seconds a request spends indexing or searching.
     * @param  int  $maxEntryBytes  The most bytes of an entry to read. Longer entries are truncated.
     * @param  Redactor|null  $redactor  Hides secrets in entries. Defaults to hiding the built-in secret formats.
     */
    public function __construct(
        LogFileFinder $finder,
        string $indexDirectory,
        float $timeLimit = 2.0,
        int $maxEntryBytes = 1048576,
        ?Redactor $redactor = null
    ) {
        $this->finder = $finder;
        $this->indexDirectory = $indexDirectory;
        $this->timeLimit = $timeLimit;
        $this->maxEntryBytes = $maxEntryBytes;
        $this->presenter = new Presenter($redactor ?? new Redactor);
    }

    /**
     * List the log files.
     */
    public function files(): ApiResponse
    {
        return ApiResponse::ok(['data' => array_map([$this->presenter, 'file'], $this->finder->all())]);
    }

    /**
     * Get a file's details and level counts, indexing new entries first.
     */
    public function file(string $id): ApiResponse
    {
        return $this->withIndex($id, function (LogFile $file, LogIndex $index, bool $complete): ApiResponse {
            return ApiResponse::ok(['data' => array_merge($this->presenter->file($file), [
                'format' => $index->format(),
                'entries' => $index->count(),
                'levels' => (object) $index->levelCounts(),
                'indexed_bytes' => $index->indexedBytes(),
                'complete' => $complete,
            ])]);
        });
    }

    /**
     * Get a page of a file's entries.
     *
     * Query parameters: page, per_page and order ("newest" or "oldest").
     *
     * @param  array<array-key, mixed>  $query
     */
    public function entries(string $id, array $query): ApiResponse
    {
        return $this->withIndex($id, function (LogFile $file, LogIndex $index, bool $complete) use ($query): ApiResponse {
            $input = new QueryInput($query);
            $page = $input->integer('page', 1, 1, PHP_INT_MAX);
            $perPage = $input->integer('per_page', 25, 1, self::MAX_PER_PAGE);
            $newestFirst = $input->choice('order', ['newest', 'oldest'], 'newest') === 'newest';

            return ApiResponse::ok([
                'data' => $this->presenter->entries($index->entries($page, $perPage, $newestFirst)),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $index->count(),
                    'last_page' => max(1, (int) ceil($index->count() / $perPage)),
                    'complete' => $complete,
                ],
            ]);
        });
    }

    /**
     * Search a file's entries.
     *
     * Query parameters: levels (comma-separated), from, to, q, regex, case,
     * cursor, limit and order ("newest" or "oldest").
     *
     * @param  array<array-key, mixed>  $query
     */
    public function search(string $id, array $query): ApiResponse
    {
        return $this->withIndex($id, function (LogFile $file, LogIndex $index, bool $complete) use ($query): ApiResponse {
            $input = new QueryInput($query);
            $filter = new SearchFilter(
                $input->list('levels'),
                $input->dateTime('from'),
                $input->dateTime('to'),
                $input->string('q'),
                $input->boolean('regex'),
                $input->boolean('case')
            );

            $result = $index->search(
                $filter,
                $input->integer('limit', 25, 1, self::MAX_PER_PAGE),
                $input->optionalInteger('cursor', 0),
                $input->choice('order', ['newest', 'oldest'], 'newest') === 'newest',
                $this->timeLimit
            );

            return ApiResponse::ok([
                'data' => $this->presenter->entries($result->entries()),
                'meta' => [
                    'next_cursor' => $result->nextCursor(),
                    'has_more' => $result->hasMore(),
                    'reached_time_limit' => $result->reachedTimeLimit(),
                    'complete' => $complete,
                ],
            ]);
        });
    }

    /**
     * Find a file, bring its index up to date within the time limit, and
     * build a response with it.
     *
     * @param  callable(LogFile, LogIndex, bool): ApiResponse  $respond
     */
    private function withIndex(string $id, callable $respond): ApiResponse
    {
        $file = $this->finder->find($id);

        if ($file === null) {
            return ApiResponse::error(404, 'Log file not found.');
        }

        try {
            $index = LogIndex::open($file->path(), $this->indexDirectory, $this->maxEntryBytes);
            $complete = $index->refresh($this->timeLimit);

            return $respond($file, $index, $complete);
        } catch (LogFileException $exception) {
            return ApiResponse::error(404, 'Log file not found.');
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error(422, $exception->getMessage());
        } catch (IndexException $exception) {
            // The exception names the folder, which isn't sent to the browser.
            return ApiResponse::error(500, 'Vlog could not write its index. Check that the "index_path" folder in the Vlog config is writable.');
        }
    }
}
