<?php

namespace Rss\Vlog\Adapters\Laravel\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows access only to users the "viewVlog" gate allows.
 */
final class Authorize
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Gate::check('viewVlog')) {
            abort(403);
        }

        return $next($request);
    }
}
