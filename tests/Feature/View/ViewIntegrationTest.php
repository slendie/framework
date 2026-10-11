<?php

declare(strict_types=1);

use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Http\ViewResponse;
use Slendie\Routing\Router;
use Slendie\Support\Facades\Blade;
use Slendie\Support\Facades\View;
use Slendie\Tests\Fixtures\Http\HttpHelpers as H;
use Slendie\Tests\Fixtures\View\ViewHelpers as V;

beforeEach(function (): void {
    $this->dir = V::tempDir();
    $this->app = H::app();

    config(['view.paths' => [$this->dir.'/views'], 'view.compiled' => $this->dir.'/cache']);

    $this->router = H::router($this->app);
});

function sessionCookieOf(Response $response): array
{
    foreach ($response->cookies() as $cookie) {
        if ($cookie->name === 'slendie_session') {
            return ['slendie_session' => $cookie->value];
        }
    }

    return [];
}

it('o ViewServiceProvider regista view, blade.compiler e os serviços de suporte', function (): void {
    expect($this->app->make('view'))->toBeInstanceOf(Slendie\View\Factory::class)
        ->and($this->app->make('view'))->toBe($this->app->make(Slendie\View\Factory::class))
        ->and($this->app->make('blade.compiler'))->toBe($this->app->make(Slendie\View\Compiler\BladeCompiler::class))
        ->and($this->app->make(Slendie\View\Finder::class)->getPaths())->toBe([$this->dir.'/views'])
        ->and($this->app->make('blade.compiler')->getCachePath())->toBe($this->dir.'/cache');
});

it('por omissão usa resources/views e storage/framework/views', function (): void {
    $app = new Slendie\Foundation\Application(fixturePath('http'));
    $app->useStoragePath('/tmp/storage-x');
    $app->bootstrapWith([Slendie\Foundation\Bootstrap\LoadEnvironment::class, Slendie\Foundation\Bootstrap\RegisterProviders::class]);

    expect($app->make(Slendie\View\Finder::class)->getPaths())->toBe([$app->resourcePath('views')])
        ->and($app->make('blade.compiler')->getCachePath())->toBe($app->storagePath('framework/views'));
});

it('Route::view renderiza um layout com secções através do Kernel', function (): void {
    V::write($this->dir, 'layouts.app', "<title>@yield('title', 'App')</title>\n<main>\n@yield('content')\n</main>");
    V::write($this->dir, 'home', "@extends('layouts.app')\n\n@section('title', \$title)\n\n@section('content')\n<h1>{{ \$title }} v4</h1>\n@endsection\n");

    $this->router->view('/', 'home', ['title' => 'Slendie']);

    $response = H::send($this->app, 'GET', '/');

    expect($response)->toBeInstanceOf(ViewResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->getHeader('Content-Type'))->toBe('text/html; charset=UTF-8')
        ->and(Slendie\Support\Str::squish($response->getContent()))->toBe('<title>Slendie</title> <main> <h1>Slendie v4</h1> </main>');
});

it('view() devolve a Factory sem argumentos e uma View com nome', function (): void {
    V::write($this->dir, 'hi', 'Olá {{ $n }}');

    expect(view())->toBeInstanceOf(Slendie\View\Factory::class)
        ->and(view('hi', ['n' => 'Ana']))->toBeInstanceOf(Slendie\View\View::class)
        ->and(view('hi', ['n' => 'Ana'])->render())->toBe('Olá Ana')
        ->and(view()->exists('hi'))->toBeTrue();
});

it('um controller/closure pode devolver view() e o Router converte em ViewResponse lazy', function (): void {
    V::write($this->dir, 'page', '<p>{{ $x }}</p>');

    $this->router->get('/p', fn () => view('page', ['x' => '<b>']));

    $response = H::send($this->app, 'GET', '/p');

    expect($response)->toBeInstanceOf(ViewResponse::class)
        ->and($response->getContent())->toBe('<p>&lt;b&gt;</p>');
});

it('response()->view() e as facades View/Blade', function (): void {
    V::write($this->dir, 'r', 'R:{{ $v }}@shout("x")');
    Blade::directive('shout', fn (string $e): string => '<?php echo strtoupper('.$e.'); ?>');

    $response = response()->view('r', ['v' => 1], 201, ['X-Test' => 'a']);

    expect($response)->toBeInstanceOf(ViewResponse::class)
        ->and($response->getStatusCode())->toBe(201)
        ->and($response->getHeader('X-Test'))->toBe('a')
        ->and($response->getContent())->toBe('R:1X')
        ->and(View::exists('r'))->toBeTrue()
        ->and(View::render('r', ['v' => 2]))->toBe('R:2X');

    View::share('compartilhado', 'sim');
    expect(view()->getShared())->toHaveKey('compartilhado');
});

