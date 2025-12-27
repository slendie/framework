<?php

declare(strict_types=1);

use Slendie\Controllers\Middlewares\WebMiddleware;
use Slendie\Framework\Request;

require_once __DIR__ . '/../../../vendor/autoload.php';

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

// Função auxiliar para resetar o WebMiddleware
function resetWebMiddleware()
{
    $reflection = new ReflectionClass(WebMiddleware::class);
    $property = $reflection->getProperty('request');
    $property->setAccessible(true);
    $property->setValue(null, null);
}

beforeEach(function () {
    resetWebMiddleware();
});

it('handle() armazena instância Request', function () {
    $env = setupRequest('GET', '/');
    $request = $env['request'];

    $mw = new WebMiddleware();
    $result = $mw->handle($request);

    expect($result)->toBeTrue();

    $storedRequest = WebMiddleware::getRequest();
    expect($storedRequest)->toBe($request);
    expect($storedRequest)->toBeInstanceOf(Request::class);

    restoreEnvironment($env['original']);
});

it('getRequest() retorna null quando nenhuma request foi armazenada', function () {
    resetWebMiddleware();

    $request = WebMiddleware::getRequest();

    expect($request)->toBeNull();
});

it('getRequest() retorna request armazenada', function () {
    $env = setupRequest('GET', '/');
    $request = $env['request'];

    $mw = new WebMiddleware();
    $mw->handle($request);

    $storedRequest = WebMiddleware::getRequest();

    expect($storedRequest)->toBe($request);
    expect($storedRequest)->toBeInstanceOf(Request::class);

    restoreEnvironment($env['original']);
});

it('handle() retorna true', function () {
    $env = setupRequest('GET', '/');
    $request = $env['request'];

    $mw = new WebMiddleware();
    $result = $mw->handle($request);

    expect($result)->toBeTrue();

    restoreEnvironment($env['original']);
});

it('handle() sobrescreve request anterior', function () {
    $env1 = setupRequest('GET', '/');
    $request1 = $env1['request'];

    $mw = new WebMiddleware();
    $mw->handle($request1);

    $stored1 = WebMiddleware::getRequest();
    expect($stored1)->toBe($request1);

    // Cria nova request
    restoreEnvironment($env1['original']);
    $env2 = setupRequest('POST', '/login');
    $request2 = $env2['request'];

    // Sobrescreve com nova request
    $mw->handle($request2);

    $stored2 = WebMiddleware::getRequest();
    expect($stored2)->toBe($request2);
    expect($stored2)->not->toBe($request1);

    restoreEnvironment($env2['original']);
});

it('request é compartilhada entre instâncias', function () {
    $env = setupRequest('GET', '/');
    $request = $env['request'];

    $mw1 = new WebMiddleware();
    $mw1->handle($request);

    $mw2 = new WebMiddleware();
    $storedRequest = WebMiddleware::getRequest();

    expect($storedRequest)->toBe($request);

    // Nova instância também pode acessar a mesma request
    $mw3 = new WebMiddleware();
    $storedRequest2 = WebMiddleware::getRequest();

    expect($storedRequest2)->toBe($request);

    restoreEnvironment($env['original']);
});

it('handle() aceita qualquer tipo de objeto', function () {
    $obj = new stdClass();
    $obj->test = 'value';

    $mw = new WebMiddleware();
    $result = $mw->handle($obj);

    expect($result)->toBeTrue();

    $stored = WebMiddleware::getRequest();
    expect($stored)->toBe($obj);
    expect($stored)->toBeInstanceOf(stdClass::class);
});

it('handle() aceita null', function () {
    $mw = new WebMiddleware();
    $result = $mw->handle(null);

    expect($result)->toBeTrue();

    $stored = WebMiddleware::getRequest();
    expect($stored)->toBeNull();
});

it('handle() aceita array', function () {
    $array = ['method' => 'GET', 'path' => '/'];

    $mw = new WebMiddleware();
    $result = $mw->handle($array);

    expect($result)->toBeTrue();

    $stored = WebMiddleware::getRequest();
    expect($stored)->toBe($array);
    expect($stored)->toBeArray();
});

it('handle() aceita string', function () {
    $string = 'test string';

    $mw = new WebMiddleware();
    $result = $mw->handle($string);

    expect($result)->toBeTrue();

    $stored = WebMiddleware::getRequest();
    expect($stored)->toBe($string);
    expect($stored)->toBeString();
});

it('handle() aceita integer', function () {
    $integer = 123;

    $mw = new WebMiddleware();
    $result = $mw->handle($integer);

    expect($result)->toBeTrue();

    $stored = WebMiddleware::getRequest();
    expect($stored)->toBe($integer);
    expect($stored)->toBeInt();
});

it('getRequest() retorna mesma instância após múltiplas chamadas', function () {
    $env = setupRequest('GET', '/');
    $request = $env['request'];

    $mw = new WebMiddleware();
    $mw->handle($request);

    $request1 = WebMiddleware::getRequest();
    $request2 = WebMiddleware::getRequest();
    $request3 = WebMiddleware::getRequest();

    expect($request1)->toBe($request2);
    expect($request2)->toBe($request3);
    expect($request1)->toBe($request);

    restoreEnvironment($env['original']);
});

