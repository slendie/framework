<?php

declare(strict_types=1);

namespace Slendie\Http;

use ValueError;

enum HttpMethod: string
{
    case GET = 'GET';
    case HEAD = 'HEAD';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';

    /**
     * @throws ValueError se o método não for suportado
     */
    public static function fromString(string $method): self
    {
        return self::from(strtoupper(trim($method)));
    }

    public static function tryFromString(string $method): ?self
    {
        return self::tryFrom(strtoupper(trim($method)));
    }

    /** GET, HEAD e OPTIONS não alteram estado (não exigem CSRF). */
    public function isSafe(): bool
    {
        return in_array($this, [self::GET, self::HEAD, self::OPTIONS], true);
    }
}
