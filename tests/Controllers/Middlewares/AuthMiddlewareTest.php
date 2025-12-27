<?php

declare(strict_types=1);

use Slendie\Controllers\Middlewares\AuthMiddleware;

require_once __DIR__ . '/../../../vendor/autoload.php';

it('bloqueia quando não autenticado', function () {
    clearSession();

    $mw = new AuthMiddleware();
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(['method' => 'GET', 'path' => '/']);
    });

    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Unauthorized');
    expect($result['code'])->toBe(401);
});

it('permite acesso quando autenticado', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = 1;

    $mw = new AuthMiddleware();
    $result = $mw->handle(['method' => 'GET', 'path' => '/']);

    expect($result)->toBeTrue();

    clearSession();
});

it('bloqueia quando user_id é null', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = null;

    $mw = new AuthMiddleware();
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

    $mw = new AuthMiddleware();
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

    $mw = new AuthMiddleware();
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(['method' => 'GET', 'path' => '/']);
    });

    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Unauthorized');
    expect($result['code'])->toBe(401);

    clearSession();
});

it('bloqueia quando user_id é false', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = false;

    $mw = new AuthMiddleware();
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(['method' => 'GET', 'path' => '/']);
    });

    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Unauthorized');
    expect($result['code'])->toBe(401);

    clearSession();
});

it('permite acesso quando user_id é string numérica', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = '123';

    $mw = new AuthMiddleware();
    $result = $mw->handle(['method' => 'GET', 'path' => '/']);

    expect($result)->toBeTrue();

    clearSession();
});

it('permite acesso quando user_id é número positivo', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = 42;

    $mw = new AuthMiddleware();
    $result = $mw->handle(['method' => 'GET', 'path' => '/']);

    expect($result)->toBeTrue();

    clearSession();
});

it('define código HTTP 401 quando não autenticado', function () {
    clearSession();

    $mw = new AuthMiddleware();

    ob_start();
    $mw->handle(['method' => 'GET', 'path' => '/']);
    ob_end_clean();

    // Verifica que o código HTTP foi definido
    if (function_exists('http_response_code')) {
        expect(http_response_code())->toBe(401);
    }
});

it('imprime "Unauthorized" quando não autenticado', function () {
    clearSession();

    $mw = new AuthMiddleware();

    ob_start();
    $mw->handle(['method' => 'GET', 'path' => '/']);
    $output = ob_get_clean();

    expect($output)->toBe('Unauthorized');
});

it('não imprime nada quando autenticado', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = 1;

    $mw = new AuthMiddleware();

    ob_start();
    $mw->handle(['method' => 'GET', 'path' => '/']);
    $output = ob_get_clean();

    expect($output)->toBe('');

    clearSession();
});

it('funciona com diferentes tipos de request', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = 1;

    $mw = new AuthMiddleware();

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

it('bloqueia com diferentes tipos de request quando não autenticado', function () {
    clearSession();

    $mw = new AuthMiddleware();

    $requests = [
        ['method' => 'GET', 'path' => '/'],
        ['method' => 'POST', 'path' => '/login'],
        ['method' => 'PUT', 'path' => '/users/1'],
        ['method' => 'DELETE', 'path' => '/users/1'],
    ];

    foreach ($requests as $request) {
        $result = captureOutputAndCode(function () use ($mw, $request) {
            return $mw->handle($request);
        });

        expect($result['result'])->toBeFalse();
        expect($result['output'])->toBe('Unauthorized');
        expect($result['code'])->toBe(401);
    }
});

it('handle() aceita qualquer tipo de request como parâmetro', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['user_id'] = 1;

    $mw = new AuthMiddleware();

    // Testa com diferentes tipos de parâmetros
    $result1 = $mw->handle(null);
    $result2 = $mw->handle('string');
    $result3 = $mw->handle(123);
    $result4 = $mw->handle(['method' => 'GET']);
    $result5 = $mw->handle(new stdClass());

    // Todos devem retornar true quando autenticado
    expect($result1)->toBeTrue();
    expect($result2)->toBeTrue();
    expect($result3)->toBeTrue();
    expect($result4)->toBeTrue();
    expect($result5)->toBeTrue();

    clearSession();
});

it('handle() ignora o parâmetro request', function () {
    clearSession();

    $mw = new AuthMiddleware();

    // O middleware não usa o parâmetro $request, apenas verifica a sessão
    $result = captureOutputAndCode(function () use ($mw) {
        return $mw->handle(null);
    });

    expect($result['result'])->toBeFalse();
    expect($result['output'])->toBe('Unauthorized');

    // Agora com user_id definido
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = 1;

    $result2 = $mw->handle(null);
    expect($result2)->toBeTrue();

    clearSession();
});
