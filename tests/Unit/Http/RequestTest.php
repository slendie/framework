<?php

declare(strict_types=1);

use Slendie\Http\HttpMethod;
use Slendie\Http\Request;
use Slendie\Http\UploadedFile;

it('create() em GET põe os parâmetros na query string', function (): void {
    $request = Request::create('/posts?page=2', 'GET', ['sort' => 'asc']);

    expect($request->method())->toBe(HttpMethod::GET)
        ->and($request->query())->toBe(['page' => '2', 'sort' => 'asc'])
        ->and($request->path())->toBe('/posts')
        ->and($request->uri())->toBe('/posts?page=2&sort=asc')
        ->and($request->fullUrl())->toBe('http://localhost/posts?page=2&sort=asc')
        ->and($request->url())->toBe('http://localhost/posts');
});

it('normaliza o path', function (): void {
    expect(Request::create('/posts/')->path())->toBe('/posts')
        ->and(Request::create('/')->path())->toBe('/')
        ->and(Request::create('//a///b/')->path())->toBe('/a/b')
        ->and(Request::create('/')->url())->toBe('http://localhost');
});

it('faz method spoofing por _method e por X-HTTP-Method-Override, só em POST', function (): void {
    $viaField = Request::create('/p/1', 'POST', ['_method' => 'put']);
    expect($viaField->method())->toBe(HttpMethod::PUT)->and($viaField->realMethod())->toBe(HttpMethod::POST);

    $viaHeader = Request::create('/p/1', 'POST', [], [], [], ['HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE']);
    expect($viaHeader->method())->toBe(HttpMethod::DELETE);

    $invalid = Request::create('/p/1', 'POST', ['_method' => 'GET']);
    expect($invalid->method())->toBe(HttpMethod::POST);

    $notPost = Request::create('/p/1', 'GET', ['_method' => 'DELETE']);
    expect($notPost->method())->toBe(HttpMethod::GET);
});

it('input() junta query e corpo; o corpo vence', function (): void {
    $request = new Request(['a' => 'query', 'q' => 'x'], ['a' => 'body', 'b' => 'y'], [], [], ['REQUEST_URI' => '/']);

    expect($request->input())->toBe(['a' => 'body', 'q' => 'x', 'b' => 'y'])
        ->and($request->input('a'))->toBe('body')
        ->and($request->input('missing', 'dflt'))->toBe('dflt')
        ->and($request->only('a', 'q'))->toBe(['a' => 'body', 'q' => 'x'])
        ->and($request->only(['b']))->toBe(['b' => 'y'])
        ->and($request->except('a'))->toBe(['q' => 'x', 'b' => 'y'])
        ->and($request->has('q'))->toBeTrue()
        ->and($request->has(['q', 'zzz']))->toBeFalse();
});

it('filled(), boolean(), integer() e string()', function (): void {
    $request = Request::create('/', 'POST', ['name' => 'Ana', 'empty' => '  ', 'flag' => 'true', 'off' => '0', 'n' => '12', 'x' => 'abc']);

    expect($request->filled('name'))->toBeTrue()
        ->and($request->filled('empty'))->toBeFalse()
        ->and($request->filled('nope'))->toBeFalse()
        ->and($request->boolean('flag'))->toBeTrue()
        ->and($request->boolean('off'))->toBeFalse()
        ->and($request->boolean('nope', true))->toBeTrue()
        ->and($request->integer('n'))->toBe(12)
        ->and($request->integer('x', 7))->toBe(7)
        ->and($request->string('name'))->toBe('Ana')
        ->and($request->string('nope', 'd'))->toBe('d');
});

it('json() descodifica o corpo e entra no input', function (): void {
    $request = Request::create(
        '/api/leads',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        '{"name":"Ana","tags":{"a":1}}',
    );

    expect($request->isJson())->toBeTrue()
        ->and($request->json('name'))->toBe('Ana')
        ->and($request->json('tags.a'))->toBe(1)
        ->and($request->json())->toBe(['name' => 'Ana', 'tags' => ['a' => 1]])
        ->and($request->input('name'))->toBe('Ana');
});

it('json inválido ou vazio devolve array vazio', function (): void {
    $bad = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{nope');
    $empty = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '');

    expect($bad->json())->toBe([])->and($empty->json('x', 'd'))->toBe('d');
});

it('expectsJson() por Accept, XHR ou path /api', function (): void {
    expect(Request::create('/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json'])->expectsJson())->toBeTrue()
        ->and(Request::create('/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/vnd.api+json'])->wantsJson())->toBeTrue()
        ->and(Request::create('/x', 'GET', [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'])->ajax())->toBeTrue()
        ->and(Request::create('/api/leads')->expectsJson())->toBeTrue()
        ->and(Request::create('/api')->expectsJson())->toBeTrue()
        ->and(Request::create('/apple')->expectsJson())->toBeFalse()
        ->and(Request::create('/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html'])->expectsJson())->toBeFalse();
});

