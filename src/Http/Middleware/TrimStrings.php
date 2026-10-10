<?php

declare(strict_types=1);

namespace Slendie\Http\Middleware;

use Closure;
use Slendie\Http\Request;
use Slendie\Http\Response;

/**
 * Faz trim de todas as strings do input e, opcionalmente, converte '' em null.
 */
class TrimStrings
{
    /** @var list<string> chaves que nunca se alteram */
    protected array $except = ['password', 'password_confirmation'];

    public function handle(Request $request, Closure $next): Response
    {
        $emptyToNull = (bool) config('app.convert_empty_strings_to_null', true);

        $request->transformInput(function (string $key, mixed $value) use ($emptyToNull): mixed {
            if (! is_string($value) || $this->isExcepted($key)) {
                return $value;
            }

            $value = trim($value);

            return $value === '' && $emptyToNull ? null : $value;
        });

        /** @var Response */
        return $next($request);
    }

    protected function isExcepted(string $key): bool
    {
        foreach ($this->except as $except) {
            if ($key === $except || str_ends_with($key, '.'.$except)) {
                return true;
            }
        }

        return false;
    }
}
