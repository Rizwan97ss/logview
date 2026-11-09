<?php

namespace Rss\Vlog\Tests\Feature\Laravel;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Rss\Vlog\Adapters\Laravel\VlogServiceProvider;

/**
 * Boots a Laravel application with Vlog reading logs from a temporary folder.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * The temporary folder the application's logs are read from.
     */
    protected string $logDirectory = '';

    protected function setUp(): void
    {
        $this->logDirectory = vlogTemporaryDirectory();

        parent::setUp();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [VlogServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('vlog.paths', [$this->logDirectory]);
        $app['config']->set('vlog.index_path', vlogTemporaryDirectory());
    }

    /**
     * Write a log file into the temporary log folder.
     */
    protected function writeLog(string $name, string $contents): string
    {
        $path = $this->logDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }
}
