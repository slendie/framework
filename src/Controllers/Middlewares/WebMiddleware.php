<?php

declare(strict_types=1);

namespace Slendie\Controllers\Middlewares;

use Slendie\Framework\Request;
use stdClass;

final class WebMiddleware
{
    private static $request = null;

    public static function getRequest(): mixed
    {
        return self::$request;
    }

    public function handle($request): bool
    {
        // Armazena a instância Request para acesso global
        self::$request = $request;
        return true;
    }
}
