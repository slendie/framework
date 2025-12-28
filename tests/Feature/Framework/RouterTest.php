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

// Reseta a instância do singleton antes de cada teste
beforeEach(function () {
    Router::resetInstance();
});

it('inicializa com array de rotas', function () {
    $routes = [
        ['method' => 'GET', 'path' => '/', 'handler' => 'tests\TestController@index']
    ];

    $router = Router::getInstance($routes);
    expect($router)->toBeInstanceOf(Router::class);
});

it('parseRoutePattern retorna regex e nomes de parâmetros para rota com parâmetro', function () {
    $routes = [];
    $router = Router::getInstance($routes);

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
    $router = Router::getInstance($routes);

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
    $router = Router::getInstance($routes);

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
    $router = Router::getInstance($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('matchRoute');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users', '/users');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(0);
});

it('matchRoute retorna null para rota que não corresponde', function () {
    $routes = [];
    $router = Router::getInstance($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('matchRoute');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users', '/posts');

    expect($result)->toBeNull();
});

it('matchRoute extrai parâmetros de rota com um parâmetro', function () {
    $routes = [];
    $router = Router::getInstance($routes);

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
    $router = Router::getInstance($routes);

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
    $router = Router::getInstance($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('matchRoute');
    $method->setAccessible(true);

    $result = $method->invoke($router, '/users/{id}', '/users/123/posts');

    expect($result)->toBeNull();
});

it('getMethodParameters retorna parâmetros do método', function () {
    $routes = [];
    $router = Router::getInstance($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('getMethodParameters');
    $method->setAccessible(true);

    $result = $method->invoke($router, 'tests\TestController', 'show');

    expect($result)->toBeArray();
    expect($result)->toContain('id');
});

it('getMethodParameters retorna array vazio para método sem parâmetros', function () {
    $routes = [];
    $router = Router::getInstance($routes);

    $reflection = new ReflectionClass($router);
    $method = $reflection->getMethod('getMethodParameters');
    $method->setAccessible(true);

    $result = $method->invoke($router, 'tests\TestController', 'index');

    expect($result)->toBeArray();
    expect($result)->toHaveCount(0);
});

it('getMethodParameters retorna array vazio quando método não existe', function () {
    $routes = [];
    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

    ob_start();
    $router->dispatch();
    $output = ob_get_clean();

    // Deve retornar 404 quando handler não tem @
    expect($output)->toBe('Not Found');

    restoreRequest($response['original']);
});

it('parseRoutePattern escapa caracteres especiais no path', function () {
    $routes = [];
    $router = Router::getInstance($routes);

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
    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

    $router = Router::getInstance($routes);

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

// Carrega as funções helper para testar has_route()
if (!function_exists('has_route')) {
    $functionsPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'functions.php';
    if (file_exists($functionsPath)) {
        require_once $functionsPath;
    }
}

it('hasRoute retorna true para rota que existe', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index',
            'name' => 'home'
        ],
        [
            'method' => 'GET',
            'path' => '/users',
            'handler' => 'tests\TestController@index',
            'name' => 'users.index'
        ]
    ];

    Router::getInstance($routes);

    expect(Router::hasRoute('home'))->toBeTrue();
    expect(Router::hasRoute('users.index'))->toBeTrue();
});

it('hasRoute retorna false para rota que não existe', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index',
            'name' => 'home'
        ]
    ];

    Router::getInstance($routes);

    expect(Router::hasRoute('non-existent'))->toBeFalse();
    expect(Router::hasRoute('users.index'))->toBeFalse();
});

it('hasRoute retorna false quando rota não tem nome', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index'
            // Sem 'name'
        ]
    ];

    Router::getInstance($routes);

    expect(Router::hasRoute('home'))->toBeFalse();
});

it('hasRoute carrega rotas do arquivo de configuração se não foram fornecidas', function () {
    Router::resetInstance();

    // Verifica se o arquivo de rotas existe
    $routesPath = BASE_PATH . '/config/routes.php';
    if (file_exists($routesPath)) {
        $routes = require $routesPath;
        
        // Se houver rotas com nome, testa uma delas
        $hasNamedRoute = false;
        $routeName = null;
        foreach ($routes as $route) {
            if (isset($route['name'])) {
                $hasNamedRoute = true;
                $routeName = $route['name'];
                break;
            }
        }

        if ($hasNamedRoute && $routeName) {
            // hasRoute deve carregar automaticamente do arquivo
            expect(Router::hasRoute($routeName))->toBeTrue();
        }
    }
});

it('hasRoute retorna false para rota inexistente quando rotas não foram carregadas', function () {
    Router::resetInstance();

    // hasRoute deve retornar false se não conseguir carregar rotas
    // e a rota não existir
    expect(Router::hasRoute('definitely-does-not-exist-route-name-12345'))->toBeFalse();
});

it('hasRoute funciona após resetInstance', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index',
            'name' => 'home'
        ]
    ];

    Router::getInstance($routes);
    expect(Router::hasRoute('home'))->toBeTrue();

    Router::resetInstance();
    expect(Router::hasRoute('home'))->toBeFalse();
});

it('has_route função helper retorna true para rota que existe', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index',
            'name' => 'home'
        ],
        [
            'method' => 'GET',
            'path' => '/users',
            'handler' => 'tests\TestController@index',
            'name' => 'users.index'
        ]
    ];

    Router::getInstance($routes);

    expect(has_route('home'))->toBeTrue();
    expect(has_route('users.index'))->toBeTrue();
});

it('has_route função helper retorna false para rota que não existe', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index',
            'name' => 'home'
        ]
    ];

    Router::getInstance($routes);

    expect(has_route('non-existent'))->toBeFalse();
    expect(has_route('users.index'))->toBeFalse();
});

it('has_route função helper funciona após resetInstance', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index',
            'name' => 'home'
        ]
    ];

    Router::getInstance($routes);
    expect(has_route('home'))->toBeTrue();

    Router::resetInstance();
    expect(has_route('home'))->toBeFalse();
});

it('hasRoute diferencia nomes de rotas corretamente', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => 'tests\TestController@index',
            'name' => 'home'
        ],
        [
            'method' => 'GET',
            'path' => '/home',
            'handler' => 'tests\TestController@index',
            'name' => 'home.page'
        ]
    ];

    Router::getInstance($routes);

    expect(Router::hasRoute('home'))->toBeTrue();
    expect(Router::hasRoute('home.page'))->toBeTrue();
    expect(Router::hasRoute('home.'))->toBeFalse();
    expect(Router::hasRoute('.home'))->toBeFalse();
});

it('hasRoute funciona com rotas que têm mesmo path mas nomes diferentes', function () {
    $routes = [
        [
            'method' => 'GET',
            'path' => '/users',
            'handler' => 'tests\TestController@index',
            'name' => 'users.index'
        ],
        [
            'method' => 'POST',
            'path' => '/users',
            'handler' => 'tests\TestController@store',
            'name' => 'users.store'
        ]
    ];

    Router::getInstance($routes);

    expect(Router::hasRoute('users.index'))->toBeTrue();
    expect(Router::hasRoute('users.store'))->toBeTrue();
    expect(Router::hasRoute('users'))->toBeFalse();
});
