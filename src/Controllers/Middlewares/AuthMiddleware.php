<?php

declare(strict_types=1);

namespace Slendie\Controllers\Middlewares;

final class AuthMiddleware
{
    public function handle($request)
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo 'Unauthorized';
            return false;
        }
        return true;
    }
}
