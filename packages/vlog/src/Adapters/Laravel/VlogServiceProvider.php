<?php

namespace Rss\Vlog\Adapters\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Rss\Vlog\Adapters\Laravel\Http\ApiController;
use Rss\Vlog\Adapters\Laravel\Http\Authorize;
use Rss\Vlog\Adapters\Laravel\Http\UiController;
use Rss\Vlog\Core\Files\LogFileFinder;
use Rss\Vlog\Core\Http\LogViewerApi;
use Rss\Vlog\Core\Security\Redactor;

/**
 * Registers Vlog's configuration, access gate and routes in Laravel.
 */
final class VlogServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__.'/../../../config/vlog.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'vlog');

        $this->app->bind(LogViewerApi::class, function (Application $app): LogViewerApi {
            /** @var Repository $config */
            $config = $app->make('config');

            return new LogViewerApi(
                new LogFileFinder(
                    $this->stringList($config->get('vlog.paths')),
                    $this->stringList($config->get('vlog.patterns', ['*.log'])),
                    (int) $this->number($config->get('vlog.max_depth'), 3)
                ),
                $this->string($config->get('vlog.index_path'), $app->storagePath().'/framework/vlog'),
                (float) $this->number($config->get('vlog.time_limit'), 2.0),
                (int) $this->number($config->get('vlog.max_entry_bytes'), 1048576),
                new Redactor(
                    $config->get('vlog.redact.enabled', true) !== false,
                    $this->stringList($config->get('vlog.redact.keys', [])),
                    $this->stringList($config->get('vlog.redact.patterns', []))
                )
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::CONFIG_PATH => $this->app->configPath('vlog.php')], 'vlog-config');
        }

        // Applications define their own gate in a service provider, which
        // boots after this one and replaces this default.
        if (! Gate::has('viewVlog')) {
            Gate::define('viewVlog', function ($user = null): bool {
                return $this->app->environment('local') === true;
            });
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        /** @var Repository $config */
        $config = $this->app->make('config');

        $routesAreCached = $this->app instanceof CachesRoutes && $this->app->routesAreCached();

        if (! $config->get('vlog.enabled', true) || $routesAreCached) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make('router');

        $router->group([
            'prefix' => trim($this->string($config->get('vlog.path'), 'vlog'), '/'),
            'domain' => $config->get('vlog.domain'),
            'middleware' => array_merge($this->stringList($config->get('vlog.middleware', ['web'])), [Authorize::class]),
            'as' => 'vlog.',
        ], function (Router $router): void {
            $router->get('/', [UiController::class, 'show'])->name('index');
            $router->get('api/files', [ApiController::class, 'files'])->name('api.files');
            $router->get('api/files/{file}', [ApiController::class, 'file'])->name('api.file');
            $router->get('api/files/{file}/entries', [ApiController::class, 'entries'])->name('api.entries');
            $router->get('api/files/{file}/search', [ApiController::class, 'search'])->name('api.search');
        });
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList($value): array
    {
        return array_values(array_filter((array) $value, 'is_string'));
    }

    /**
     * Get a config value as a string, or the default when it isn't one.
     *
     * @param  mixed  $value
     */
    private function string($value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Get a config value as a number, or the default when it isn't one.
     * Values from environment variables arrive as numeric strings.
     *
     * @param  mixed  $value
     * @param  int|float  $default
     * @return int|float
     */
    private function number($value, $default)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return is_string($value) && is_numeric($value) ? $value + 0 : $default;
    }
}
