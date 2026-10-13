<?php

declare(strict_types=1);

namespace Slendie\Http\Middleware;

use Closure;
use Slendie\Exceptions\TokenMismatchException;
use Slendie\Foundation\Application;
use Slendie\Http\Cookie;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Support\Str;

/**
 * Exige o token CSRF em pedidos que alteram estado (POST/PUT/PATCH/DELETE).
 * O token vem em `_token` (input) ou nos headers X-CSRF-TOKEN / X-XSRF-TOKEN.
 */
class VerifyCsrfToken
{
    /**
     * Caminhos (com `*`) isentos, sem barra inicial: `webhooks/*`.
     *
     * @var list<string>
     */
    protected array $except = [];

    public function __construct(protected ?Application $app = null)
    {
    }

    /**
     * @throws TokenMismatchException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isReading($request) || $this->disabledForTests() || $this->inExceptArray($request) || $this->tokensMatch($request)) {
            /** @var Response $response */
            $response = $next($request);

            return $this->isReading($request) ? $this->addXsrfCookie($request, $response) : $response;
        }

        throw new TokenMismatchException();
    }

    /** O Slendie\Testing\TestCase desliga o CSRF por omissão (`withCsrf()` volta a ligá-lo). */
    protected function disabledForTests(): bool
    {
        return $this->app?->bound('testing.csrf.disabled') === true;
    }

    protected function isReading(Request $request): bool
    {
        return $request->method()->isSafe();
    }

    protected function inExceptArray(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        foreach ($this->except as $pattern) {
            if (Str::is(ltrim($pattern, '/'), $path)) {
                return true;
            }
        }

        return false;
    }

    protected function tokensMatch(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $token = $request->input('_token');
        $token = is_string($token) && $token !== '' ? $token : ($request->header('X-CSRF-TOKEN') ?? $request->header('X-XSRF-TOKEN'));

        return is_string($token) && $token !== '' && hash_equals($request->session()->token(), $token);
    }

    /** Cookie legível por JavaScript para frameworks front-end que a ecoam no header X-XSRF-TOKEN. */
    protected function addXsrfCookie(Request $request, Response $response): Response
    {
        if (! $request->hasSession()) {
            return $response;
        }

        return $response->cookie(new Cookie('XSRF-TOKEN', $request->session()->token(), 0, '/', null, $request->isSecure(), false));
    }
}