it('headers são insensíveis a maiúsculas e há bearerToken', function (): void {
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Test/1.0',
        'HTTP_AUTHORIZATION' => 'Bearer abc123',
        'CONTENT_TYPE' => 'text/plain',
    ]);

    expect($request->header('user-agent'))->toBe('Test/1.0')
        ->and($request->header('User-Agent'))->toBe('Test/1.0')
        ->and($request->userAgent())->toBe('Test/1.0')
        ->and($request->header('Content-Type'))->toBe('text/plain')
        ->and($request->header('X-Nope', 'd'))->toBe('d')
        ->and($request->bearerToken())->toBe('abc123')
        ->and(Request::create('/')->bearerToken())->toBeNull();
});

it('scheme, host e isSecure', function (): void {
    $http = Request::create('http://example.com:8080/x');
    expect($http->host())->toBe('example.com:8080')
        ->and($http->scheme())->toBe('http')
        ->and($http->isSecure())->toBeFalse()
        ->and($http->root())->toBe('http://example.com:8080');

    $https = Request::create('https://example.com/x');
    expect($https->isSecure())->toBeTrue()->and($https->root())->toBe('https://example.com');
});

it('cookies e ip', function (): void {
    $request = Request::create('/', 'GET', [], ['theme' => 'dark']);

    expect($request->cookie('theme'))->toBe('dark')
        ->and($request->cookie('x', 'd'))->toBe('d')
        ->and($request->cookies())->toBe(['theme' => 'dark'])
        ->and($request->ip())->toBe('127.0.0.1');
});

it('ficheiros enviados são convertidos em UploadedFile', function (): void {
    $tmp = tempnam(sys_get_temp_dir(), 'slendie');
    file_put_contents($tmp, 'conteudo');

    $request = Request::create('/upload', 'POST', [], [], [
        'avatar' => ['name' => 'foto.PNG', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 8, 'test' => true],
        'vazio' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
        'docs' => [
            'name' => ['a.txt', 'b.txt'],
            'type' => ['text/plain', 'text/plain'],
            'tmp_name' => [$tmp, $tmp],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [8, 8],
            'test' => [true, true],
        ],
    ]);

    $file = $request->file('avatar');

    expect($file)->toBeInstanceOf(UploadedFile::class)
        ->and($file->clientName())->toBe('foto.PNG')
        ->and($file->extension())->toBe('png')
        ->and($file->size())->toBe(8)
        ->and($file->isValid())->toBeTrue()
        ->and($request->hasFile('avatar'))->toBeTrue()
        ->and($request->hasFile('vazio'))->toBeFalse()
        ->and($request->file('docs.1'))->toBeInstanceOf(UploadedFile::class)
        ->and($request->file('docs.1')->clientName())->toBe('b.txt');

    unlink($tmp);
});

it('UploadedFile::store() move para storage/app/{dir}', function (): void {
    Slendie\Tests\Fixtures\Http\HttpHelpers::app();

    $tmp = tempnam(sys_get_temp_dir(), 'slendie');
    file_put_contents($tmp, 'abc');

    $file = new UploadedFile($tmp, 'nota.txt', 'text/plain', 3, UPLOAD_ERR_OK, test: true);
    $stored = $file->store('docs', 'nota.txt');

    expect($stored)->toBe('docs/nota.txt')
        ->and(is_file(storage_path('app/docs/nota.txt')))->toBeTrue()
        ->and(file_get_contents(storage_path('app/docs/nota.txt')))->toBe('abc')
        ->and($file->path())->toBe(storage_path('app').DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'nota.txt');
});

it('atributos e resolver de utilizador', function (): void {
    $request = Request::create('/');
    $request->setAttribute('tenant', 'acme');

    expect($request->attribute('tenant'))->toBe('acme')
        ->and($request->attribute('x', 'd'))->toBe('d')
        ->and($request->user())->toBeNull();

    $request->setUserResolver(fn () => 'ana');

    expect($request->user())->toBe('ana');
});

it('session() lança se a sessão não foi iniciada', function (): void {
    $request = Request::create('/');

    expect($request->hasSession())->toBeFalse();
    $request->session();
})->throws(RuntimeException::class);

it('validate() fica para a peça 08', function (): void {
    Request::create('/')->validate(['a' => 'required']);
})->throws(RuntimeException::class);

it('transformInput aplica o callback a query, corpo e json', function (): void {
    $request = Request::create('/?q=%20x%20', 'POST', ['name' => ' Ana ', 'nested' => ['k' => ' v ']]);
    $request->transformInput(fn (string $key, mixed $value): mixed => is_string($value) ? trim($value) : $value);

    expect($request->query('q'))->toBe('x')
        ->and($request->post('name'))->toBe('Ana')
        ->and($request->post('nested.k'))->toBe('v');
});

it('missing() é o contrário de has()', function (): void {
    $request = Request::create('/', 'POST', ['a' => '1']);

    expect($request->missing('b'))->toBeTrue()
        ->and($request->missing('a'))->toBeFalse();
});
