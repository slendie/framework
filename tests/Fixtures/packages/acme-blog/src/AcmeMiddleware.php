<?php

declare(strict_types=1);

namespace Acme\Blog;

use Closure;
use Slendie\Http\Request;
use Slendie\Http\Response;

final class AcmeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->header('X-Acme', 'alias');

        return $response;
    }
}
