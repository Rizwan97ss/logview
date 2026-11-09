<?php

use Rss\Vlog\Core\Files\LogFile;
use Rss\Vlog\Core\Files\LogFileFinder;

/**
 * Create a file inside a folder, creating subfolders as needed.
 */
function vlogCreateFile(string $directory, string $name, int $modifiedAt = 1000): void
{
    $path = $directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }

    file_put_contents($path, 'contents');
    touch($path, $modifiedAt);
}

/**
 * @param  list<LogFile>  $files
 * @return list<string>
 */
function vlogRelativePaths(array $files): array
{
    return array_map(function (LogFile $file): string {
        return $file->relativePath();
    }, $files);
}

it('finds files matching the patterns, most recently modified first', function () {
    $directory = vlogTemporaryDirectory();
    vlogCreateFile($directory, 'laravel.log', 1000);
    vlogCreateFile($directory, 'worker.log', 3000);
    vlogCreateFile($directory, 'notes.txt', 2000);

    $files = (new LogFileFinder([$directory]))->all();

    expect(vlogRelativePaths($files))->toBe(['worker.log', 'laravel.log']);
});

it('finds files in subfolders up to the maximum depth', function () {
    $directory = vlogTemporaryDirectory();
    vlogCreateFile($directory, 'a/one.log', 3000);
    vlogCreateFile($directory, 'a/b/two.log', 2000);
    vlogCreateFile($directory, 'a/b/c/three.log', 1000);

    $files = (new LogFileFinder([$directory], ['*.log'], 2))->all();

    expect(vlogRelativePaths($files))->toBe(['a/one.log', 'a/b/two.log']);
});

it('finds files in several folders without listing a file twice', function () {
    $first = vlogTemporaryDirectory();
    $second = vlogTemporaryDirectory();
    vlogCreateFile($first, 'app.log', 2000);
    vlogCreateFile($second, 'queue.log', 1000);

    $files = (new LogFileFinder([$first, $second, $first]))->all();

    expect(vlogRelativePaths($files))->toBe(['app.log', 'queue.log']);
    expect($files[1]->folder())->toBe(basename($second));
});

it('matches several file name patterns', function () {
    $directory = vlogTemporaryDirectory();
    vlogCreateFile($directory, 'app.log', 2000);
    vlogCreateFile($directory, 'log-2026-11-09.php', 1000);

    $files = (new LogFileFinder([$directory], ['*.log', 'log-*.php']))->all();

    expect(vlogRelativePaths($files))->toBe(['app.log', 'log-2026-11-09.php']);
});

it('ignores folders that do not exist', function () {
    expect((new LogFileFinder([vlogTemporaryDirectory().'/missing']))->all())->toBe([]);
});

it('finds a file by its ID', function () {
    $directory = vlogTemporaryDirectory();
    vlogCreateFile($directory, 'laravel.log');
    $finder = new LogFileFinder([$directory]);
    $id = $finder->all()[0]->id();

    $file = $finder->find($id);

    expect($file->relativePath())->toBe('laravel.log');
    expect($file->path())->toBe(realpath($directory.DIRECTORY_SEPARATOR.'laravel.log'));
});

it('finds no file for an ID it did not create', function (string $id) {
    $directory = vlogTemporaryDirectory();
    vlogCreateFile($directory, 'laravel.log');

    expect((new LogFileFinder([$directory]))->find($id))->toBeNull();
})->with([
    'file name' => ['laravel.log'],
    'path' => ['../laravel.log'],
    'empty' => [''],
]);
