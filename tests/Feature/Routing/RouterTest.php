<?php

declare(strict_types=1);

use Slendie\Exceptions\UrlGenerationException;
use Slendie\Http\JsonResponse;
use Slendie\Http\RedirectResponse;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Http\ViewResponse;
use Slendie\Routing\Router;
use Slendie\Routing\UrlGenerator;
use Slendie\Support\Facades\Route;
use Slendie\Tests\Fixtures\Http\Article;
use Slendie\Tests\Fixtures\Http\FakeRenderable;
use Slendie\Tests\Fixtures\Http\Greeting;
use Slendie\Tests\Fixtures\Http\HttpHelpers as H;
use Slendie\Tests\Fixtures\Http\InvokableController;
use Slendie\Tests\Fixtures\Http\PostController;
use Slendie\Tests\Fixtures\Http\TagMiddleware;

beforeEach(function (): void {
    TagMiddleware::$log = [];
    FakeRenderable::$renders = 0;
    $this->app = H::app();
    $this->router = H::router($this->app);
    $this->router->aliasMiddleware('tag', TagMiddleware::class);
});

function dispatch(string $uri, string $method = 'GET', array $params = []): Response
{
    return app(Router::class)->dispatch(Request::create($uri, $method, $params));
}

it('despacha uma closure e devolve Response', function (): void {
    $this->router->get('/', fn () => 'Slendie v4');

    $response = dispatch('/');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getContent())->toBe('Slendie v4')
        ->and($response->getStatusCode())->toBe(200);
});

it('injecta parâmetros de rota na closure, com coerção de tipo', function (): void {
    $this->router->get('/posts/{id}/{slug?}', fn (int $id, ?string $slug = null) => $id.':'.get_debug_type($id).':'.($slug ?? 'sem'));

    expect(dispatch('/posts/5')->getContent())->toBe('5:int:sem')
        ->and(dispatch('/posts/5/ola')->getContent())->toBe('5:int:ola');
});

it('injecta o Request e dependências por tipo na closure', function (): void {
    $this->router->get('/who', fn (Request $request, Greeting $greeting) => $request->path().'|'.$greeting->text);

    expect(dispatch('/who')->getContent())->toBe('/who|hello');
});

it('controller [Classe, método] com DI no construtor e parâmetro de rota', function (): void {
    $this->router->get('/p', [PostController::class, 'index']);
    $this->router->get('/p/{post}', [PostController::class, 'show'])->whereNumber('post');

    expect(dispatch('/p')->getContent())->toBe('index:hello')
        ->and(dispatch('/p/9')->getContent())->toBe('show:9:int:/p/9');
});

it('suporta Classe@método e controllers invocáveis', function (): void {
    $this->router->get('/a', PostController::class.'@create');
    $this->router->get('/b', InvokableController::class);

    expect(dispatch('/a')->getContent())->toBe('create')
        ->and(dispatch('/b')->getContent())->toBe('invoked');
});

it('resource() gera 7 rotas nomeadas e despacha cada uma', function (): void {
    $routes = $this->router->resource('posts', PostController::class);

    $names = array_map(fn ($r) => $r->getName(), $routes);

    expect($names)->toBe(['posts.index', 'posts.create', 'posts.store', 'posts.show', 'posts.edit', 'posts.update', 'posts.destroy'])
        ->and(dispatch('/posts')->getContent())->toBe('index:hello')
        ->and(dispatch('/posts/create')->getContent())->toBe('create')
        ->and(dispatch('/posts', 'POST', ['title' => 'T'])->getContent())->toBe('{"stored":"T"}')
        ->and(dispatch('/posts/3/edit')->getContent())->toBe('edit:3')
        ->and(dispatch('/posts/3', 'PUT')->getContent())->toBe('update:3')
        ->and(dispatch('/posts/3', 'PATCH')->getContent())->toBe('update:3')
        ->and(dispatch('/posts/3', 'DELETE')->getContent())->toBe('destroy:3');
});

it('resource() respeita only/except e apiResource() não tem create/edit', function (): void {
    $only = $this->router->resource('tags', PostController::class, ['only' => ['index', 'show']]);
    $except = $this->router->resource('pages', PostController::class, ['except' => ['destroy']]);
    $api = $this->router->apiResource('items', PostController::class);

    expect(array_map(fn ($r) => $r->getName(), $only))->toBe(['tags.index', 'tags.show'])
        ->and($except)->toHaveCount(6)
        ->and(array_map(fn ($r) => $r->getName(), $api))->toBe(['items.index', 'items.store', 'items.show', 'items.update', 'items.destroy']);
});

it('resource() aninhado usa o parâmetro singular do pai', function (): void {
    $routes = $this->router->resource('posts.comments', PostController::class, ['only' => ['show']]);

    expect($routes[0]->uri())->toBe('/posts/{post}/comments/{comment}')
        ->and($routes[0]->getName())->toBe('posts.comments.show');
});

