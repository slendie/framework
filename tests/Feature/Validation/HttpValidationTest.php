<?php

declare(strict_types=1);

use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Routing\Router;
use Slendie\Tests\Fixtures\Http\HttpHelpers as H;
use Slendie\Tests\Fixtures\Validation\DeniedRequest;
use Slendie\Tests\Fixtures\Validation\PreparedRequest;
use Slendie\Tests\Fixtures\Validation\StoreLeadRequest;
use Slendie\Validation\ValidationException;

function cookieOf(Response $response): ?string
{
    foreach ($response->cookies() as $cookie) {
        if ($cookie->name === 'slendie_session') {
            return $cookie->value;
        }
    }

    return null;
}

beforeEach(function (): void {
    StoreLeadRequest::$log = [];
    $this->app = H::app();
    $this->router = H::router($this->app);

    $this->router->middleware('web')->group(function (Router $router): void {
        $router->get('/contact', fn () => view('contact'));
        $router->get('/token', fn () => csrf_token());
        $router->post('/contact', function (Request $request) {
            $data = $request->validate([
                'name' => 'required|string|max:10',
                'email' => 'required|email',
                'password' => 'nullable|min:8|confirmed',
            ]);

            return response()->json($data);
        });
        $router->post('/bag', fn (Request $request) => $request->validate(['name' => 'required'], [], []) ?: 'x');
        $router->post('/leads', fn (StoreLeadRequest $request) => response()->json($request->validated()));
        $router->post('/denied', fn (DeniedRequest $request) => 'nunca');
        $router->post('/prepared', fn (PreparedRequest $request) => response()->json($request->validated()));
    });

    // Abre a sessão (cookie + token CSRF) como um browser.
    $first = H::send($this->app, 'GET', '/token');
    $this->cookie = ['slendie_session' => cookieOf($first)];
    $this->token = H::send($this->app, 'GET', '/token', [], [], $this->cookie)->getContent();

    $this->post = fn (string $uri, array $data = [], array $server = [], ?array $cookie = null): Response => H::send(
        $this->app,
        'POST',
        $uri,
        ['_token' => $this->token, ...$data],
        ['HTTP_REFERER' => 'http://localhost/contact', ...$server],
        $cookie ?? $this->cookie,
    );
});

it('POST inválido numa rota web: 302 de volta, com erros e input antigo em sessão', function (): void {
    $response = ($this->post)('/contact', ['name' => '', 'email' => 'nao-e-email', 'password' => 'abc']);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toBe('http://localhost/contact');

    $session = $this->app->make('session.handler')->read($this->cookie['slendie_session'] ?? '');

    expect($session)->toContain('errors')->toContain('_old_input')->toContain('nao-e-email');
});

it('o GET seguinte mostra @error, $errors e old()', function (): void {
    ($this->post)('/contact', ['name' => '', 'email' => 'nao-e-email', 'message' => 'oi']);

    $page = H::send($this->app, 'GET', '/contact', [], [], $this->cookie)->getContent();

    expect($page)
        ->toContain('<p class="error-name">O campo nome é obrigatório.</p>')
        ->toContain('<p class="error-email">O campo e-mail tem de ser um endereço de e-mail válido.</p>')
        ->toContain('<li>O campo nome é obrigatório.</li>')
        ->toContain('<input name="email" value="nao-e-email">')
        ->toContain('<span class="old-message">oi</span>');

    // O flash só vive um pedido.
    $again = H::send($this->app, 'GET', '/contact', [], [], $this->cookie)->getContent();

    expect($again)->not->toContain('error-name')->toContain('<span class="old-message">sem-valor</span>');
});

it('passwords nunca voltam em old()', function (): void {
    ($this->post)('/contact', ['name' => '', 'email' => 'a@b.pt', 'password' => 'segredo123', 'password_confirmation' => 'x']);

    $handler = $this->app->make('session.handler');
    $cookie = $this->cookie['slendie_session'];

    expect($handler->read($cookie ?? ''))->not->toContain('segredo123');
});

it('POST válido devolve só os campos validados', function (): void {
    $response = ($this->post)('/contact', ['name' => 'Ana', 'email' => 'ana@x.pt', 'extra' => 'ignorado']);

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true))->toBe(['name' => 'Ana', 'email' => 'ana@x.pt']);
});

it('Accept: application/json → 422 com o formato exacto', function (): void {
    $response = ($this->post)('/contact', ['name' => '', 'email' => 'x'], ['HTTP_ACCEPT' => 'application/json']);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getHeader('Content-Type'))->toContain('application/json')
        ->and(json_decode($response->getContent(), true))->toBe([
            'message' => 'Os dados fornecidos são inválidos.',
            'errors' => [
                'name' => ['O campo nome é obrigatório.'],
                'email' => ['O campo e-mail tem de ser um endereço de e-mail válido.'],
            ],
        ]);
});

