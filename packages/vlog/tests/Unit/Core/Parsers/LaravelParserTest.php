<?php

use Rss\Vlog\Core\Parsers\LaravelParser;

it('recognizes header lines in the formats Laravel writes', function (string $line) {
    expect((new LaravelParser)->startsEntry($line))->toBeTrue();
})->with([
    'default date format' => ['[2026-11-09 10:15:32] local.ERROR: Something failed'],
    'ISO date with microseconds and time zone' => ['[2026-11-09T10:15:32.123456+04:00] production.INFO: Started'],
    'empty message' => ['[2026-11-09 10:15:32] local.DEBUG:'],
]);

it('does not treat other lines as the start of an entry', function (string $line) {
    expect((new LaravelParser)->startsEntry($line))->toBeFalse();
})->with([
    'stack frame' => ['#0 /var/www/app/Http/Kernel.php(12): handle()'],
    'stack trace marker' => ['[stacktrace]'],
    'CodeIgniter header' => ['ERROR - 2026-11-09 10:15:32 --> Something failed'],
    'blank' => [''],
]);

it('reads the time, channel, level and message from the header', function () {
    $entry = (new LaravelParser)->parse('[2026-11-09 10:15:32] production.WARNING: Disk almost full');

    expect($entry->time()->format('Y-m-d H:i:s'))->toBe('2026-11-09 10:15:32');
    expect($entry->channel())->toBe('production');
    expect($entry->rawLevel())->toBe('WARNING');
    expect($entry->level())->toBe('warning');
    expect($entry->message())->toBe('Disk almost full');
    expect($entry->context())->toBeNull();
});

it('keeps the time zone of ISO timestamps', function () {
    $entry = (new LaravelParser)->parse('[2026-11-09T10:15:32.123456+04:00] local.INFO: Started');

    expect($entry->time()->format('Y-m-d\TH:i:s.uP'))->toBe('2026-11-09T10:15:32.123456+04:00');
});

it('separates the JSON context from the message', function () {
    $entry = (new LaravelParser)->parse('[2026-11-09 10:15:30] local.INFO: User logged in {"user_id":7}');

    expect($entry->message())->toBe('User logged in');
    expect($entry->context())->toBe(['user_id' => 7]);
});

it('keeps JSON inside the message and takes the last JSON value as the context', function () {
    $entry = (new LaravelParser)->parse('[2026-11-09 10:15:30] local.INFO: Payload {"a":1} received {"request_id":"abc"}');

    expect($entry->message())->toBe('Payload {"a":1} received');
    expect($entry->context())->toBe(['request_id' => 'abc']);
});

it('reads the empty context and extra arrays written by older Monolog versions', function () {
    $entry = (new LaravelParser)->parse('[2026-11-09 10:15:30] local.INFO: Queue started [] []');

    expect($entry->message())->toBe('Queue started');
    expect($entry->context())->toBe([]);
});

it('reads an exception logged across several lines with its stack trace', function () {
    $text = <<<'LOG'
[2026-11-09 10:15:32] local.ERROR: Division by zero {"user_id":7,"exception":"[object] (DivisionByZeroError(code: 0): Division by zero at /var/www/app/Report.php:21)
[stacktrace]
#0 /var/www/app/Http/Controller.php(54): App\\Report->show()
#1 [internal function]: App\\Http\\Controller->callAction()
#2 {main}
"}
LOG;

    $entry = (new LaravelParser)->parse($text);
    $frames = $entry->stackFrames();

    expect($entry->message())->toBe('Division by zero');
    expect($entry->context()['user_id'])->toBe(7);
    expect($frames)->toHaveCount(3);
    expect([$frames[0]->file(), $frames[0]->line(), $frames[0]->call()])
        ->toBe(['/var/www/app/Http/Controller.php', 54, 'App\Report->show()']);
    expect([$frames[1]->file(), $frames[1]->call()])->toBe([null, 'App\Http\Controller->callAction()']);
    expect($frames[2]->call())->toBe('{main}');
});

it('keeps a multi-line message without context whole', function () {
    $entry = (new LaravelParser)->parse("[2026-11-09 10:15:30] local.INFO: First line\nSecond line");

    expect($entry->message())->toBe("First line\nSecond line");
    expect($entry->context())->toBeNull();
});

it('keeps an unrecognized level as written without a standard level', function () {
    $entry = (new LaravelParser)->parse('[2026-11-09 10:15:30] local.AUDIT: Settings changed');

    expect($entry->rawLevel())->toBe('AUDIT');
    expect($entry->level())->toBeNull();
});

it('returns text without a header as a message with no level', function () {
    $entry = (new LaravelParser)->parse('#3 /var/www/app/Jobs/Import.php(9): run()');

    expect($entry->message())->toBe('#3 /var/www/app/Jobs/Import.php(9): run()');
    expect($entry->level())->toBeNull();
    expect($entry->time())->toBeNull();
});

it('reads the level and time from a header line without parsing the entry', function () {
    $header = (new LaravelParser)->headerOf('[2026-11-09T10:15:32+00:00] local.ERROR: Failed {"id":1}');

    expect($header->level())->toBe('error');
    expect($header->timestamp())->toBe(1794219332);
});

it('reads no header from a line that does not start an entry', function () {
    expect((new LaravelParser)->headerOf('#0 /var/www/app/Job.php(5): run()'))->toBeNull();
});
