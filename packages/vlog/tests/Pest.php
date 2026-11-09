<?php

use Orchestra\Testbench\TestCase;
use Rss\Vlog\Tests\Feature\Laravel\TestCase as LaravelTestCase;

// Laravel's test tools need PHP 8.2 or later, so on older PHP versions only
// the core's unit tests run.
if (class_exists(TestCase::class)) {
    uses(LaravelTestCase::class)->in('Feature/Laravel');
}

/**
 * Get the path of a file in the tests/Fixtures directory.
 */
function vlogFixture(string $name): string
{
    return __DIR__.'/Fixtures/'.$name;
}

/**
 * Create a temporary directory that's deleted when the tests end.
 */
function vlogTemporaryDirectory(): string
{
    static $root = null;

    if ($root === null) {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vlog-tests-'.getmypid();
        $directory = $root;

        register_shutdown_function(function () use ($directory): void {
            vlogDeleteDirectory($directory);
        });
    }

    $path = $root.DIRECTORY_SEPARATOR.uniqid('', true);
    mkdir($path, 0775, true);

    return $path;
}

/**
 * Write contents to a temporary log file that's deleted when the tests end.
 */
function vlogTemporaryLog(string $contents): string
{
    $path = vlogTemporaryDirectory().DIRECTORY_SEPARATOR.'test.log';
    file_put_contents($path, $contents);

    return $path;
}

/**
 * Delete a directory and everything in it.
 */
function vlogDeleteDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }

    @rmdir($directory);
}
