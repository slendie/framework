<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Http;

use Closure;
use Slendie\Http\Request;
use Slendie\Http\Response;

/**
 * Acrescenta um header X-Tag-N com os parâmetros recebidos e regista a ordem de execução.
 */
class TagMiddleware
{
    /** @var list<string> */
    public static array $log = [];

    public function handle(Request $request, Closure $next, string ...$tags): Response
    {
        self::$log[] = 'before:'.implode('|', $tags);

        /** @var Response $response */
        $response = $next($request);

        self::$log[] = 'after:'.implode('|', $tags);

        return $response->header('X-Tag', implode('|', $tags), replace: false);
    }
}
