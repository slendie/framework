<?php

declare(strict_types=1);

use Slendie\Http\Kernel;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Routing\Router;
use Slendie\Tests\Fixtures\Http\HttpHelpers as H;
use Slendie\Tests\Fixtures\Http\TagMiddleware;

beforeEach(function (): void {
    TagMiddleware::$log = [];
    $this->app = H::app();
    $this->router = H::router($this->app);
});

function sessionCookie(Response $response): ?string
{
    foreach ($response->cookies() as $cookie) {
        if ($cookie->name === 'slendie_session') {
            return $cookie->value;
        }
    }

    return null;
}

it('o Kernel passa o pedido pelo middleware global e devolve sempre Response', function (): void {
    $this->router->get('/', fn () => 'Slendie v4');

    $response = H::send($this->app, 'GET', '/');

    expect($response->getContent())->toBe('Slendie v4')
        ->and($response->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getHeader('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->getHeader('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->hasHeader('Permissions-Policy'))->toBeTrue();
});

it('404 e 405 passam pelo Handler e 405 traz Allow', function (): void {
    $this->router->get('/', fn () => 'home');

    $notFound = H::send($this->app, 'GET', '/x');
    $notAllowed = H::send($this->app, 'POST', '/');

    expect($notFound->getStatusCode())->toBe(404)
        ->and($notFound->getContent())->toContain('404')
        ->and($notAllowed->getStatusCode())->toBe(405)
        ->and($notAllowed->getHeader('Allow'))->toBe('GET, HEAD, OPTIONS');
});

it('SecurityHeaders é configurável e permite desactivar headers', function (): void {
    config(['app.security_headers' => ['X-Frame-Options' => 'DENY', 'Permissions-Policy' => '', 'X-Custom' => 'yes']]);
    $this->router->get('/', fn () => 'x');

    $response = H::send($this->app, 'GET', '/');

    expect($response->getHeader('X-Frame-Options'))->toBe('DENY')
        ->and($response->getHeader('X-Custom'))->toBe('yes')
        ->and($response->getHeader('X-Content-Type-Options'))->toBe('nosniff');
});

it('TrimStrings faz trim e converte vazios em null (menos passwords)', function (): void {
    $this->router->post('/echo', fn (Request $request) => $request->all());

    $response = H::send($this->app, 'POST', '/echo', [
        'name' => '  Ana  ',
        'empty' => '   ',
        'password' => ' segredo ',
        'nested' => ['k' => ' v '],
    ]);

    expect(json_decode($response->getContent(), true))->toBe([
        'name' => 'Ana',
        'empty' => null,
        'password' => ' segredo ',
        'nested' => ['k' => 'v'],
    ]);
});

it('TrimStrings respeita app.convert_empty_strings_to_null=false', function (): void {
    config(['app.convert_empty_strings_to_null' => false]);
    $this->router->post('/echo', fn (Request $request) => $request->all());

    $response = H::send($this->app, 'POST', '/echo', ['empty' => ' ']);

    expect(json_decode($response->getContent(), true))->toBe(['empty' => '']);
});

it('CSRF: POST sem token dá 419 e com token passa', function (): void {
    $this->router->middleware('web')->group(function (Router $router): void {
        $router->get('/form', fn () => csrf_field().'|'.csrf_token());
        $router->post('/form', fn () => 'enviado');
    });

    $form = H::send($this->app, 'GET', '/form');
    $session = sessionCookie($form);
    [$field, $token] = explode('|', $form->getContent());

    expect($session)->not->toBeNull()
        ->and($field)->toBe('<input type="hidden" name="_token" value="'.$token.'" autocomplete="off">');

    $without = H::send($this->app, 'POST', '/form', [], [], ['slendie_session' => $session]);
    $wrong = H::send($this->app, 'POST', '/form', ['_token' => 'errado'], [], ['slendie_session' => $session]);
    $withInput = H::send($this->app, 'POST', '/form', ['_token' => $token], [], ['slendie_session' => $session]);
    $withHeader = H::send($this->app, 'POST', '/form', [], ['HTTP_X_CSRF_TOKEN' => $token], ['slendie_session' => $session]);
    $withXsrf = H::send($this->app, 'POST', '/form', [], ['HTTP_X_XSRF_TOKEN' => $token], ['slendie_session' => $session]);

    expect($without->getStatusCode())->toBe(419)
        ->and($wrong->getStatusCode())->toBe(419)
        ->and($withInput->getContent())->toBe('enviado')
        ->and($withHeader->getContent())->toBe('enviado')
        ->and($withXsrf->getContent())->toBe('enviado');
});

it('CSRF: sem cookie de sessão o token nunca coincide', function (): void {
    $this->router->middleware('web')->group(function (Router $router): void {
        $router->post('/form', fn () => 'enviado');
    });

    expect(H::send($this->app, 'POST', '/form', ['_token' => 'qualquer'])->getStatusCode())->toBe(419);
});

it('CSRF: caminhos em $except estão isentos', function (): void {
    $this->router->middleware(Slendie\Tests\Fixtures\Http\ExemptCsrf::class)->group(function (Router $router): void {
        $router->post('/webhooks/stripe', fn () => 'ok');
        $router->post('/other', fn () => 'no');
    });

    // Sem StartSession não há sessão: o caminho isento passa, o resto falha com 419.
    expect(H::send($this->app, 'POST', '/webhooks/stripe')->getContent())->toBe('ok')
        ->and(H::send($this->app, 'POST', '/other')->getStatusCode())->toBe(419);
});

it('o método de formulário pode ser falsificado com _method (com CSRF)', function (): void {
    $this->router->middleware('web')->group(function (Router $router): void {
        $router->get('/f', fn () => csrf_token());
        $router->delete('/f', fn () => 'apagado');
    });

    $get = H::send($this->app, 'GET', '/f');
    $cookie = sessionCookie($get);

    $response = H::send($this->app, 'POST', '/f', ['_method' => 'DELETE', '_token' => $get->getContent()], [], ['slendie_session' => $cookie]);

    expect($response->getContent())->toBe('apagado');
});

it('a sessão persiste entre pedidos e o flash dura um pedido', function (): void {
    $this->router->middleware('web')->group(function (Router $router): void {
        $router->get('/set', fn () => redirect('/read')->with('status', 'Gravado'));
        $router->get('/read', fn () => (string) session('status', 'vazio'));
    });

    $first = H::send($this->app, 'GET', '/set');
    $cookie = ['slendie_session' => sessionCookie($first)];

    expect($first->getStatusCode())->toBe(302)
        ->and($first->getHeader('Location'))->toBe('http://localhost/read');

    $second = H::send($this->app, 'GET', '/read', [], [], $cookie);
    $third = H::send($this->app, 'GET', '/read', [], [], $cookie);

    expect($second->getContent())->toBe('Gravado')
        ->and($third->getContent())->toBe('vazio');
});

it('withInput/old() e back() usam a sessão', function (): void {
    $this->router->middleware('web')->group(function (Router $router): void {
        $router->get('/form', fn () => 'valor:'.old('name', 'nenhum'));
        $router->get('/token', fn () => csrf_token());
        $router->post('/form', fn (Request $request) => back()->withInput()->withErrors(['name' => ['Inválido']]));
    });

    $form = H::send($this->app, 'GET', '/form', [], ['HTTP_HOST' => 'localhost']);
    $cookie = ['slendie_session' => sessionCookie($form)];
    $token = H::send($this->app, 'GET', '/token', [], [], $cookie)->getContent();

    $post = H::send($this->app, 'POST', '/form', ['_token' => $token, 'name' => 'Ana', 'password' => 'x'], ['HTTP_REFERER' => 'http://localhost/form'], $cookie);
    $again = H::send($this->app, 'GET', '/form', [], [], $cookie);

    expect($post->getStatusCode())->toBe(302)
        ->and($post->getHeader('Location'))->toBe('http://localhost/form')
        ->and($again->getContent())->toBe('valor:Ana');
});

it('ShareErrorsFromSession disponibiliza os erros aos views e ao request', function (): void {
    $this->app->instance('view', new Slendie\Tests\Fixtures\Http\FakeRenderable());
    Slendie\Tests\Fixtures\Http\FakeRenderable::$shared = [];

    $this->router->middleware('web')->group(function (Router $router): void {
        $router->get('/e', fn (Request $request) => json_encode($request->attribute('errors')));
    });

    $response = H::send($this->app, 'GET', '/e');

    expect($response->getContent())->toBe('[]')
        ->and(Slendie\Tests\Fixtures\Http\FakeRenderable::$shared)->toHaveKey('errors');
});

it('CORS: preflight OPTIONS responde 204 com os headers certos', function (): void {
    $this->router->middleware('api')->group(function (Router $router): void {
        $router->post('/api/leads', fn () => 'lead');
    });

    $preflight = H::send($this->app, 'OPTIONS', '/api/leads', [], [
        'HTTP_ORIGIN' => 'https://site.test',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-token',
    ]);

    expect($preflight->getStatusCode())->toBe(204)
        ->and($preflight->getHeader('Access-Control-Allow-Origin'))->toBe('*')
        ->and($preflight->getHeader('Access-Control-Allow-Methods'))->toContain('POST')
        ->and($preflight->getHeader('Access-Control-Allow-Headers'))->toBe('content-type,x-token');

    $actual = H::send($this->app, 'POST', '/api/leads', [], ['HTTP_ORIGIN' => 'https://site.test']);

    expect($actual->getContent())->toBe('lead')
        ->and($actual->getHeader('Access-Control-Allow-Origin'))->toBe('*');
});

it('CORS respeita allowed_origins, credenciais e exposed_headers', function (): void {
    config(['cors' => [
        'paths' => ['api/*'],
        'allowed_origins' => ['https://ok.test', 'https://*.dev.test'],
        'allowed_methods' => ['GET', 'POST'],
        'allowed_headers' => ['X-A'],
        'exposed_headers' => ['X-Total'],
        'max_age' => 600,
        'supports_credentials' => true,
    ]]);

    $this->router->middleware('api')->group(function (Router $router): void {
        $router->get('/api/x', fn () => 'x');
    });

    $allowed = H::send($this->app, 'GET', '/api/x', [], ['HTTP_ORIGIN' => 'https://ok.test']);
    $wildcard = H::send($this->app, 'GET', '/api/x', [], ['HTTP_ORIGIN' => 'https://a.dev.test']);
    $denied = H::send($this->app, 'GET', '/api/x', [], ['HTTP_ORIGIN' => 'https://evil.test']);
    $preflight = H::send($this->app, 'OPTIONS', '/api/x', [], ['HTTP_ORIGIN' => 'https://ok.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET']);

    expect($allowed->getHeader('Access-Control-Allow-Origin'))->toBe('https://ok.test')
        ->and($allowed->getHeader('Access-Control-Allow-Credentials'))->toBe('true')
        ->and($allowed->getHeader('Access-Control-Expose-Headers'))->toBe('X-Total')
        ->and($wildcard->getHeader('Access-Control-Allow-Origin'))->toBe('https://a.dev.test')
        ->and($denied->hasHeader('Access-Control-Allow-Origin'))->toBeFalse()
        ->and($preflight->getHeader('Access-Control-Allow-Methods'))->toBe('GET, POST')
        ->and($preflight->getHeader('Access-Control-Allow-Headers'))->toBe('X-A')
        ->and($preflight->getHeader('Access-Control-Max-Age'))->toBe('600');
});

it('rotas de api/routes são carregadas por RoutingServiceProvider com prefix e grupo', function (): void {
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-routes-'.bin2hex(random_bytes(4));
    mkdir($base.'/routes', 0777, true);
    file_put_contents($base.'/routes/web.php', '<?php use Slendie\Support\Facades\Route; Route::get("/", fn () => "web ok");');
    file_put_contents($base.'/routes/api.php', '<?php use Slendie\Support\Facades\Route; Route::get("/ping", fn () => ["pong" => true]);');

    $app = new Slendie\Foundation\Application($base);
    $app->useConfigPath(fixturePath('http/config'));
    $app->useStoragePath($base.'/storage');
    $app->singleton('session.handler', fn () => new Slendie\Session\ArraySessionHandler());

    $web = $app->handle(Request::create('/'));
    $api = $app->handle(Request::create('/api/ping'));

    expect($web->getContent())->toBe('web ok')
        ->and($web->cookies())->not->toBeEmpty()
        ->and($api->getContent())->toBe('{"pong":true}')
        ->and($api->cookies())->toBeEmpty();

    Slendie\Foundation\Bootstrap\HandleExceptions::flush();
});

it('exceptions não apanhadas tornam-se 500 (debug mostra a classe)', function (): void {
    $this->router->get('/boom', fn () => throw new RuntimeException('rebentou'));

    $response = H::send($this->app, 'GET', '/boom');

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getContent())->toContain('RuntimeException')
        ->and($response->getContent())->toContain('rebentou');
});

