<?php

declare(strict_types=1);

use Slendie\Controllers\Middlewares\AccessMiddleware;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Slendie\Framework\Database;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

beforeEach(function () {
    setupTestEnv();
    createTestTables();
    clearTestTables();
    clearSession();
});

it('bloqueia quando não autenticado', function () {
    clearSession();

    $mw = new AccessMiddleware('view_dashboard');
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(['method' => 'GET', 'path' => '/']);
    });

    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Unauthorized');
    expect($result['code'])->toBe(401);
});

it('bloqueia quando autenticado mas sem permissão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Cria role e usuário sem a permissão
    $roleId = Role::create(['name' => 'user']);
    $userId = User::create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'role_id' => $roleId]);

    $_SESSION['user_id'] = $userId;

    $mw = new AccessMiddleware('view_dashboard');
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(['method' => 'GET', 'path' => '/']);
    });

    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Forbidden');
    expect($result['code'])->toBe(403);

    clearSession();
});

it('permite acesso quando autenticado e com permissão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Cria role, permissão e associação
    $roleId = Role::create(['name' => 'admin']);
    $permId = Permission::create(['name' => 'view_dashboard']);
    $pdo = Database::getConnection();
    $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $permId]);

    // Cria usuário com a role
    $userId = User::create(['name' => 'Admin User', 'email' => 'admin@example.com', 'password' => 'password', 'role_id' => $roleId]);

    $_SESSION['user_id'] = $userId;

    $mw = new AccessMiddleware('view_dashboard');
    $result = $mw->handle(['method' => 'GET', 'path' => '/']);

    expect($result)->toBeTrue();

    clearSession();
});

it('armazena permissão no construtor', function () {
    $mw = new AccessMiddleware('edit_users');

    $reflection = new ReflectionClass($mw);
    $property = $reflection->getProperty('permission');
    $property->setAccessible(true);
    $permission = $property->getValue($mw);

    expect($permission)->toBe('edit_users');
});

it('funciona com diferentes permissões', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Cria múltiplas permissões
    $roleId = Role::create(['name' => 'admin']);
    $perm1Id = Permission::create(['name' => 'view_dashboard']);
    $perm2Id = Permission::create(['name' => 'edit_users']);
    $perm3Id = Permission::create(['name' => 'delete_users']);

    $pdo = Database::getConnection();
    $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $perm1Id]);
    $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $perm2Id]);
    $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $perm3Id]);

    $userId = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role_id' => $roleId]);
    $_SESSION['user_id'] = $userId;

    // Testa cada permissão
    $mw1 = new AccessMiddleware('view_dashboard');
    expect($mw1->handle(['method' => 'GET', 'path' => '/']))->toBeTrue();

    $mw2 = new AccessMiddleware('edit_users');
    expect($mw2->handle(['method' => 'GET', 'path' => '/']))->toBeTrue();

    $mw3 = new AccessMiddleware('delete_users');
    expect($mw3->handle(['method' => 'GET', 'path' => '/']))->toBeTrue();

    // Testa permissão que não existe
    $mw4 = new AccessMiddleware('non_existent_permission');
    $result = captureOutputAndCode(function () use ($mw4) {
        return $mw4->handle(['method' => 'GET', 'path' => '/']);
    });
    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Forbidden');
    expect($result['code'])->toBe(403);

    clearSession();
});

it('define código HTTP 401 quando não autenticado', function () {
    clearSession();

    $mw = new AccessMiddleware('view_dashboard');

    ob_start();
    $mw->handle(['method' => 'GET', 'path' => '/']);
    ob_end_clean();

    if (function_exists('http_response_code')) {
        expect(http_response_code())->toBe(401);
    }
});

it('define código HTTP 403 quando sem permissão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $roleId = Role::create(['name' => 'user']);
    $userId = User::create(['name' => 'Test', 'email' => 'test@example.com', 'password' => 'password', 'role_id' => $roleId]);
    $_SESSION['user_id'] = $userId;

    $mw = new AccessMiddleware('view_dashboard');

    ob_start();
    $mw->handle(['method' => 'GET', 'path' => '/']);
    ob_end_clean();

    if (function_exists('http_response_code')) {
        expect(http_response_code())->toBe(403);
    }

    clearSession();
});

it('imprime "Unauthorized" quando não autenticado', function () {
    clearSession();

    $mw = new AccessMiddleware('view_dashboard');

    ob_start();
    $mw->handle(['method' => 'GET', 'path' => '/']);
    $output = ob_get_clean();

    expect($output)->toBe('Unauthorized');
});

it('imprime "Forbidden" quando sem permissão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $roleId = Role::create(['name' => 'user']);
    $userId = User::create(['name' => 'Test', 'email' => 'test@example.com', 'password' => 'password', 'role_id' => $roleId]);
    $_SESSION['user_id'] = $userId;

    $mw = new AccessMiddleware('view_dashboard');

    ob_start();
    $mw->handle(['method' => 'GET', 'path' => '/']);
    $output = ob_get_clean();

    expect($output)->toBe('Forbidden');

    clearSession();
});

