<?php

declare(strict_types=1);

namespace App\Models;

use Slendie\Framework\Database;
use Slendie\Models\Model;

final class User extends Model
{
    protected static string $table = 'users';

    public static function hasPermission(int|string $userId, string $permission): bool
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT COUNT(*) FROM permissions p '
            . 'JOIN role_permissions rp ON rp.permission_id = p.id '
            . 'JOIN roles r ON rp.role_id = r.id '
            . 'JOIN users u ON u.role_id = r.id '
            . 'WHERE u.id = ? AND p.name = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $permission]);
        return $stmt->fetchColumn() > 0;
    }
}