it('terminate() chama terminate nos middlewares e os callbacks da app', function (): void {
    $terminated = [];
    $this->app->terminating(function () use (&$terminated): void {
        $terminated[] = 'app';
    });

    Slendie\Tests\Fixtures\Http\TerminatingMiddleware::$calls = 0;

    $this->router->get('/t', fn () => 'ok')->middleware(Slendie\Tests\Fixtures\Http\TerminatingMiddleware::class);

    $request = Request::create('/t');
    $response = $this->app->handle($request);
    $this->app->terminate($request, $response);

    expect(Slendie\Tests\Fixtures\Http\TerminatingMiddleware::$calls)->toBe(1)->and($terminated)->toBe(['app']);
});

it('o Kernel permite acrescentar middleware global e de grupo', function (): void {
    $kernel = $this->app->make(Kernel::class);
    $this->router->aliasMiddleware('tag', TagMiddleware::class);

    $kernel->pushMiddleware(TagMiddleware::class);
    $kernel->appendMiddlewareToGroup('web', TagMiddleware::class);
    $kernel->prependMiddlewareToGroup('api', TagMiddleware::class);
    $kernel->aliasMiddleware('t', TagMiddleware::class);

    expect($kernel->getGlobalMiddleware())->toContain(TagMiddleware::class)
        ->and(end($kernel->getMiddlewareGroups()['web']))->toBe(TagMiddleware::class)
        ->and($kernel->getMiddlewareGroups()['api'][0])->toBe(TagMiddleware::class)
        ->and($this->router->getMiddleware()['t'])->toBe(TagMiddleware::class);
});

it('withMiddleware/withExceptions do bootstrap fluente configuram Kernel e Handler', function (): void {
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-boot-'.bin2hex(random_bytes(4));
    mkdir($base, 0777, true);

    $app = Slendie\Foundation\Application::configure($base)
        ->withMiddleware(function (Kernel $kernel): void {
            $kernel->pushMiddleware(TagMiddleware::class);
        })
        ->withExceptions(function (Slendie\Foundation\Exceptions\Handler $handler): void {
            $handler->dontReport([DomainException::class]);
        })
        ->create();

    $app->useConfigPath(fixturePath('http/config'));
    $app->singleton('session.handler', fn () => new Slendie\Session\ArraySessionHandler());

    $app->handle(Request::create('/qualquer'));

    expect($app->make(Kernel::class)->getGlobalMiddleware())->toContain(TagMiddleware::class)
        ->and($app->make(Slendie\Foundation\Exceptions\Handler::class)->shouldReport(new DomainException()))->toBeFalse()
        ->and($app->make(Slendie\Foundation\Exceptions\Handler::class)->shouldReport(new RuntimeException()))->toBeTrue();

    Slendie\Foundation\Bootstrap\HandleExceptions::flush();
});
