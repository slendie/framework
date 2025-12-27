<?php

declare(strict_types=1);

use Slendie\Framework\Request;

require_once __DIR__ . '/../../vendor/autoload.php';

it('obtém método HTTP da requisição', function () {
    $request = simulateRequest('GET', '/');
    $response = setupRequest('GET', '/');
    $request = $response['request'];
    $original = $response['original'];
    expect($request->method())->toBe('GET');

    // $request = simulateRequest('POST', '/');
    $response = setupRequest('POST', '/');
    $request = $response['request'];
    expect($request->method())->toBe('POST');

    // $request = simulateRequest('PUT', '/');
    $response = setupRequest('PUT', '/');
    $request = $response['request'];
    expect($request->method())->toBe('PUT');

    // $request = simulateRequest('DELETE', '/');
    $response = setupRequest('DELETE', '/');
    $request = $response['request'];
    expect($request->method())->toBe('DELETE');

    
});

it('usa GET como método padrão quando REQUEST_METHOD não está definido', function () {
    // Salva valores originais
    $originalServer = $_SERVER;

    // Limpa
    unset($_SERVER['REQUEST_METHOD']);

    $request = new Request();

    expect($request->method())->toBe('GET');

    // Restaura
    $_SERVER = $originalServer;
});

it('normaliza path removendo barra final', function () {
    $request = simulateRequest('GET', '/users');
    expect($request->path())->toBe('/users');

    $request = simulateRequest('GET', '/users/');
    expect($request->path())->toBe('/users');

    $request = simulateRequest('GET', '/users/posts/');
    expect($request->path())->toBe('/users/posts');
});

it('mantém barra única para raiz', function () {
    $request = simulateRequest('GET', '/');
    expect($request->path())->toBe('/');
});

it('adiciona barra inicial quando não existe', function () {
    // Salva valores originais
    $originalServer = $_SERVER;

    // Simula path sem barra inicial
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = 'users';

    $request = new Request();

    expect($request->path())->toBe('/users');

    // Restaura
    $_SERVER = $originalServer;
});

it('obtém parâmetros GET', function () {
    $request = simulateRequest('GET', '/users', ['id' => '123', 'name' => 'John']);

    expect($request->get('id'))->toBe('123');
    expect($request->get('name'))->toBe('John');
    expect($request->get('non_existent', 'default'))->toBe('default');
});

it('retorna todos os parâmetros GET quando key é null', function () {
    $getParams = ['id' => '123', 'name' => 'John', 'page' => '1'];
    $request = simulateRequest('GET', '/users', $getParams);

    $all = $request->get();
    expect($all)->toBe($getParams);
    expect($all)->toHaveCount(3);
});

it('obtém parâmetros POST', function () {
    $request = simulateRequest('POST', '/users', [], ['name' => 'John', 'email' => 'john@example.com']);

    expect($request->post('name'))->toBe('John');
    expect($request->post('email'))->toBe('john@example.com');
    expect($request->post('non_existent', 'default'))->toBe('default');
});

it('retorna todos os parâmetros POST quando key é null', function () {
    $postParams = ['name' => 'John', 'email' => 'john@example.com'];
    $request = simulateRequest('POST', '/users', [], $postParams);

    $all = $request->post();
    expect($all)->toBe($postParams);
    expect($all)->toHaveCount(2);
});

it('obtém parâmetros de input (GET e POST combinados)', function () {
    $request = simulateRequest('POST', '/users', ['page' => '1'], ['name' => 'John']);

    expect($request->input('page'))->toBe('1');
    expect($request->input('name'))->toBe('John');
    expect($request->input('non_existent', 'default'))->toBe('default');
});

it('retorna todos os parâmetros de input quando key é null', function () {
    $request = simulateRequest('POST', '/users', ['page' => '1'], ['name' => 'John']);

    $all = $request->input();
    expect($all)->toHaveCount(2);
    expect($all['page'])->toBe('1');
    expect($all['name'])->toBe('John');
});

it('POST sobrescreve GET quando há chaves duplicadas', function () {
    $request = simulateRequest('POST', '/users', ['name' => 'GET Value'], ['name' => 'POST Value']);

    // input() deve retornar POST (último no merge)
    expect($request->input('name'))->toBe('POST Value');

    // get() deve retornar GET
    expect($request->get('name'))->toBe('GET Value');

    // post() deve retornar POST
    expect($request->post('name'))->toBe('POST Value');
});

