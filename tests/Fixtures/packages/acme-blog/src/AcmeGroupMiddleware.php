<?php

declare(strict_types=1);

namespace Acme\Blog;

use Closure;
use Slendie\Http\Request;
use Slendie\Http\Response;

/** Acrescentado ao grupo `web` pelo provider (`pushMiddlewareToGroup`). */
final class AcmeGroupMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->header('X-Acme-Group', 'web');

        return $response;
    }
}