it('grupos acumulam prefix, name e middleware', function (): void {
    $this->router->group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => ['tag:outer']], function (Router $router): void {
        $router->get('/dashboard', fn () => 'dash')->name('dashboard');

        $router->group(['prefix' => 'users', 'as' => 'users.', 'middleware' => ['tag:inner']], function (Router $router): void {
            $router->get('/', fn () => 'users')->name('index');
        });
    });

    $dash = dispatch('/admin/dashboard');
    $users = dispatch('/admin/users');

    expect($dash->getContent())->toBe('dash')
        ->and($users->getContent())->toBe('users')
        ->and($this->router->getRoutes()->getByName('admin.dashboard'))->not->toBeNull()
        ->and($this->router->getRoutes()->getByName('admin.users.index')?->uri())->toBe('/admin/users')
        ->and($this->router->getRoutes()->getByName('admin.users.index')?->middleware())->toBe(['tag:outer', 'tag:inner'])
        ->and(TagMiddleware::$log)->toBe(['before:outer', 'after:outer', 'before:outer', 'before:inner', 'after:inner', 'after:outer']);
});

it('API fluente prefix()->middleware()->name()->group()', function (): void {
    $this->router->prefix('api/v1')->name('v1.')->middleware('tag:x')->group(function (Router $router): void {
        $router->get('/ping', fn () => 'pong')->name('ping');
    });

    expect(dispatch('/api/v1/ping')->getContent())->toBe('pong')
        ->and($this->router->has('v1.ping'))->toBeTrue();
});

it('Route::prefix()->get() regista a rota dentro do grupo', function (): void {
    $route = $this->router->prefix('x')->get('/y', fn () => 'xy');

    expect($route->uri())->toBe('/x/y');
});

it('controller() do grupo aceita acções só com o nome do método', function (): void {
    $this->router->controller(PostController::class)->group(function (Router $router): void {
        $router->get('/ctl', 'index');
        $router->get('/ctl/new', 'create');
    });

    expect(dispatch('/ctl')->getContent())->toBe('index:hello')
        ->and(dispatch('/ctl/new')->getContent())->toBe('create');
});

it('group() com ficheiro carrega as rotas com a facade Route', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'routes');
    file_put_contents($file, '<?php use Slendie\Support\Facades\Route; Route::get("/from-file", fn () => "file ok"); $router->get("/from-var", fn () => "var ok");');

    $this->router->group(['prefix' => 'f'], $file);
    unlink($file);

    expect(dispatch('/f/from-file')->getContent())->toBe('file ok')
        ->and(dispatch('/f/from-var')->getContent())->toBe('var ok');
});

it('middleware global da rota corre em cebola e o do controller também', function (): void {
    $this->router->get('/m', [PostController::class, 'create'])->middleware('tag:route');

    $response = dispatch('/m');

    expect(TagMiddleware::$log)->toBe(['before:route', 'before:controller', 'after:controller', 'after:route'])
        ->and($response->getContent())->toBe('create');
});

it('withoutMiddleware retira middleware herdado do grupo', function (): void {
    $this->router->middleware('tag:g')->group(function (Router $router): void {
        $router->get('/with', fn () => 'a');
        $router->get('/without', fn () => 'b')->withoutMiddleware('tag');
    });

    dispatch('/with');
    expect(TagMiddleware::$log)->toBe(['before:g', 'after:g']);

    TagMiddleware::$log = [];
    dispatch('/without');
    expect(TagMiddleware::$log)->toBe([]);
});

it('grupos de middleware expandem para as suas classes', function (): void {
    $this->router->middlewareGroup('demo', ['tag:one', 'tag:two']);
    $this->router->get('/g', fn () => 'ok')->middleware('demo');

    dispatch('/g');

    expect(TagMiddleware::$log)->toBe(['before:one', 'before:two', 'after:two', 'after:one']);
});

it('fallback() trata GET sem rota', function (): void {
    $this->router->get('/known', fn () => 'known');
    $this->router->fallback(fn () => response('custom 404', 404));

    $response = dispatch('/qualquer/coisa');

    expect($response->getStatusCode())->toBe(404)->and($response->getContent())->toBe('custom 404');
});

it('redirect() e permanentRedirect() criam rotas de redirecionamento', function (): void {
    $this->router->redirect('/old', '/new');
    $this->router->permanentRedirect('/older', 'https://example.org/x');

    $first = dispatch('/old');
    $second = dispatch('/older');

    expect($first)->toBeInstanceOf(RedirectResponse::class)
        ->and($first->getStatusCode())->toBe(302)
        ->and($first->getHeader('Location'))->toBe('http://localhost/new')
        ->and($second->getStatusCode())->toBe(301)
        ->and($second->getHeader('Location'))->toBe('https://example.org/x');
});

it('view() devolve um ViewResponse já renderizado dentro do pipeline', function (): void {
    $this->app->instance('view', new FakeRenderable());
    $this->router->view('/welcome', 'pages.welcome', ['name' => 'Ana']);

    $response = dispatch('/welcome');

    expect($response)->toBeInstanceOf(ViewResponse::class)
        ->and(FakeRenderable::$renders)->toBe(1)
        ->and($response->getContent())->toBe('<p>pages.welcome:{"name":"Ana"}</p>')
        ->and(FakeRenderable::$renders)->toBe(1);
});

