<?php

use Rss\Vlog\Core\Support\LogLevel;

it('lists every level from least to most severe', function () {
    expect(LogLevel::all())->toBe([
        'debug',
        'info',
        'notice',
        'warning',
        'error',
        'critical',
        'alert',
        'emergency',
    ]);
});

it('normalizes levels written by Laravel and CodeIgniter', function (string $raw, string $expected) {
    expect(LogLevel::normalize($raw))->toBe($expected);
})->with([
    'upper case' => ['ERROR', 'error'],
    'lower case' => ['debug', 'debug'],
    'mixed case' => ['Warning', 'warning'],
    'surrounding spaces' => ['  CRITICAL ', 'critical'],
]);

it('normalizes common short spellings to the standard level', function (string $raw, string $expected) {
    expect(LogLevel::normalize($raw))->toBe($expected);
})->with([
    ['WARN', 'warning'],
    ['err', 'error'],
    ['crit', 'critical'],
    ['emerg', 'emergency'],
]);

it('returns null for values that are not levels', function (string $raw) {
    expect(LogLevel::normalize($raw))->toBeNull();
})->with([
    'empty' => [''],
    'unknown word' => ['verbose'],
    'CodeIgniter 3 threshold' => ['ALL'],
]);

it('converts Monolog severity numbers to levels', function (int $severity, ?string $expected) {
    expect(LogLevel::fromSeverity($severity))->toBe($expected);
})->with([
    'debug' => [100, 'debug'],
    'error' => [400, 'error'],
    'emergency' => [600, 'emergency'],
    'not a Monolog severity' => [450, null],
]);

it('treats a level as meeting a minimum when it is equally or more severe', function (string $level, string $minimum, bool $expected) {
    expect(LogLevel::isAtLeast($level, $minimum))->toBe($expected);
})->with([
    'more severe' => ['critical', 'error', true],
    'equally severe' => ['ERROR', 'error', true],
    'less severe' => ['info', 'warning', false],
    'alias' => ['warn', 'notice', true],
]);

it('never treats an unrecognized level as meeting a minimum', function (string $level, string $minimum) {
    expect(LogLevel::isAtLeast($level, $minimum))->toBeFalse();
})->with([
    'unknown level' => ['verbose', 'debug'],
    'unknown minimum' => ['emergency', 'verbose'],
]);