it('@csrf, @method e old() integram com a sessão (formulário completo)', function (): void {
    V::write($this->dir, 'form', <<<'BLADE'
<form method="POST" action="/form">
    @csrf
    @method('PUT')
    <input name="name" value="{{ old('name') }}" @required(true)>
    @error('name')
        <span class="error">{{ $message }}</span>
    @enderror
    @if ($errors->any())
        <ul>@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
    @endif
</form>
BLADE);

    $this->router->middleware('web')->group(function (Router $router): void {
        $router->get('/form', fn () => view('form'));
        $router->get('/token', fn () => csrf_token());
        $router->put('/form', fn () => back()->withInput()->withErrors(['name' => ['Nome inválido']]));
    });

    $first = H::send($this->app, 'GET', '/form');
    $cookie = sessionCookieOf($first);
    $token = H::send($this->app, 'GET', '/token', [], [], $cookie)->getContent();

    expect($first->getContent())->toContain('<input type="hidden" name="_token" value="')
        ->toContain('<input type="hidden" name="_method" value="PUT">')
        ->not->toContain('class="error"');

    $post = H::send($this->app, 'POST', '/form', ['_method' => 'PUT', '_token' => $token, 'name' => 'Ana'], ['HTTP_REFERER' => 'http://localhost/form'], $cookie);
    $again = H::send($this->app, 'GET', '/form', [], [], $cookie);

    expect($post->getStatusCode())->toBe(302)
        ->and($again->getContent())->toContain('value="Ana" required')
        ->toContain('<span class="error">Nome inválido</span>')
        ->toContain('<li>Nome inválido</li>');

    // o flash dura um pedido
    $third = H::send($this->app, 'GET', '/form', [], [], $cookie);
    expect($third->getContent())->not->toContain('Nome inválido');
});

it('uma view inexistente numa rota resulta em 500 (e em debug mostra o erro)', function (): void {
    $this->router->get('/x', fn () => view('nao.existe'));

    $response = H::send($this->app, 'GET', '/x');

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getContent())->toContain('ViewNotFoundException');
});

it('abort(404) dentro de uma view devolve a página 404', function (): void {
    V::write($this->dir, 'gone', "@php abort(404, 'Sem lead'); @endphp");
    $this->router->get('/gone', fn () => view('gone'));

    $response = H::send($this->app, 'GET', '/gone');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toContain('Página não encontrada');
});

it('o projecto pode sobrepor as páginas de erro e o layout da framework', function (): void {
    // as páginas de erro vivem em resources/views/errors do projecto, não em view.paths
    $resources = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-err-'.bin2hex(random_bytes(4));
    mkdir($resources.'/resources/views/errors', 0777, true);
    file_put_contents($resources.'/resources/views/errors/404.blade.php', "@extends('errors::layout')\n@section('message', 'Nada aqui')");
    file_put_contents($resources.'/resources/views/errors/layout.blade.php', 'LAYOUT[@yield("message")]');

    $custom = new Slendie\Foundation\Application($resources);
    $custom->useStoragePath($this->dir.'/storage');
    $custom->bootstrapWith([Slendie\Foundation\Bootstrap\LoadEnvironment::class, Slendie\Foundation\Bootstrap\RegisterProviders::class]);

    $response = $custom->make(Slendie\Foundation\Exceptions\Handler::class)
        ->render(Request::create('/x'), new Slendie\Exceptions\NotFoundHttpException());

    expect($response->getContent())->toContain('LAYOUT[Nada aqui]');
});

it('a página de debug é uma view Blade com excerto, trace, pedido e ambiente sanitizado', function (): void {
    putenv('API_SECRET=muito-secreto');
    $_ENV['API_SECRET'] = 'muito-secreto';

    $handler = $this->app->make(Slendie\Foundation\Exceptions\Handler::class);
    $response = $handler->render(Request::create('/boom?a=1'), H::exception('algo correu mal'));

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getContent())->toStartWith('<!doctype html>')
        ->toContain('RuntimeException')->toContain('algo correu mal')->toContain('Stack trace')
        ->toContain('Pedido')->toContain('Ambiente (sanitizado)')
        ->not->toContain('muito-secreto');

    putenv('API_SECRET');
    unset($_ENV['API_SECRET']);
});

it('as páginas de erro da framework estão disponíveis em errors::', function (): void {
    $factory = $this->app->make('view');

    foreach (['403', '404', '419', '500', 'generic', 'debug', 'layout'] as $name) {
        expect($factory->exists('errors::'.$name))->toBeTrue("errors::{$name}");
    }
});
