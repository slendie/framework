<?php

declare(strict_types=1);

namespace Slendie\Http\Middleware;

use Closure;
use Slendie\Http\Request;
use Slendie\Http\Response;

/**
 * Cabeçalhos de segurança básicos. Configuráveis em config('app.security_headers');
 * um valor false/null/'' desactiva o header.
 */
class SecurityHeaders
{
    /** @var array<string, string> */
    protected array $defaults = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $configured = config('app.security_headers', []);
        $headers = [...$this->defaults, ...(is_array($configured) ? $configured : [])];

        foreach ($headers as $name => $value) {
            if (is_string($name) && is_string($value) && $value !== '') {
                $response->header($name, $value, replace: false);
            }
        }

        return $response;
    }
}
