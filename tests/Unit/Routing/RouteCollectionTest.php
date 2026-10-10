<?php

declare(strict_types=1);

use Slendie\Exceptions\MethodNotAllowedHttpException;
use Slendie\Exceptions\NotFoundHttpException;
use Slendie\Http\Request;
use Slendie\Routing\Route;
use Slendie\Routing\RouteCollection;

beforeEach(function (): void {
    $this->routes = new RouteCollection();
    $this->routes->add(new Route('GET', '/posts', fn () => 'list'));
    $this->routes->add(new Route('POST', '/posts', fn () => 'store'));
    $this->routes->add((new Route('GET', '/about', fn () => 'about'))->setNamePrefix('site.')->name('about'));
});

it('devolve a rota certa por método', function (): void {
    expect($this->routes->match(Request::create('/posts'))->methods()[0]->value)->toBe('GET')
        ->and($this->routes->match(Request::create('/posts', 'POST'))->methods()[0]->value)->toBe('POST');
});

it('lança 404 quando nenhuma rota coincide', function (): void {
    $this->routes->match(Request::create('/nada'));
})->throws(NotFoundHttpException::class);

it('lança 405 com header Allow quando o caminho existe com outros métodos', function (): void {
    try {
        $this->routes->match(Request::create('/posts', 'DELETE'));
        $this->fail('Devia lançar 405');
    } catch (MethodNotAllowedHttpException $e) {
        expect($e->getStatusCode())->toBe(405)
            ->and($e->getHeaders()['Allow'])->toBe('GET, HEAD, OPTIONS, POST');
    }
});

it('HEAD cai em GET', function (): void {
    $route = $this->routes->match(Request::create('/about', 'HEAD'));

    expect($route->getName())->toBe('site.about');
});

it('responde a OPTIONS automaticamente com 204 e Allow', function (): void {
    $route = $this->routes->match(Request::create('/posts', 'OPTIONS'));
    $response = $route->resolvedAction()();

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getHeader('Allow'))->toBe('GET, HEAD, OPTIONS, POST');
});

it('o OPTIONS automático herda o middleware das rotas do caminho', function (): void {
    $routes = new RouteCollection();
    $routes->add((new Route('POST', '/api/x', fn () => 'x'))->middleware('api'));

    expect($routes->match(Request::create('/api/x', 'OPTIONS'))->middleware())->toBe(['api']);
});

it('encontra rotas por nome, mesmo nomeadas depois de adicionadas', function (): void {
    $late = $this->routes->add(new Route('GET', '/late', fn () => 'x'));
    $late->name('late');

    expect($this->routes->getByName('site.about'))->not->toBeNull()
        ->and($this->routes->hasNamedRoute('late'))->toBeTrue()
        ->and($this->routes->hasNamedRoute('inexistente'))->toBeFalse()
        ->and($this->routes)->toHaveCount(4);
});

it('usa a rota fallback só para GET/HEAD sem correspondência', function (): void {
    $fallback = new Route('GET', '/__fallback', fn () => 'fb');
    $this->routes->setFallback($fallback);

    expect($this->routes->match(Request::create('/qualquer/coisa')))->toBe($fallback);

    $this->routes->match(Request::create('/qualquer', 'POST'));
})->throws(NotFoundHttpException::class);
