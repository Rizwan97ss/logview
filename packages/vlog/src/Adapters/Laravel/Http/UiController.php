<?php

namespace Rss\Vlog\Adapters\Laravel\Http;

use Illuminate\Http\Response;
use Illuminate\Contracts\Routing\UrlGenerator;
use Rss\Vlog\Core\Http\UiPage;

/**
 * Serves the viewer's page.
 */
final class UiController
{
    private UiPage $page;

    private UrlGenerator $url;

    public function __construct(UiPage $page, UrlGenerator $url)
    {
        $this->page = $page;
        $this->url = $url;
    }

    public function show(): Response
    {
        // The API's files route, without its last segment, is where every API route starts.
        $apiUrl = substr($this->url->route('vlog.api.files', [], false), 0, -strlen('/files'));

        return new Response($this->page->render($apiUrl), 200, $this->page->headers());
    }
}
