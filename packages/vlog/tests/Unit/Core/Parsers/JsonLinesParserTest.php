<?php

use Rss\Vlog\Core\Parsers\JsonLinesParser;

it('recognizes JSON objects that have a message', function () {
    expect((new JsonLinesParser)->startsEntry('{"message":"Started","level_name":"INFO"}'))->toBeTrue();
});

it('does not treat other lines as the start of an entry', function (string $line) {
    expect((new JsonLinesParser)->startsEntry($line))->toBeFalse();
})->with([
    'JSON without a message' => ['{"event":"started"}'],
    'invalid JSON' => ['{"message":'],
    'JSON array' => ['["message"]'],
    'Laravel header' => ['[2026-11-09 10:15:32] local.ERROR: Something failed'],
]);

it('reads the fields Monolog writes', function () {
    $entry = (new JsonLinesParser)->parse('{"message":"Payment captured","context":{"order_id":991},"level":200,"level_name":"INFO","channel":"payments","datetime":"2026-11-09T11:00:00.123456+00:00","extra":{}}');

    expect($entry->message())->toBe('Payment captured');
    expect($entry->level())->toBe('info');
    expect($entry->channel())->toBe('payments');
    expect($entry->context())->toBe(['order_id' => 991]);
    expect($entry->time()->format('Y-m-d\TH:i:s.uP'))->toBe('2026-11-09T11:00:00.123456+00:00');
});

it('reads the level from a severity number or a name when there is no level name', function (string $json, string $expected) {
    expect((new JsonLinesParser)->parse($json)->level())->toBe($expected);
})->with([
    'severity number' => ['{"message":"Failed","level":400}', 'error'],
    'level name' => ['{"message":"Failed","level":"warning"}', 'warning'],
]);

it('reads stack frames from an exception Monolog normalized', function () {
    $entry = (new JsonLinesParser)->parse('{"message":"Gateway timeout","context":{"exception":{"class":"RuntimeException","file":"/var/www/app/Gateway.php:88","trace":["/var/www/app/Checkout.php:41","/var/www/public/index.php:17"]}},"level_name":"CRITICAL"}');
    $frames = $entry->stackFrames();

    expect($frames)->toHaveCount(2);
    expect([$frames[1]->index(), $frames[1]->file(), $frames[1]->line()])->toBe([1, '/var/www/public/index.php', 17]);
});

it('returns text that is not a JSON log record as a message with no level', function () {
    $entry = (new JsonLinesParser)->parse('not json');

    expect($entry->message())->toBe('not json');
    expect($entry->level())->toBeNull();
});

it('reads the level and time from a record without parsing the entry', function () {
    $header = (new JsonLinesParser)->headerOf('{"message":"Failed","level":400,"datetime":"2026-11-09T10:15:32+00:00"}');

    expect($header->level())->toBe('error');
    expect($header->timestamp())->toBe(1794219332);
});

it('reads no header from a line that is not a log record', function () {
    expect((new JsonLinesParser)->headerOf('{"event":"started"}'))->toBeNull();
});
