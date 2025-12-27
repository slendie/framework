<?php

declare(strict_types=1);

namespace Slendie\Controllers\Middlewares;

use App\Models\User;

final class AccessMiddleware
{
    private $permission;

    public function __construct($permission)
    {
        $this->permission = $permission;
    }

    public function handle($request)
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo 'Unauthorized';
            return false;
        }
        $userId = $_SESSION['user_id'];
        if (!User::hasPermission($userId, $this->permission)) {
            http_response_code(403);
            echo 'Forbidden';
            return false;
        }
        return true;
    }
}