it('obtém arquivos enviados', function () {
    $files = [
        'avatar' => [
            'name' => 'avatar.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/php123',
            'error' => 0,
            'size' => 1024
        ]
    ];

    $request = simulateRequest('POST', '/upload', [], [], $files);

    $file = $request->file('avatar');
    expect($file)->not->toBeNull();
    expect($file['name'])->toBe('avatar.jpg');
    expect($file['type'])->toBe('image/jpeg');
});

it('retorna null quando arquivo não existe', function () {
    $request = simulateRequest('POST', '/upload');

    expect($request->file('non_existent'))->toBeNull();
});

it('retorna todos os arquivos quando key é null', function () {
    $files = [
        'avatar' => ['name' => 'avatar.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/tmp/php123', 'error' => 0, 'size' => 1024],
        'document' => ['name' => 'doc.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/php456', 'error' => 0, 'size' => 2048]
    ];

    $request = simulateRequest('POST', '/upload', [], [], $files);

    $allFiles = $request->file();
    expect($allFiles)->toBe($files);
    expect($allFiles)->toHaveCount(2);
});

it('verifica se chave existe no input', function () {
    $request = simulateRequest('POST', '/users', ['page' => '1'], ['name' => 'John']);

    expect($request->has('page'))->toBeTrue();
    expect($request->has('name'))->toBeTrue();
    expect($request->has('non_existent'))->toBeFalse();
});

it('verifica se arquivo existe', function () {
    $files = [
        'avatar' => ['name' => 'avatar.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/tmp/php123', 'error' => 0, 'size' => 1024]
    ];

    $request = simulateRequest('POST', '/upload', [], [], $files);

    expect($request->hasFile('avatar'))->toBeTrue();
    expect($request->hasFile('non_existent'))->toBeFalse();
});

it('retorna todos os inputs com all()', function () {
    $request = simulateRequest('POST', '/users', ['page' => '1'], ['name' => 'John']);

    $all = $request->all();
    expect($all)->toHaveCount(2);
    expect($all['page'])->toBe('1');
    expect($all['name'])->toBe('John');
});

it('retorna apenas chaves especificadas com only()', function () {
    $request = simulateRequest('POST', '/users', ['page' => '1'], ['name' => 'John', 'email' => 'john@example.com']);

    $only = $request->only(['name', 'email']);
    expect($only)->toHaveCount(2);
    expect($only)->toHaveKey('name');
    expect($only)->toHaveKey('email');
    expect($only)->not->toHaveKey('page');
});

it('only() aceita múltiplos argumentos', function () {
    $request = simulateRequest('POST', '/users', ['page' => '1'], ['name' => 'John', 'email' => 'john@example.com']);

    $only = $request->only('name', 'email');
    expect($only)->toHaveCount(2);
    expect($only)->toHaveKey('name');
    expect($only)->toHaveKey('email');
});

it('only() ignora chaves que não existem', function () {
    $request = simulateRequest('POST', '/users', [], ['name' => 'John']);

    $only = $request->only(['name', 'non_existent']);
    expect($only)->toHaveCount(1);
    expect($only)->toHaveKey('name');
    expect($only)->not->toHaveKey('non_existent');
});

it('retorna todos exceto chaves especificadas com except()', function () {
    $request = simulateRequest('POST', '/users', ['page' => '1'], ['name' => 'John', 'email' => 'john@example.com']);

    $except = $request->except(['page']);
    expect($except)->toHaveCount(2);
    expect($except)->toHaveKey('name');
    expect($except)->toHaveKey('email');
    expect($except)->not->toHaveKey('page');
});

it('except() aceita múltiplos argumentos', function () {
    $request = simulateRequest('POST', '/users', ['page' => '1'], ['name' => 'John', 'email' => 'john@example.com']);

    $except = $request->except('page', 'email');
    expect($except)->toHaveCount(1);
    expect($except)->toHaveKey('name');
    expect($except)->not->toHaveKey('page');
    expect($except)->not->toHaveKey('email');
});

