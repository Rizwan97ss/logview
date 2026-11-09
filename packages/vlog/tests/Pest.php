<?php

/**
 * Get the path of a file in the tests/Fixtures directory.
 */
function vlogFixture(string $name): string
{
    return __DIR__.'/Fixtures/'.$name;
}

/**
 * Write contents to a temporary log file that's deleted when the tests end.
 */
function vlogTemporaryLog(string $contents): string
{
    static $paths = [];

    if ($paths === []) {
        register_shutdown_function(function () use (&$paths): void {
            foreach ($paths as $path) {
                @unlink($path);
            }
        });
    }

    $path = (string) tempnam(sys_get_temp_dir(), 'vlog');
    file_put_contents($path, $contents);
    $paths[] = $path;

    return $path;
}
