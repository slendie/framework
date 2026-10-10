<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Http;

use Closure;
use Slendie\Http\Request;
use Slendie\Http\Response;

class TerminatingMiddleware
{
    public static int $calls = 0;

    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        self::$calls++;
    }
}
