<?php

declare(strict_types=1);

namespace Slendie\Http\Middleware;

use Closure;
use Slendie\Http\HttpMethod;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Support\Str;

/**
 * CORS a partir de config/cors.php. Responde ao preflight (OPTIONS) com 204.
 */
class HandleCors
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldRun($request)) {
            /** @var Response */
            return $next($request);
        }

        $origin = $request->header('Origin');

        if ($origin === null) {
            /** @var Response */
            return $next($request);
        }

        if ($this->isPreflight($request)) {
            $response = new Response('', 204);

            return $this->addHeaders($request, $response, $origin, preflight: true);
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->addHeaders($request, $response, $origin);
    }

    protected function isPreflight(Request $request): bool
    {
        return $request->realMethod() === HttpMethod::OPTIONS && $request->header('Access-Control-Request-Method') !== null;
    }

    protected function shouldRun(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        foreach ($this->list('paths') as $pattern) {
            if (Str::is(ltrim($pattern, '/'), $path)) {
                return true;
            }
        }

        return false;
    }

    protected function addHeaders(Request $request, Response $response, string $origin, bool $preflight = false): Response
    {
        $allowedOrigin = $this->allowedOrigin($origin);

        if ($allowedOrigin === null) {
            return $response;
        }

        $response->header('Access-Control-Allow-Origin', $allowedOrigin);

        if ($allowedOrigin !== '*') {
            $response->header('Vary', 'Origin');
        }

        if ((bool) config('cors.supports_credentials', false)) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        if ($preflight) {
            $methods = $this->list('allowed_methods');
            $response->header('Access-Control-Allow-Methods', in_array('*', $methods, true) ? 'GET, POST, PUT, PATCH, DELETE, OPTIONS' : implode(', ', $methods));

            $headers = $this->list('allowed_headers');
            $requested = $request->header('Access-Control-Request-Headers');
            $response->header('Access-Control-Allow-Headers', in_array('*', $headers, true) ? ($requested ?? '*') : implode(', ', $headers));

            $maxAge = config('cors.max_age', 0);

            if (is_numeric($maxAge) && (int) $maxAge > 0) {
                $response->header('Access-Control-Max-Age', (string) (int) $maxAge);
            }

            return $response;
        }

        $exposed = $this->list('exposed_headers');

        if ($exposed !== []) {
            $response->header('Access-Control-Expose-Headers', implode(', ', $exposed));
        }

        return $response;
    }

    protected function allowedOrigin(string $origin): ?string
    {
        $allowed = $this->list('allowed_origins');
        $credentials = (bool) config('cors.supports_credentials', false);

        if (in_array('*', $allowed, true)) {
            // Com credenciais o browser rejeita '*': reflecte a origem.
            return $credentials ? $origin : '*';
        }

        return Str::is($allowed, $origin) ? $origin : null;
    }

    /**
     * @return list<string>
     */
    protected function list(string $key): array
    {
        $value = config('cors.'.$key, []);

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