it('corpo JSON também é validado e rotas /api respondem sempre 422 JSON', function (): void {
    $this->router->post('/api/leads', fn (Request $request) => $request->validate(['email' => 'required|email']));

    $request = Request::create('/api/leads', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"email":"x"}');
    $response = $this->app->handle($request);

    expect($response->getStatusCode())->toBe(422)
        ->and(json_decode($response->getContent(), true)['errors'])->toHaveKey('email');

    $ok = $this->app->handle(Request::create('/api/leads', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"email":"a@b.pt"}'));
    expect($ok->getStatusCode())->toBe(200);
});

it('FormRequest: injectado, validado antes do controller, com mensagens e atributos próprios', function (): void {
    $invalid = ($this->post)('/leads', ['name' => '', 'email' => 'x'], ['HTTP_ACCEPT' => 'application/json']);

    expect($invalid->getStatusCode())->toBe(422)
        ->and(json_decode($invalid->getContent(), true)['errors'])->toBe([
            'name' => ['Diz-nos o teu nome.'],
            'email' => ['O campo endereço de e-mail tem de ser um endereço de e-mail válido.'],
        ])
        ->and(StoreLeadRequest::$log)->toBe(['after']);

    $valid = ($this->post)('/leads', ['name' => 'Ana', 'email' => 'ana@x.pt', 'nada' => 1]);

    expect($valid->getStatusCode())->toBe(200)
        ->and(json_decode($valid->getContent(), true))->toBe(['name' => 'Ana', 'email' => 'ana@x.pt'])
        ->and(StoreLeadRequest::$log)->toBe(['after', 'after', 'passed']);
});

it('FormRequest inválido num pedido web redirecciona de volta com os erros', function (): void {
    $response = ($this->post)('/leads', ['name' => '', 'email' => 'ana@x.pt']);

    expect($response->getStatusCode())->toBe(302);

    $page = H::send($this->app, 'GET', '/contact', [], [], $this->cookie)->getContent();
    expect($page)->toContain('Diz-nos o teu nome.')->toContain('<input name="email" value="ana@x.pt">');
});

it('FormRequest::authorize() false → 403', function (): void {
    $response = ($this->post)('/denied', ['name' => 'x'], ['HTTP_ACCEPT' => 'application/json']);

    expect($response->getStatusCode())->toBe(403)
        ->and(json_decode($response->getContent(), true))->toBe(['message' => 'This action is unauthorized.']);
});

it('FormRequest::prepareForValidation() altera o input antes de validar', function (): void {
    $response = ($this->post)('/prepared', ['name' => 'Minha Pagina']);

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true))->toBe(['name' => 'Minha Pagina', 'slug' => 'minha-pagina']);
});

it('FormRequest herda input, headers, sessão e rota do pedido original', function (): void {
    $this->router->middleware('web')->post('/inspect/{id}', function (StoreLeadRequest $request, string $id) {
        return response()->json([
            'id' => $id,
            'route' => $request->route('id'),
            'header' => $request->header('X-Test'),
            'session' => $request->hasSession(),
            'path' => $request->path(),
            'safe' => $request->safe()->only('name'),
            'name' => $request->validated('name'),
            'missing' => $request->validated('nada', 'dflt'),
        ]);
    });

    $response = ($this->post)('/inspect/7', ['name' => 'Ana', 'email' => 'a@b.pt'], ['HTTP_X_TEST' => 'sim']);

    expect(json_decode($response->getContent(), true))->toBe([
        'id' => '7', 'route' => '7', 'header' => 'sim', 'session' => true, 'path' => '/inspect/7',
        'safe' => ['name' => 'Ana'], 'name' => 'Ana', 'missing' => 'dflt',
    ]);
});

it('ValidationException::redirectTo e errorBag personalizados', function (): void {
    $this->router->middleware('web')->post('/custom', function () {
        throw ValidationException::withMessages(['x' => 'Falhou.'])->redirectTo('/outro')->errorBag('login');
    });

    $response = ($this->post)('/custom');
    $session = $this->app->make('session.handler')->read($this->cookie['slendie_session'] ?? '');

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toBe('http://localhost/outro')
        ->and($session)->toContain('login')->toContain('Falhou.');
});

it('ValidationException não vai para o log', function (): void {
    $handler = $this->app->make(Slendie\Foundation\Exceptions\Handler::class);

    expect($handler->shouldReport(ValidationException::withMessages(['a' => 'b'])))->toBeFalse();
});

it('sem sessão e sem Accept JSON, a falha responde 422 JSON em vez de rebentar', function (): void {
    $this->router->post('/nosession', fn (Request $request) => $request->validate(['a' => 'required']));

    $response = $this->app->handle(Request::create('/nosession', 'POST'));

    expect($response->getStatusCode())->toBe(422);
});
