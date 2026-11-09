<?php

use Illuminate\Support\Facades\Gate;

it('forbids access outside the local environment by default', function () {
    $this->getJson('/vlog/api/files')->assertForbidden();
});

it('allows access in the local environment by default', function () {
    $this->app->detectEnvironment(function (): string {
        return 'local';
    });

    $this->getJson('/vlog/api/files')->assertOk();
});

it('allows access when the application gate allows it', function () {
    Gate::define('viewVlog', function ($user = null): bool {
        return true;
    });

    $this->getJson('/vlog/api/files')->assertOk();
});

it('forbids access when the application gate denies it, even locally', function () {
    $this->app->detectEnvironment(function (): string {
        return 'local';
    });
    Gate::define('viewVlog', function ($user = null): bool {
        return false;
    });

    $this->getJson('/vlog/api/files')->assertForbidden();
});
