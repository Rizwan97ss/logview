<?php

use Rss\Vlog\Core\Entry\LogEntry;
use Rss\Vlog\Core\Index\LogIndex;
use Rss\Vlog\Core\Search\SearchFilter;

/**
 * Open and refresh the index of a temporary log file.
 */
function vlogIndexOf(string $contents): LogIndex
{
    $index = LogIndex::open(vlogTemporaryLog($contents), vlogTemporaryDirectory());
    $index->refresh();

    return $index;
}

/**
 * Get the first line of each entry's message.
 *
 * @param  list<LogEntry>  $entries
 * @return list<string>
 */
function vlogSearchMessages(array $entries): array
{
    return array_map(function (LogEntry $entry): string {
        return explode("\n", $entry->message())[0];
    }, $entries);
}

/**
 * A log with entries at different levels and times.
 */
function vlogMixedLog(): string
{
    return implode("\n", [
        '[2026-11-01 09:00:00] local.INFO: Order 1 placed',
        '[2026-11-02 09:00:00] local.ERROR: Order 2 failed {"order_id":2}',
        '[2026-11-03 09:00:00] local.WARNING: Order 3 slow',
        '[2026-11-04 09:00:00] local.ERROR: Order 4 failed',
        '#0 /var/www/app/Payments/Gateway.php(88): charge()',
        '[2026-11-05 09:00:00] local.INFO: Order 5 placed',
    ])."\n";
}

it('finds entries at the chosen levels, newest first', function () {
    $result = vlogIndexOf(vlogMixedLog())->search(new SearchFilter(['error']));

    expect(vlogSearchMessages($result->entries()))->toBe(['Order 4 failed', 'Order 2 failed']);
    expect($result->hasMore())->toBeFalse();
});

it('finds entries oldest first', function () {
    $result = vlogIndexOf(vlogMixedLog())->search(new SearchFilter(['info']), 25, null, false);

    expect(vlogSearchMessages($result->entries()))->toBe(['Order 1 placed', 'Order 5 placed']);
});

it('finds entries within a date range', function () {
    $filter = new SearchFilter(null, new DateTimeImmutable('2026-11-02 00:00:00'), new DateTimeImmutable('2026-11-03 23:59:59'));

    $result = vlogIndexOf(vlogMixedLog())->search($filter);

    expect(vlogSearchMessages($result->entries()))->toBe(['Order 3 slow', 'Order 2 failed']);
});

it('finds text in the context and stack trace as well as the message', function (string $query, string $expected) {
    $result = vlogIndexOf(vlogMixedLog())->search(new SearchFilter(null, null, null, $query));

    expect(vlogSearchMessages($result->entries()))->toBe([$expected]);
})->with([
    'context' => ['"order_id":2', 'Order 2 failed'],
    'stack trace' => ['Gateway.php', 'Order 4 failed'],
]);

it('finds entries matching a regular expression', function () {
    $result = vlogIndexOf(vlogMixedLog())->search(new SearchFilter(null, null, null, 'Order [13] ', true));

    expect(vlogSearchMessages($result->entries()))->toBe(['Order 3 slow', 'Order 1 placed']);
});

it('combines level, date and text conditions', function () {
    $filter = new SearchFilter(['error'], new DateTimeImmutable('2026-11-03 00:00:00'), null, 'failed');

    $result = vlogIndexOf(vlogMixedLog())->search($filter);

    expect(vlogSearchMessages($result->entries()))->toBe(['Order 4 failed']);
});

it('returns results a page at a time using the cursor', function (bool $newestFirst, array $expectedPages) {
    $index = vlogIndexOf(vlogMixedLog());
    $filter = new SearchFilter(null, null, null, 'Order');
    $pages = [];
    $cursor = null;

    do {
        $result = $index->search($filter, 2, $cursor, $newestFirst);
        $pages[] = vlogSearchMessages($result->entries());
        $cursor = $result->nextCursor();
    } while ($result->hasMore());

    expect($pages)->toBe($expectedPages);
})->with([
    'newest first' => [true, [['Order 5 placed', 'Order 4 failed'], ['Order 3 slow', 'Order 2 failed'], ['Order 1 placed']]],
    'oldest first' => [false, [['Order 1 placed', 'Order 2 failed'], ['Order 3 slow', 'Order 4 failed'], ['Order 5 placed']]],
]);

it('returns no entries and no cursor when nothing matches', function () {
    $result = vlogIndexOf(vlogMixedLog())->search(new SearchFilter(['critical']));

    expect($result->entries())->toBe([]);
    expect($result->nextCursor())->toBeNull();
    expect($result->reachedTimeLimit())->toBeFalse();
});

it('returns no entries for a cursor past the end of a smaller index', function () {
    $result = vlogIndexOf(vlogMixedLog())->search(new SearchFilter, 25, 999, false);

    expect($result->entries())->toBe([]);
    expect($result->hasMore())->toBeFalse();
});

it('stops at the time limit with a cursor that continues the search', function () {
    $log = '';

    for ($number = 1; $number <= 5000; $number++) {
        $log .= "[2026-11-09 10:00:00] local.INFO: Entry {$number}\n";
    }

    $index = vlogIndexOf($log);
    $filter = new SearchFilter(null, null, null, 'Entry 1');

    $first = $index->search($filter, 1000, null, true, 0.0);

    expect($first->reachedTimeLimit())->toBeTrue();
    expect($first->hasMore())->toBeTrue();

    $found = count($first->entries());
    $cursor = $first->nextCursor();

    while ($cursor !== null) {
        $next = $index->search($filter, 1000, $cursor);
        $found += count($next->entries());
        $cursor = $next->nextCursor();
    }

    // "Entry 1" matches 1, 10-19, 100-199 and 1000-1999.
    expect($found)->toBe(1111);
});

it('restores the PCRE backtracking limit after searching', function () {
    $previous = ini_set('pcre.backtrack_limit', '5000000');

    try {
        vlogIndexOf(vlogMixedLog())->search(new SearchFilter(null, null, null, 'Order \d', true));
        $limitAfterSearch = ini_get('pcre.backtrack_limit');
    } finally {
        ini_set('pcre.backtrack_limit', (string) $previous);
    }

    expect($limitAfterSearch)->toBe('5000000');
});

it('reports no more results once every entry at the chosen levels is found', function () {
    $result = vlogIndexOf(vlogMixedLog())->search(new SearchFilter(['warning']), 1);

    expect(vlogSearchMessages($result->entries()))->toBe(['Order 3 slow']);
    expect($result->hasMore())->toBeFalse();
});