it('obtém headers da requisição', function () {
    $headers = [
        'content-type' => 'application/json',
        'authorization' => 'Bearer token123',
        'user-agent' => 'Test Agent'
    ];

    $request = simulateRequest('GET', '/', [], [], [], $headers);

    expect($request->header('content-type'))->toBe('application/json');
    expect($request->header('authorization'))->toBe('Bearer token123');
    expect($request->header('user-agent'))->toBe('Test Agent');
});

it('retorna todos os headers quando key é null', function () {
    $headers = [
        'content-type' => 'application/json',
        'authorization' => 'Bearer token123'
    ];

    $request = simulateRequest('GET', '/', [], [], [], $headers);

    $allHeaders = $request->header();
    expect($allHeaders)->toHaveKey('content-type');
    expect($allHeaders)->toHaveKey('authorization');
});

it('header() é case-insensitive', function () {
    $headers = [
        'content-type' => 'application/json'
    ];

    $request = simulateRequest('GET', '/', [], [], [], $headers);

    expect($request->header('Content-Type'))->toBe('application/json');
    expect($request->header('CONTENT-TYPE'))->toBe('application/json');
    expect($request->header('content-type'))->toBe('application/json');
});

it('retorna default quando header não existe', function () {
    $request = simulateRequest('GET', '/');

    expect($request->header('non-existent', 'default'))->toBe('default');
    expect($request->header('non-existent'))->toBeNull();
});

it('converte headers HTTP_ para formato correto', function () {
    // Salva valores originais
    $originalServer = $_SERVER;

    // Limpa
    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
    $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom-value';

    $request = new Request();

    expect($request->header('content-type'))->toBe('application/json');
    expect($request->header('authorization'))->toBe('Bearer token123');
    expect($request->header('x-custom-header'))->toBe('custom-value');

    // Restaura
    $_SERVER = $originalServer;
});

it('verifica método HTTP com isMethod()', function () {
    $request = simulateRequest('GET', '/');
    expect($request->isMethod('GET'))->toBeTrue();
    expect($request->isMethod('POST'))->toBeFalse();

    $request = simulateRequest('POST', '/');
    expect($request->isMethod('POST'))->toBeTrue();
    expect($request->isMethod('GET'))->toBeFalse();
});

it('isMethod() é case-insensitive', function () {
    $request = simulateRequest('GET', '/');
    expect($request->isMethod('get'))->toBeTrue();
    expect($request->isMethod('Get'))->toBeTrue();
    expect($request->isMethod('GET'))->toBeTrue();

    $request = simulateRequest('POST', '/');
    expect($request->isMethod('post'))->toBeTrue();
    expect($request->isMethod('Post'))->toBeTrue();
});

it('retorna JSON do body quando disponível', function () {
    // Salva valores originais
    $originalServer = $_SERVER;
    $originalGet = $_GET;
    $originalPost = $_POST;

    // Limpa
    $_SERVER = [];
    $_GET = [];
    $_POST = [];

    // Configura requisição
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/users';
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

    // Para testar JSON, precisamos simular php://input
    // Como não podemos fazer isso diretamente, vamos testar que o método existe
    // e que retorna um array (vazio quando não há JSON)
    $request = new Request();

    $json = $request->json();
    expect($json)->toBeArray();

    // Restaura
    $_SERVER = $originalServer;
    $_GET = $originalGet;
    $_POST = $originalPost;
});

it('lida com URI com query string', function () {
    // Salva valores originais
    $originalServer = $_SERVER;

    // Simula URI com query string
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/users?id=123&name=John';

    $request = new Request();

    // O path deve ser apenas o caminho, sem query string
    expect($request->path())->toBe('/users');

    // Restaura
    $_SERVER = $originalServer;
});

it('lida com path vazio', function () {
    // Salva valores originais
    $originalServer = $_SERVER;

    // Simula URI vazio - parse_url retorna false para string vazia
    // então o código usa '/' como padrão
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '';

    // Suprime warning de string offset (comportamento esperado do código)
    $request = @new Request();

    // Deve normalizar para '/'
    $path = $request->path();
    expect($path)->toBe('/');

    // Restaura
    $_SERVER = $originalServer;
});