it('view() de uma view inexistente falha com ViewNotFoundException', function (): void {
    $this->router->view('/welcome', 'pages.welcome');

    dispatch('/welcome');
})->throws(Slendie\View\ViewNotFoundException::class);

it('toResponse converte string, array, Arrayable, Renderable, null e Response', function (): void {
    $router = $this->router;

    expect($router->toResponse('texto')->getContent())->toBe('texto')
        ->and($router->toResponse(['a' => 1]))->toBeInstanceOf(JsonResponse::class)
        ->and($router->toResponse(['a' => 1])->getContent())->toBe('{"a":1}')
        ->and($router->toResponse(new class () implements Slendie\Contracts\Arrayable {
            public function toArray(): array
            {
                return ['k' => 'v'];
            }
        })->getContent())->toBe('{"k":"v"}')
        ->and($router->toResponse(null)->getContent())->toBe('')
        ->and($router->toResponse(null)->getStatusCode())->toBe(200)
        ->and($router->toResponse(new FakeRenderable('v'))->getContent())->toBe('<p>v:[]</p>');

    $existing = new Response('x', 418);
    expect($router->toResponse($existing))->toBe($existing)
        ->and($router->toResponse(new class () implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return ['j' => true];
            }
        })->getContent())->toBe('{"j":true}');
});

it('toResponse recusa tipos que não sabe converter', function (): void {
    $this->router->toResponse(12);
})->throws(UnexpectedValueException::class);

it('route-model binding: UrlRoutable substitui o parâmetro ou dá 404', function (): void {
    $this->router->get('/articles/{article}', fn (Article $article) => 'article:'.$article->id);

    expect(dispatch('/articles/42')->getContent())->toBe('article:42');

    dispatch('/articles/7');
})->throws(Slendie\Exceptions\NotFoundHttpException::class);

it('current(), currentRouteName(), has() e is()', function (): void {
    $this->router->get('/named', fn () => 'x')->name('site.named');
    dispatch('/named');

    expect($this->router->current()?->uri())->toBe('/named')
        ->and($this->router->currentRouteName())->toBe('site.named')
        ->and($this->router->has('site.named'))->toBeTrue()
        ->and($this->router->is('site.*'))->toBeTrue()
        ->and($this->router->is('admin.*'))->toBeFalse();
});

it('a facade Route fala com o router do container', function (): void {
    Route::get('/facade', fn () => 'via facade');

    expect(dispatch('/facade')->getContent())->toBe('via facade');
});

it('UrlGenerator gera URLs por nome, com parâmetros, opcionais e query', function (): void {
    $this->router->get('/posts/{post}/comments/{comment?}', fn () => 'x')->name('comments');
    $this->router->get('/', fn () => 'home')->name('home');

    $url = app(UrlGenerator::class);

    expect($url->route('home'))->toBe('http://localhost')
        ->and($url->route('comments', ['post' => 3]))->toBe('http://localhost/posts/3/comments')
        ->and($url->route('comments', ['post' => 3, 'comment' => 'a b']))->toBe('http://localhost/posts/3/comments/a%20b')
        ->and($url->route('comments', [3, 9]))->toBe('http://localhost/posts/3/comments/9')
        ->and($url->route('comments', ['post' => 3, 'page' => 2]))->toBe('http://localhost/posts/3/comments?page=2')
        ->and($url->route('comments', ['post' => 3], absolute: false))->toBe('/posts/3/comments')
        ->and(route('home'))->toBe('http://localhost');
});

it('UrlGenerator lança para rota inexistente ou parâmetro em falta', function (): void {
    $this->router->get('/posts/{post}', fn () => 'x')->name('post');

    $url = app(UrlGenerator::class);

    expect(fn () => $url->route('nope'))->toThrow(UrlGenerationException::class, 'Route [nope] not defined.')
        ->and(fn () => $url->route('post'))->toThrow(UrlGenerationException::class, 'Missing required parameter [post]');
});

it('UrlGenerator::to/asset/current/previous', function (): void {
    $url = app(UrlGenerator::class);

    expect($url->to('a/b'))->toBe('http://localhost/a/b')
        ->and($url->to('/a', [1, 'x y']))->toBe('http://localhost/a/1/x%20y')
        ->and($url->to('https://other.test/p'))->toBe('https://other.test/p')
        ->and($url->to('a', [], true))->toBe('https://localhost/a')
        ->and($url->asset('css/app.css'))->toBe('http://localhost/css/app.css')
        ->and($url->previous('/fallback'))->toBe('http://localhost/fallback')
        ->and(url('x'))->toBe('http://localhost/x')
        ->and(asset('img/a.png'))->toBe('http://localhost/img/a.png');
});

it('resource() aceita o nome com barras e parâmetros personalizados', function (): void {
    $routes = $this->router->resource('/articles/', PostController::class, ['only' => ['show'], 'parameters' => ['articles' => 'article']]);

    expect($routes[0]->uri())->toBe('/articles/{article}')
        ->and($routes[0]->getName())->toBe('articles.show');
});
