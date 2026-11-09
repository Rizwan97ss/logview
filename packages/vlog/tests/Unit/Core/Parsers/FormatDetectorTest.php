<?php

use Rss\Vlog\Core\Exceptions\LogFileException;
use Rss\Vlog\Core\Parsers\FormatDetector;

it('detects the format of each supported log file', function (string $file, string $format) {
    expect((new FormatDetector)->detect(vlogFixture($file))->name())->toBe($format);
})->with([
    'Laravel' => ['laravel.log', 'laravel'],
    'CodeIgniter 4' => ['codeigniter4.log', 'codeigniter'],
    'CodeIgniter 3' => ['codeigniter3.php', 'codeigniter'],
    'Monolog JSON' => ['monolog.json', 'json'],
]);

it('falls back to plain text for unrecognized and empty files', function (string $contents) {
    expect((new FormatDetector)->detect(vlogTemporaryLog($contents))->name())->toBe('plain');
})->with([
    'unrecognized' => ["127.0.0.1 - - [09/Nov/2026:10:00:00 +0000] \"GET / HTTP/1.1\" 200\n"],
    'empty' => [''],
]);

it('chooses the format that recognizes the most lines', function () {
    $lines = [
        '[2026-11-09 10:15:32] local.ERROR: Laravel entry',
        'ERROR - 2026-11-09 10:15:32 --> CodeIgniter entry',
        'ERROR - 2026-11-09 10:15:33 --> CodeIgniter entry',
    ];

    expect((new FormatDetector)->detectFromLines($lines)->name())->toBe('codeigniter');
});

it('throws when the file cannot be read', function () {
    (new FormatDetector)->detect(vlogFixture('missing.log'));
})->throws(LogFileException::class);