it('funciona com diferentes tipos de Request', function () {
    $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
    $paths = ['/', '/users', '/users/1', '/login', '/api/data'];

    foreach ($methods as $method) {
        foreach ($paths as $path) {
            $env = setupRequest($method, $path);
            $request = $env['request'];

            $mw = new WebMiddleware();
            $mw->handle($request);

            $stored = WebMiddleware::getRequest();
            expect($stored)->toBe($request);
            expect($stored->method())->toBe($method);
            expect($stored->path())->toBe($path);

            restoreEnvironment($env['original']);
            resetWebMiddleware();
        }
    }
});

it('handle() pode ser chamado múltiplas vezes com mesma request', function () {
    $env = setupRequest('GET', '/');
    $request = $env['request'];

    $mw = new WebMiddleware();

    $mw->handle($request);
    $stored1 = WebMiddleware::getRequest();

    $mw->handle($request);
    $stored2 = WebMiddleware::getRequest();

    expect($stored1)->toBe($stored2);
    expect($stored1)->toBe($request);

    restoreEnvironment($env['original']);
});

it('getRequest() é método estático', function () {
    $env = setupRequest('GET', '/');
    $request = $env['request'];

    $mw = new WebMiddleware();
    $mw->handle($request);

    // Pode ser chamado sem instância
    $stored = WebMiddleware::getRequest();

    expect($stored)->toBe($request);

    restoreEnvironment($env['original']);
});

it('handle() não modifica a request armazenada', function () {
    $env = setupRequest('GET', '/users');
    $request = $env['request'];

    $mw = new WebMiddleware();
    $mw->handle($request);

    $stored = WebMiddleware::getRequest();
    $originalPath = $stored->path();

    // Modifica a request original (se possível)
    // Como Request tem propriedades privadas, não podemos modificar diretamente
    // Mas podemos verificar que a referência é mantida

    expect($stored->path())->toBe($originalPath);
    expect($stored)->toBe($request);

    restoreEnvironment($env['original']);
});

it('funciona corretamente quando request é substituída', function () {
    $env1 = setupRequest('GET', '/');
    $request1 = $env1['request'];

    $mw = new WebMiddleware();
    $mw->handle($request1);

    expect(WebMiddleware::getRequest())->toBe($request1);

    restoreEnvironment($env1['original']);

    $env2 = setupRequest('POST', '/login');
    $request2 = $env2['request'];

    $mw->handle($request2);

    expect(WebMiddleware::getRequest())->toBe($request2);
    expect(WebMiddleware::getRequest())->not->toBe($request1);

    restoreEnvironment($env2['original']);
});

it('handle() retorna true mesmo quando request é null', function () {
    resetWebMiddleware();

    $mw = new WebMiddleware();
    $result = $mw->handle(null);

    expect($result)->toBeTrue();
    expect(WebMiddleware::getRequest())->toBeNull();
});

it('handle() retorna true mesmo quando request é false', function () {
    $mw = new WebMiddleware();
    $result = $mw->handle(false);

    expect($result)->toBeTrue();
    expect(WebMiddleware::getRequest())->toBe(false);
});

it('handle() retorna true mesmo quando request é zero', function () {
    $mw = new WebMiddleware();
    $result = $mw->handle(0);

    expect($result)->toBeTrue();
    expect(WebMiddleware::getRequest())->toBe(0);
});

it('getRequest() retorna valor exato armazenado', function () {
    $values = [
        null,
        false,
        true,
        0,
        123,
        '',
        'string',
        [],
        ['key' => 'value'],
        new stdClass()
    ];

    foreach ($values as $value) {
        resetWebMiddleware();

        $mw = new WebMiddleware();
        $mw->handle($value);

        $stored = WebMiddleware::getRequest();

        if ($value === null) {
            expect($stored)->toBeNull();
        } elseif (is_object($value)) {
            expect($stored)->toBe($value);
        } else {
            expect($stored)->toBe($value);
        }
    }
});

it('handle() não lança exceção com qualquer valor', function () {
    $values = [
        null,
        false,
        true,
        0,
        -1,
        123.45,
        '',
        'string',
        [],
        ['key' => 'value'],
        new stdClass(),
        new Request()
    ];

    foreach ($values as $value) {
        resetWebMiddleware();

        $mw = new WebMiddleware();

        try {
            $result = $mw->handle($value);
            expect($result)->toBeTrue();
        } catch (Exception $e) {
            expect(false)->toBeTrue(); // Não deve lançar exceção
        }
    }
});

it('getRequest() retorna null após reset', function () {
    $env = setupRequest('GET', '/');
    $request = $env['request'];

    $mw = new WebMiddleware();
    $mw->handle($request);

    expect(WebMiddleware::getRequest())->toBe($request);

    resetWebMiddleware();

    expect(WebMiddleware::getRequest())->toBeNull();

    restoreEnvironment($env['original']);
});