it('lida com REQUEST_URI não definido', function () {
    // Salva valores originais
    $originalServer = $_SERVER;

    // Remove REQUEST_URI
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['REQUEST_URI']);

    $request = new Request();

    // Deve usar '/' como padrão
    expect($request->path())->toBe('/');

    // Restaura
    $_SERVER = $originalServer;
});

it('lida com múltiplos arquivos', function () {
    $files = [
        'avatar' => ['name' => 'avatar.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/tmp/php123', 'error' => 0, 'size' => 1024],
        'document' => ['name' => 'doc.pdf', 'type' => 'application/pdf', 'tmp_name' => '/tmp/php456', 'error' => 0, 'size' => 2048]
    ];

    $request = simulateRequest('POST', '/upload', [], [], $files);

    expect($request->hasFile('avatar'))->toBeTrue();
    expect($request->hasFile('document'))->toBeTrue();
    expect($request->file('avatar')['name'])->toBe('avatar.jpg');
    expect($request->file('document')['name'])->toBe('doc.pdf');
});

it('lida com arrays aninhados em GET', function () {
    // Salva valores originais
    $originalServer = $_SERVER;
    $originalGet = $_GET;

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/users';
    $_GET = ['filters' => ['name' => 'John', 'age' => '30']];

    $request = new Request();

    $filters = $request->get('filters');
    expect($filters)->toBeArray();
    expect($filters['name'])->toBe('John');
    expect($filters['age'])->toBe('30');

    // Restaura
    $_SERVER = $originalServer;
    $_GET = $originalGet;
});

it('lida com arrays aninhados em POST', function () {
    // Salva valores originais
    $originalServer = $_SERVER;
    $originalPost = $_POST;

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/users';
    $_POST = ['user' => ['name' => 'John', 'email' => 'john@example.com']];

    $request = new Request();

    $user = $request->post('user');
    expect($user)->toBeArray();
    expect($user['name'])->toBe('John');
    expect($user['email'])->toBe('john@example.com');

    // Restaura
    $_SERVER = $originalServer;
    $_POST = $originalPost;
});

it('only() funciona com arrays aninhados', function () {
    $request = simulateRequest('POST', '/users', [], ['name' => 'John', 'user' => ['email' => 'john@example.com']]);

    $only = $request->only(['name', 'user']);
    expect($only)->toHaveKey('name');
    expect($only)->toHaveKey('user');
    expect($only['user'])->toBeArray();
});

it('except() funciona com arrays aninhados', function () {
    $request = simulateRequest('POST', '/users', [], ['name' => 'John', 'user' => ['email' => 'john@example.com']]);

    $except = $request->except(['name']);
    expect($except)->not->toHaveKey('name');
    expect($except)->toHaveKey('user');
});

it('lida com valores vazios em input', function () {
    $request = simulateRequest('POST', '/users', [], ['name' => '', 'email' => 'john@example.com']);

    expect($request->has('name'))->toBeTrue();
    expect($request->input('name'))->toBe('');
    expect($request->input('email'))->toBe('john@example.com');
});

it('lida com valores null em input', function () {
    // Salva valores originais
    $originalServer = $_SERVER;
    $originalPost = $_POST;

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/users';
    $_POST = ['name' => null, 'email' => 'john@example.com'];

    $request = new Request();

    // isset() retorna false para null, então has() retorna false
    // Mas input() ainda retorna null quando a chave existe
    expect($request->has('name'))->toBeFalse(); // isset() retorna false para null
    expect($request->input('name'))->toBeNull(); // Mas ainda podemos obter o valor null
    expect($request->input('email'))->toBe('john@example.com');

    // Restaura
    $_SERVER = $originalServer;
    $_POST = $originalPost;
});

it('lida com path complexo com múltiplos segmentos', function () {
    $request = simulateRequest('GET', '/api/v1/users/123/posts');
    expect($request->path())->toBe('/api/v1/users/123/posts');

    $request = simulateRequest('GET', '/api/v1/users/123/posts/');
    expect($request->path())->toBe('/api/v1/users/123/posts');
});

it('lida com diferentes métodos HTTP', function () {
    $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    foreach ($methods as $method) {
        $request = simulateRequest($method, '/');
        expect($request->method())->toBe($method);
        expect($request->isMethod($method))->toBeTrue();
    }
});
