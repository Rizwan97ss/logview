<?php

use Rss\Vlog\Core\Parsers\StackTraceParser;

it('reads each frame format PHP and CodeIgniter write', function (string $line, array $expected) {
    $frame = StackTraceParser::fromText($line)[0];

    expect([$frame->index(), $frame->file(), $frame->line(), $frame->call()])->toBe($expected);
})->with([
    'PHP frame' => ['#0 /var/www/app/Job.php(54): App\Job->handle()', [0, '/var/www/app/Job.php', 54, 'App\Job->handle()']],
    'call with arguments in parentheses' => ['#1 /var/www/app/Job.php(9): retry(3)', [1, '/var/www/app/Job.php', 9, 'retry(3)']],
    'path containing parentheses' => ['#2 /tmp/copy(2)/run.php(7): main()', [2, '/tmp/copy(2)/run.php', 7, 'main()']],
    'internal function' => ['#3 [internal function]: array_map()', [3, null, null, 'array_map()']],
    'main' => ['#4 {main}', [4, null, null, '{main}']],
    'CodeIgniter 4 frame' => [' 5 SYSTEMPATH/CodeIgniter.php(933): run()', [5, 'SYSTEMPATH/CodeIgniter.php', 933, 'run()']],
]);

it('ignores lines that are not frames', function () {
    $frames = StackTraceParser::fromText("[stacktrace]\n#0 /var/www/app/Job.php(54): handle()\nin APPPATH/Job.php on line 54.");

    expect($frames)->toHaveCount(1);
});

it('builds frames from file and line locations', function () {
    $frames = StackTraceParser::fromLocations(['/var/www/app/Checkout.php:41', 'C:\\www\\index.php:17', 'unknown']);

    expect([$frames[0]->file(), $frames[0]->line()])->toBe(['/var/www/app/Checkout.php', 41]);
    expect([$frames[1]->file(), $frames[1]->line()])->toBe(['C:\\www\\index.php', 17]);
    expect([$frames[2]->file(), $frames[2]->line()])->toBe(['unknown', null]);
});
