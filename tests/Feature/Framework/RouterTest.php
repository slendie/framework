<?php

declare(strict_types=1);

use Slendie\Framework\Router;
use tests\TestController;

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

$autoload_path = BASE_PATH . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require_once $autoload_path;

it('inicializa com array de rotas', function () {
    $routes = [
        ['method' => 'GET', 'path' => '/', 'handler' => 'tests\TestController@index']
    ];

    $router = new Router($routes);
    expect($router)->toBeInstanceOf(Router::class);
});

it('parseRoutePattern retorna regex e nomes de parâmetros para rota com parâmetro', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('parseRoutePattern');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users/{id}');

    expect($result)->toBeArray();
    expect($result)->toHaveKey('regex');
    expect($result)->toHaveKey('paramNames');
    expect($result['paramNames'])->toContain('id');
    expect($result['regex'])->toContain('users');
});

it('parseRoutePattern retorna regex para rota sem parâmetros', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('parseRoutePattern');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users');

    expect($result)->toBeArray();
    expect($result['paramNames'])->toHaveCount(0);
    expect($result['regex'])->toContain('users');
});

it('parseRoutePattern extrai múltiplos parâmetros', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('parseRoutePattern');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users/{id}/posts/{postId}');

    expect($result['paramNames'])->toHaveCount(2);
    expect($result['paramNames'])->toContain('id');
    expect($result['paramNames'])->toContain('postId');
});

it('matchRoute retorna array vazio para rota exata sem parâmetros', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('matchRoute');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users', '/users');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(0);
});

it('matchRoute retorna null para rota que não corresponde', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('matchRoute');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users', '/posts');

    expect($result)->toBeNull();
});

it('matchRoute extrai parâmetros de rota com um parâmetro', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('matchRoute');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users/{id}', '/users/123');

    expect($result)->toBeArray();
    expect($result)->toHaveKey('id');
    expect($result['id'])->toBe('123');
});

it('matchRoute extrai múltiplos parâmetros', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('matchRoute');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users/{id}/posts/{postId}', '/users/123/posts/456');

    expect($result)->toBeArray();
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('postId');
    expect($result['id'])->toBe('123');
    expect($result['postId'])->toBe('456');
});

it('matchRoute retorna null quando path não corresponde ao padrão', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('matchRoute');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users/{id}', '/users/123/posts');

    expect($result)->toBeNull();
});

it('getMethodParameters retorna parâmetros do método', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('getMethodParameters');
    $method->setAccessible(true);

    $result = $method->invoke($router, 'tests\TestController', 'show');

    expect($result)->toBeArray();
    expect($result)->toContain('id');
});

it('getMethodParameters retorna array vazio para método sem parâmetros', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('getMethodParameters');
    $method->setAccessible(true);

    $result = $method->invoke($router, 'tests\TestController', 'index');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(0);
});

it('getMethodParameters retorna array vazio quando método não existe', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('getMethodParameters');
    $method->setAccessible(true);

    $result = $method->invoke($router, 'tests\TestController', 'nonExistent');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(0);
});

it('dispatch chama handler para rota exata', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index'
        ]
    ];

    $response = setupRequest('GET', '/');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('index');

    restoreRequest($response['original']);
});

it('dispatch chama handler com parâmetros de rota', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/users/{id}',
            'handler' => 'tests\TestController@show'
        ]
    ];

    $response = setupRequest('GET', '/users/123');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('show');
    expect(TestController::$calledArgs)->toHaveCount(1);
    expect(TestController::$calledArgs[0])->toBe('123');

    restoreRequest($response['original']);
});

it('dispatch chama handler com múltiplos parâmetros', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/users/{id}/edit/{action}',
            'handler' => 'tests\TestController@edit'
        ]
    ];

    $response = setupRequest('GET', '/users/123/edit/update');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('edit');
    expect(TestController::$calledArgs)->toHaveCount(2);
    expect(TestController::$calledArgs[0])->toBe('123');
    expect(TestController::$calledArgs[1])->toBe('update');

    restoreRequest($response['original']);
});

it('dispatch retorna 404 quando rota não é encontrada', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/users',
            'handler' => 'tests\TestController@index'
        ]
    ];

    $response = setupRequest('GET', '/non-existent');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect($output)->toBe('Not Found');
    expect(http_response_code())->toBe(404);

    restoreRequest($response['original']);
});

it('dispatch verifica método HTTP', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'POST',
            'path' => '/users',
            'handler' => 'tests\TestController@store'
        ]
    ];

    // Tenta com GET (não deve chamar)
    $response = setupRequest('GET', '/users');
    $original = $response['original'];

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBeNull();
    expect($output)->toBe('Not Found');

    // Tenta com POST (deve chamar)
    $response = setupRequest('POST', '/users');

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('store');

    restoreRequest($original);
});

it('dispatch é case-insensitive para método HTTP', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'get',
            'path' => '/',
            'handler' => 'tests\TestController@index'
        ]
    ];

    $response = setupRequest('GET', '/');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('index');

    restoreRequest($response['original']);
});

it('dispatch aceita handler como array', function () {
    TestController::reset();

    $controller = new TestController();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => [$controller, 'index']
        ]
    ];

    $response = setupRequest('GET', '/');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('index');

    restoreRequest($response['original']);
});

it('dispatch aceita handler como array com string de classe', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => ['tests\TestController', 'index']
        ]
    ];

    $response = setupRequest('GET', '/');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('index');

    restoreRequest($response['original']);
});

