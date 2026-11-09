<?php

namespace Rss\Vlog\Adapters\Laravel\Http;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Response;
use Rss\Vlog\Core\Http\UiPage;

/**
 * Serves the viewer's page.
 */
final class UiController
{
    private UiPage $page;

    private UrlGenerator $url;

    private Container $container;

    public function __construct(UiPage $page, UrlGenerator $url, Container $container)
    {
        $this->page = $page;
        $this->url = $url;
        $this->container = $container;
    }

    public function show(): Response
    {
        // The API's files route, without its last segment, is where every API route starts.
        $apiUrl = substr($this->url->route('vlog.api.files', [], false), 0, -strlen('/files'));

        return new Response($this->page->render($apiUrl, 'Vlog', $this->cspNonce()), 200, $this->page->headers());
    }

    /**
     * Get the Content Security Policy nonce the application gave Laravel's
     * Vite integration, so the page works under a strict policy.
     */
    private function cspNonce(): ?string
    {
        $nonce = $this->container->make(Vite::class)->cspNonce();

        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }
}
