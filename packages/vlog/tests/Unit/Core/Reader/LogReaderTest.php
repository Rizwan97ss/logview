<?php

use Rss\Vlog\Core\Entry\LogEntry;
use Rss\Vlog\Core\Exceptions\LogFileException;
use Rss\Vlog\Core\Parsers\CodeIgniterParser;
use Rss\Vlog\Core\Parsers\JsonLinesParser;
use Rss\Vlog\Core\Parsers\LaravelParser;
use Rss\Vlog\Core\Reader\LogReader;

/**
 * Read every entry's message from a file.
 *
 * @return list<string>
 */
function vlogMessagesIn(LogReader $reader, string $path, int $offset = 0): array
{
    return array_map(function (LogEntry $entry): string {
        return $entry->message();
    }, iterator_to_array($reader->entries($path, $offset), false));
}

it('groups multi-line entries with their stack traces', function () {
    $entries = iterator_to_array((new LogReader(new LaravelParser))->entries(vlogFixture('laravel.log')), false);

    expect($entries)->toHaveCount(3);
    expect($entries[1]->message())->toBe('Division by zero');
    expect($entries[1]->stackFrames())->toHaveCount(3);
    expect($entries[2]->message())->toBe('Cache miss for key users:7');
});

it('reads every supported format', function ($parser, string $file, array $expected) {
    expect(vlogMessagesIn(new LogReader($parser), vlogFixture($file)))->toBe($expected);
})->with([
    'CodeIgniter 4' => [new CodeIgniterParser, 'codeigniter4.log', [
        'Controller "App\Controllers\Reports" loaded.',
        "ErrorException: Undefined variable \$total\n[Method: GET, Route: reports]\nin APPPATH/Controllers/Reports.php on line 18.\n 1 APPPATH/Controllers/Reports.php(18): CodeIgniter\Debug\Exceptions->errorHandler(2, 'Undefined variable \$total', '/var/www/app/Controllers/Reports.php', 18)\n 2 SYSTEMPATH/CodeIgniter.php(933): App\Controllers\Reports->index()",
        'Total execution time: 0.0125',
    ]],
    'CodeIgniter 3 without its PHP guard line' => [new CodeIgniterParser, 'codeigniter3.php', [
        'Severity: Warning --> Undefined index: id /var/www/application/controllers/Users.php 42',
        '404 Page Not Found: /favicon.ico',
    ]],
    'Monolog JSON' => [new JsonLinesParser, 'monolog.json', ['Payment captured', 'Gateway timeout']],
]);

it('records where each entry starts so it can be read again from there', function () {
    $reader = new LogReader(new LaravelParser);
    $path = vlogFixture('laravel.log');
    $entries = iterator_to_array($reader->entries($path), false);

    expect(vlogMessagesIn($reader, $path, $entries[1]->offset()))->toBe(['Division by zero', 'Cache miss for key users:7']);
});

it('records the size of each entry so entries cover the whole file', function () {
    $path = vlogFixture('laravel.log');
    $entries = iterator_to_array((new LogReader(new LaravelParser))->entries($path), false);

    expect($entries[1]->offset())->toBe($entries[0]->offset() + $entries[0]->length());
    expect($entries[2]->offset() + $entries[2]->length())->toBe(filesize($path));
});

it('reads files with Windows line breaks', function () {
    $firstLine = "[2026-11-09 10:00:00] local.INFO: First\r\n";
    $path = vlogTemporaryLog($firstLine."[2026-11-09 10:00:01] local.INFO: Second\r\n");

    $entries = iterator_to_array((new LogReader(new LaravelParser))->entries($path), false);

    expect($entries[0]->message())->toBe('First');
    expect($entries[1]->offset())->toBe(strlen($firstLine));
});

it('keeps text before the first header as its own entry', function () {
    $path = vlogTemporaryLog("leftover line\n[2026-11-09 10:00:00] local.INFO: First\n");

    expect(vlogMessagesIn(new LogReader(new LaravelParser), $path))->toBe(['leftover line', 'First']);
});

it('truncates entries over the size limit without losing their position', function () {
    $longLine = '[2026-11-09 10:00:00] local.ERROR: '.str_repeat('x', 100);
    $path = vlogTemporaryLog($longLine."\n[2026-11-09 10:00:01] local.INFO: Next\n");

    $entries = iterator_to_array((new LogReader(new LaravelParser, 50))->entries($path), false);

    expect($entries[0]->isTruncated())->toBeTrue();
    expect(strlen($entries[0]->raw()))->toBe(50);
    expect($entries[1]->message())->toBe('Next');
    expect($entries[1]->offset())->toBe(strlen($longLine) + 1);
    expect($entries[1]->isTruncated())->toBeFalse();
});

it('does not mark an entry exactly at the size limit as truncated', function () {
    $line = '[2026-11-09 10:00:00] local.INFO: Exact';
    $path = vlogTemporaryLog($line."\n");

    $entries = iterator_to_array((new LogReader(new LaravelParser, strlen($line)))->entries($path), false);

    expect($entries[0]->isTruncated())->toBeFalse();
});

it('reads a last line that has no line break', function () {
    $path = vlogTemporaryLog('[2026-11-09 10:00:00] local.INFO: Still being written');

    expect(vlogMessagesIn(new LogReader(new LaravelParser), $path))->toBe(['Still being written']);
});

it('reads nothing from an empty file', function () {
    expect(vlogMessagesIn(new LogReader(new LaravelParser), vlogTemporaryLog('')))->toBe([]);
});

it('throws when the file cannot be read', function () {
    iterator_to_array((new LogReader(new LaravelParser))->entries(vlogFixture('missing.log')));
})->throws(LogFileException::class);

it('reads entries at known positions and truncates ones over the size limit', function () {
    $first = "[2026-11-09 10:00:00] local.ERROR: First\n#0 {main}\n";
    $second = '[2026-11-09 10:00:01] local.INFO: '.str_repeat('x', 100)."\n";
    $path = vlogTemporaryLog($first.$second);

    $entries = (new LogReader(new LaravelParser, 60))->entriesAt($path, [
        ['offset' => strlen($first), 'length' => strlen($second)],
        ['offset' => 0, 'length' => strlen($first)],
    ]);

    expect($entries[0]->isTruncated())->toBeTrue();
    expect($entries[0]->offset())->toBe(strlen($first));
    expect($entries[1]->message())->toBe("First\n#0 {main}");
    expect($entries[1]->isTruncated())->toBeFalse();
});

it('reads the raw text of entries far apart in the file', function () {
    $first = "[2026-11-09 10:00:00] local.INFO: First\r\n";
    $padding = str_repeat("[2026-11-09 10:00:01] local.DEBUG: padding\n", 220000);
    $last = "[2026-11-09 10:00:02] local.INFO: Last\n";
    $path = vlogTemporaryLog($first.$padding.$last);

    $texts = (new LogReader(new LaravelParser))->textsAt($path, [
        ['offset' => strlen($first) + strlen($padding), 'length' => strlen($last)],
        ['offset' => 0, 'length' => strlen($first)],
    ]);

    expect($texts)->toBe(["[2026-11-09 10:00:02] local.INFO: Last\n", "[2026-11-09 10:00:00] local.INFO: First\n"]);
});
