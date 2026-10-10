<?php

declare(strict_types=1);

use Slendie\Http\HttpMethod;
use Slendie\Http\Request;
use Slendie\Routing\Route;
use Slendie\Tests\Fixtures\Http\InvokableController;
use Slendie\Tests\Fixtures\Http\PostController;

it('compila uma rota com parâmetro e extrai-o', function (): void {
    $route = new Route('GET', '/posts/{id}', fn () => 'x');

    expect($route->compile()->parameterNames)->toBe(['id'])
        ->and($route->matchesPath('/posts/42'))->toBeTrue()
        ->and($route->parameters())->toBe(['id' => '42'])
        ->and($route->matchesPath('/posts'))->toBeFalse()
        ->and($route->matchesPath('/posts/42/extra'))->toBeFalse();
});

it('trata parâmetros opcionais', function (): void {
    $route = new Route('GET', '/posts/{id?}', fn () => 'x');

    expect($route->matchesPath('/posts'))->toBeTrue()
        ->and($route->parameters())->toBe([])
        ->and($route->matchesPath('/posts/7'))->toBeTrue()
        ->and($route->parameters())->toBe(['id' => '7'])
        ->and($route->compile()->optionalNames)->toBe(['id']);
});

it('aplica constraints whereNumber, whereAlpha, whereUuid e whereIn', function (): void {
    $number = (new Route('GET', '/posts/{id}', fn () => 'x'))->whereNumber('id');
    expect($number->matchesPath('/posts/12'))->toBeTrue()
        ->and($number->matchesPath('/posts/abc'))->toBeFalse();

    $alpha = (new Route('GET', '/tags/{tag}', fn () => 'x'))->whereAlpha('tag');
    expect($alpha->matchesPath('/tags/php'))->toBeTrue()
        ->and($alpha->matchesPath('/tags/php8'))->toBeFalse();

    $uuid = (new Route('GET', '/u/{id}', fn () => 'x'))->whereUuid('id');
    expect($uuid->matchesPath('/u/123e4567-e89b-12d3-a456-426614174000'))->toBeTrue()
        ->and($uuid->matchesPath('/u/nope'))->toBeFalse();

    $in = (new Route('GET', '/{lang}/home', fn () => 'x'))->whereIn('lang', ['pt', 'en']);
    expect($in->matchesPath('/pt/home'))->toBeTrue()
        ->and($in->matchesPath('/fr/home'))->toBeFalse();

    $custom = (new Route('GET', '/files/{path}', fn () => 'x'))->where('path', '.+');
    expect($custom->matchesPath('/files/a/b/c.txt'))->toBeTrue()
        ->and($custom->parameter('path'))->toBe('a/b/c.txt');
});

it('suporta literais misturados com parâmetros no mesmo segmento', function (): void {
    $route = new Route('GET', '/invoice-{number}.pdf', fn () => 'x');

    expect($route->matchesPath('/invoice-2026.pdf'))->toBeTrue()
        ->and($route->parameter('number'))->toBe('2026')
        ->and($route->matchesPath('/invoice-2026.html'))->toBeFalse();
});

it('descodifica o valor do parâmetro', function (): void {
    $route = new Route('GET', '/tags/{tag}', fn () => 'x');
    $route->matchesPath('/tags/caf%C3%A9');

    expect($route->parameter('tag'))->toBe('café');
});

it('faz match na raiz', function (): void {
    $route = new Route('GET', '/', fn () => 'x');

    expect($route->uri())->toBe('/')
        ->and($route->matchesPath('/'))->toBeTrue()
        ->and($route->matchesPath('/x'))->toBeFalse();
});

it('matches() verifica método e caminho; HEAD cai em GET', function (): void {
    $route = new Route('GET', '/ping', fn () => 'x');

    expect($route->matches(Request::create('/ping')))->toBeTrue()
        ->and($route->matches(Request::create('/ping', 'HEAD')))->toBeTrue()
        ->and($route->matches(Request::create('/ping', 'POST')))->toBeFalse();
});

it('guarda nome com prefixo de grupo, middleware e defaults', function (): void {
    $route = (new Route(['GET', HttpMethod::POST], '/a', fn () => 'x'))
        ->setNamePrefix('admin.')
        ->name('home')
        ->middleware('auth', ['throttle:5'])
        ->middleware('auth')
        ->withoutMiddleware('csrf')
        ->defaults(['page' => 1]);

    $route->matchesPath('/a');

    expect($route->getName())->toBe('admin.home')
        ->and($route->middleware())->toBe(['auth', 'throttle:5'])
        ->and($route->excludedMiddleware())->toBe(['csrf'])
        ->and($route->parameters())->toBe(['page' => 1])
        ->and($route->methods())->toBe([HttpMethod::GET, HttpMethod::POST]);
});

it('prefix() antepõe ao URI', function (): void {
    $route = (new Route('GET', '/users', fn () => 'x'))->prefix('/admin/');

    expect($route->uri())->toBe('/admin/users')
        ->and($route->matchesPath('/admin/users'))->toBeTrue();
});

it('resolve os vários formatos de acção', function (): void {
    $closure = new Route('GET', '/a', fn () => 'x');
    expect($closure->getActionName())->toBe('Closure');

    $array = new Route('GET', '/a', [PostController::class, 'index']);
    expect($array->getActionName())->toBe(PostController::class.'@index');

    $string = new Route('GET', '/a', PostController::class.'@show');
    expect($string->resolvedAction())->toBe([PostController::class, 'show']);

    $invokable = new Route('GET', '/a', InvokableController::class);
    expect($invokable->resolvedAction())->toBe([InvokableController::class, '__invoke']);

    $grouped = (new Route('GET', '/a', 'index'))->setController(PostController::class);
    expect($grouped->resolvedAction())->toBe([PostController::class, 'index']);
});

it('recusa acção que não é classe nem método', function (): void {
    (new Route('GET', '/a', 'naoExiste'))->resolvedAction();
})->throws(LogicException::class);