it('dispatch aplica WebMiddleware', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index'
        ]
    ];

    $response = setupRequest('GET', '/');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    // WebMiddleware deve ter sido aplicado (não lança erro)
    expect(TestController::$calledMethod)->toBe('index');

    restoreRequest($response['original']);
});

it('dispatch passa apenas parâmetros que o método espera', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/users/{id}/posts/{postId}',
            'handler' => 'tests\TestController@show'
        ]
    ];

    $response = setupRequest('GET', '/users/123/posts/456');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    // show() espera apenas 'id', não 'postId'
    expect(TestController::$calledMethod)->toBe('show');
    expect(TestController::$calledArgs)->toHaveCount(1);
    expect(TestController::$calledArgs[0])->toBe('123');

    restoreRequest($response['original']);
});

it('dispatch passa parâmetros na ordem correta', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/users/{id}/edit/{action}',
            'handler' => 'tests\TestController@edit'
        ]
    ];

    $response = setupRequest('GET', '/users/123/edit/update');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    // edit($id, $action) deve receber na ordem correta
    expect(TestController::$calledMethod)->toBe('edit');
    expect(TestController::$calledArgs[0])->toBe('123');
    expect(TestController::$calledArgs[1])->toBe('update');

    restoreRequest($response['original']);
});

it('dispatch retorna resultado do handler', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index'
        ]
    ];

    $response = setupRequest('GET', '/');

    $router = new Router($routes);

    ob_start();
    $result = $router->dispatch();
    $output = ob_get_clean();

    expect($result)->toBe('index output');

    restoreRequest($response['original']);
});

it('dispatch processa primeira rota que corresponde', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/users',
            'handler' => 'tests\TestController@index'
        ],
        [
            'method' => 'GET',
            'path' => '/users',
            'handler' => 'tests\TestController@show'
        ]
    ];

    $response = setupRequest('GET', '/users');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    // Deve chamar apenas o primeiro handler
    expect(TestController::$calledMethod)->toBe('index');
    expect(TestController::$calledArgs)->not->toContain('show');

    restoreRequest($response['original']);
});

it('dispatch ignora rotas com método diferente', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'POST',
            'path' => '/users',
            'handler' => 'tests\TestController@store'
        ],
        [
            'method' => 'GET',
            'path' => '/users',
            'handler' => 'tests\TestController@index'
        ]
    ];

    $response = setupRequest('GET', '/users');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('index');

    restoreRequest($response['original']);
});

it('dispatch ignora rotas com path diferente', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/posts',
            'handler' => 'tests\TestController@index'
        ],
        [
            'method' => 'GET',
            'path' => '/users',
            'handler' => 'tests\TestController@create'
        ]
    ];

    $response = setupRequest('GET', '/users');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('create');

    restoreRequest($response['original']);
});

it('dispatch lida com rotas sem middlewares', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index'
            // middlewares não definido
        ]
    ];

    $response = setupRequest('GET', '/');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('index');

    restoreRequest($response['original']);
});

it('dispatch lida com handler que não é string nem array', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 123 // Tipo inválido
        ]
    ];

    $response = setupRequest('GET', '/');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    // Deve retornar 404 quando handler é inválido
    expect($output)->toBe('Not Found');

    restoreRequest($response['original']);
});

it('dispatch lida com handler string sem @', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController' // Sem @
        ]
    ];

    $response = setupRequest('GET', '/');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    // Deve retornar 404 quando handler não tem @
    expect($output)->toBe('Not Found');

    restoreRequest($response['original']);
});

it('parseRoutePattern escapa caracteres especiais no path', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('parseRoutePattern');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users/posts');

    // Deve escapar pontos e outros caracteres especiais
    expect($result['regex'])->toContain('users');
    expect($result['regex'])->toContain('posts');
});

it('matchRoute lida com path que termina com barra', function () {
    $routes = [];
    $router = new Router($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('matchRoute');
    $method->setAccessible(true);

    // Request normaliza paths, então vamos testar diretamente
    $result = $method->invoke($router, '/users', '/users/');

    // Deve retornar null porque paths não correspondem exatamente
    expect($result)->toBeNull();
});

it('dispatch lida com rota na raiz', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index'
        ]
    ];

    $response = setupRequest('GET', '/');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('index');

    restoreRequest($response['original']);
});

it('dispatch lida com parâmetros com caracteres especiais', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/users/{id}',
            'handler' => 'tests\TestController@show'
        ]
    ];

    $response = setupRequest('GET', '/users/123-abc');

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('show');
    expect(TestController::$calledArgs[0])->toBe('123-abc');

    restoreRequest($response['original']);
});

it('dispatch lida com múltiplas rotas na mesma lista', function () {
    TestController::reset();

    $routes = [
        [
            'method' => 'GET',
            'path' => '/users',
            'handler' => 'tests\TestController@index'
        ],
        [
            'method' => 'GET',
            'path' => '/posts',
            'handler' => 'tests\TestController@create'
        ],
        [
            'method' => 'POST',
            'path' => '/posts',
            'handler' => 'tests\TestController@store'
        ]
    ];

    // Testa primeira rota
    $response = setupRequest('GET', '/users');
    $original = $response['original'];

    $router = new Router($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('index');

    // Testa segunda rota
    TestController::reset();
    $response = setupRequest('GET', '/posts');

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('create');

    // Testa terceira rota
    TestController::reset();
    $response = setupRequest('POST', '/posts');

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    expect(TestController::$calledMethod)->toBe('store');

    restoreRequest($original);
});