it('não imprime nada quando tem permissão', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $roleId = Role::create(['name' => 'admin']);
    $permId = Permission::create(['name' => 'view_dashboard']);
    $pdo = Database::getConnection();
    $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $permId]);

    $userId = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role_id' => $roleId]);
    $_SESSION['user_id'] = $userId;

    $mw = new AccessMiddleware('view_dashboard');

    ob_start();
    $mw->handle(['method' => 'GET', 'path' => '/']);
    $output = ob_get_clean();

    expect($output)->toBe('');

    clearSession();
});

it('bloqueia quando user_id é null', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = null;

    $mw = new AccessMiddleware('view_dashboard');
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(['method' => 'GET', 'path' => '/']);
    });

    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Unauthorized');
    expect($result['code'])->toBe(401);

    clearSession();
});

it('bloqueia quando user_id é string vazia', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = '';

    $mw = new AccessMiddleware('view_dashboard');
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(['method' => 'GET', 'path' => '/']);
    });

    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Unauthorized');
    expect($result['code'])->toBe(401);

    clearSession();
});

it('bloqueia quando user_id é zero', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = 0;

    $mw = new AccessMiddleware('view_dashboard');
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(['method' => 'GET', 'path' => '/']);
    });

    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Unauthorized');
    expect($result['code'])->toBe(401);

    clearSession();
});

it('funciona com diferentes tipos de request', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $roleId = Role::create(['name' => 'admin']);
    $permId = Permission::create(['name' => 'view_dashboard']);
    $pdo = Database::getConnection();
    $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $permId]);

    $userId = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role_id' => $roleId]);
    $_SESSION['user_id'] = $userId;

    $mw = new AccessMiddleware('view_dashboard');

    $requests = [
        ['method' => 'GET', 'path' => '/'],
        ['method' => 'POST', 'path' => '/login'],
        ['method' => 'PUT', 'path' => '/users/1'],
        ['method' => 'DELETE', 'path' => '/users/1'],
    ];

    foreach ($requests as $request) {
        $result = $mw->handle($request);
        expect($result)->toBeTrue();
    }

    clearSession();
});

it('handle() ignora o parâmetro request', function () {
    clearSession();

    $mw = new AccessMiddleware('view_dashboard');

    // O middleware não usa o parâmetro $request, apenas verifica a sessão e permissão
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(null);
    });

    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Unauthorized');

    // Agora com user_id definido mas sem permissão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $roleId = Role::create(['name' => 'user']);
    $userId = User::create(['name' => 'Test', 'email' => 'test@example.com', 'password' => 'password', 'role_id' => $roleId]);
    $_SESSION['user_id'] = $userId;

    $result2 = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(null);
    });
    expect($result2['result'])->toBeFalse();
    expect($result2['output'])->toBe('Forbidden');

    clearSession();
});

it('verifica permissão específica passada no construtor', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Cria usuário com permissão A mas não com permissão B
    $roleId = Role::create(['name' => 'editor']);
    $permAId = Permission::create(['name' => 'edit_posts']);
    $permBId = Permission::create(['name' => 'delete_posts']);

    $pdo = Database::getConnection();
    $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $permAId]);

    $userId = User::create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'password', 'role_id' => $roleId]);
    $_SESSION['user_id'] = $userId;

    // Deve permitir com permissão A
    $mw1 = new AccessMiddleware('edit_posts');
    expect($mw1->handle(['method' => 'GET', 'path' => '/']))->toBeTrue();

    // Deve bloquear com permissão B
    $mw2 = new AccessMiddleware('delete_posts');
    $result = captureOutputAndCode(function () use ($mw2) {
        return $mw2->handle(['method' => 'GET', 'path' => '/']);
    });
    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Forbidden');
    expect($result['code'])->toBe(403);

    clearSession();
});

it('funciona com permissões que contêm caracteres especiais', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $roleId = Role::create(['name' => 'admin']);
    $permId = Permission::create(['name' => 'view_dashboard_v2']);
    $pdo = Database::getConnection();
    $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $permId]);

    $userId = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role_id' => $roleId]);
    $_SESSION['user_id'] = $userId;

    $mw = new AccessMiddleware('view_dashboard_v2');
    $result = $mw->handle(['method' => 'GET', 'path' => '/']);

    expect($result)->toBeTrue();

    clearSession();
});

it('retorna false quando user_id não existe no banco', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Define um user_id que não existe no banco
    $_SESSION['user_id'] = 99999;

    $mw = new AccessMiddleware('view_dashboard');
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(['method' => 'GET', 'path' => '/']);
    });

    // Deve retornar 403 (Forbidden) porque o usuário não tem a permissão
    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Forbidden');
    expect($result['code'])->toBe(403);

    clearSession();
});

it('cada instância mantém sua própria permissão', function () {
    $mw1 = new AccessMiddleware('permission_1');
    $mw2 = new AccessMiddleware('permission_2');
    $mw3 = new AccessMiddleware('permission_3');

    $reflection = new ReflectionClass(AccessMiddleware::class);
    $property = $reflection->getProperty('permission');
    $property->setAccessible(true);

    expect($property->getValue($mw1))->toBe('permission_1');
    expect($property->getValue($mw2))->toBe('permission_2');
    expect($property->getValue($mw3))->toBe('permission_3');
});
