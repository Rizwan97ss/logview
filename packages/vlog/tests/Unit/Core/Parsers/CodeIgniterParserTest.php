<?php

use Rss\Vlog\Core\Parsers\CodeIgniterParser;

it('recognizes header lines', function (string $line) {
    expect((new CodeIgniterParser)->startsEntry($line))->toBeTrue();
})->with([
    'CodeIgniter 4' => ['CRITICAL - 2026-11-09 09:00:02 --> ErrorException: Undefined variable $total'],
    'CodeIgniter 3 message containing an arrow' => ['ERROR - 2026-11-09 08:30:00 --> Severity: Warning --> Undefined index: id'],
]);

it('does not treat other lines as the start of an entry', function (string $line) {
    expect((new CodeIgniterParser)->startsEntry($line))->toBeFalse();
})->with([
    'stack frame' => [' 1 APPPATH/Controllers/Reports.php(18): index()'],
    'Laravel header' => ['[2026-11-09 10:15:32] local.ERROR: Something failed'],
    'PHP guard line' => ["<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>"],
]);

it('skips the PHP guard line and blank lines outside entries', function (string $line) {
    expect((new CodeIgniterParser)->isNoise($line))->toBeTrue();
})->with([
    'PHP guard line' => ["<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>"],
    'blank' => ['   '],
]);

it('reads the time, level and message from a CodeIgniter 3 entry', function () {
    $entry = (new CodeIgniterParser)->parse('ERROR - 2026-11-09 08:30:00 --> Severity: Warning --> Undefined index: id');

    expect($entry->time()->format('Y-m-d H:i:s'))->toBe('2026-11-09 08:30:00');
    expect($entry->level())->toBe('error');
    expect($entry->message())->toBe('Severity: Warning --> Undefined index: id');
    expect($entry->channel())->toBeNull();
});

it('reads a CodeIgniter 4 exception with its stack trace', function () {
    $text = <<<'LOG'
CRITICAL - 2026-11-09 09:00:02 --> ErrorException: Undefined variable $total
in APPPATH/Controllers/Reports.php on line 18.
 1 APPPATH/Controllers/Reports.php(18): CodeIgniter\Debug\Exceptions->errorHandler(2)
 2 SYSTEMPATH/CodeIgniter.php(933): App\Controllers\Reports->index()
LOG;

    $entry = (new CodeIgniterParser)->parse($text);
    $frames = $entry->stackFrames();

    expect($entry->level())->toBe('critical');
    expect($entry->message())->toStartWith("ErrorException: Undefined variable \$total\nin APPPATH");
    expect($frames)->toHaveCount(2);
    expect([$frames[1]->index(), $frames[1]->file(), $frames[1]->line(), $frames[1]->call()])
        ->toBe([2, 'SYSTEMPATH/CodeIgniter.php', 933, 'App\Controllers\Reports->index()']);
});

it('reads the level and time from a header line without parsing the entry', function () {
    $header = (new CodeIgniterParser)->headerOf('CRITICAL - 2026-11-09 09:00:02 --> Failed');

    expect($header->level())->toBe('critical');
    expect($header->timestamp())->not->toBeNull();
});

it('reads no header from a line that does not start an entry', function () {
    expect((new CodeIgniterParser)->headerOf(' 1 APPPATH/Controllers/Reports.php(18): index()'))->toBeNull();
});
