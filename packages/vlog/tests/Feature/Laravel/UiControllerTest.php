<?php

use Illuminate\Support\Facades\Gate;

it('serves the viewer page with the API URL', function () {
    Gate::define('viewVlog', function ($user = null): bool {
        return true;
    });

    $response = $this->get('/vlog');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertSee('<div id="vlog"></div>', false)
        ->assertSee('{"apiUrl":"/vlog/api","title":"Vlog"}', false);
    expect($response->headers->get('Content-Type'))->toBe('text/html; charset=utf-8');
});

it('forbids the viewer page when the gate denies access', function () {
    $this->get('/vlog')->assertForbidden();
});
