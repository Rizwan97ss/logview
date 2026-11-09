<?php

namespace Rss\Vlog\Adapters\Laravel\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Rss\Vlog\Core\Http\ApiResponse;
use Rss\Vlog\Core\Http\LogViewerApi;

/**
 * Sends Laravel requests to the framework-independent log viewer API.
 */
final class ApiController
{
    private LogViewerApi $api;

    public function __construct(LogViewerApi $api)
    {
        $this->api = $api;
    }

    public function files(): Response
    {
        return $this->respond($this->api->files());
    }

    public function file(string $file): Response
    {
        return $this->respond($this->api->file($file));
    }

    public function entries(Request $request, string $file): Response
    {
        return $this->respond($this->api->entries($file, $request->query->all()));
    }

    public function search(Request $request, string $file): Response
    {
        return $this->respond($this->api->search($file, $request->query->all()));
    }

    private function respond(ApiResponse $response): Response
    {
        return new Response($response->json(), $response->status(), $response->headers());
    }
}
