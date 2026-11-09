<?php

/**
 * Get every PHP file in a directory and its subdirectories.
 *
 * @return list<string>
 */
function vlogPhpFilesIn(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

test('core code does not depend on any framework', function () {
    $offenders = [];

    foreach (vlogPhpFilesIn(dirname(__DIR__, 2).'/src/Core') as $file) {
        if (preg_match('/\b(Illuminate|Laravel|CodeIgniter|Symfony)\\\\/', (string) file_get_contents($file))) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});
