<?php

declare(strict_types=1);

namespace Slendie\Http;

use Slendie\Foundation\Application;
use Slendie\Foundation\Exceptions\Handler;
use Slendie\Http\Middleware\HandleCors;
use Slendie\Http\Middleware\SecurityHeaders;
use Slendie\Http\Middleware\ShareErrorsFromSession;
use Slendie\Http\Middleware\StartSession;
use Slendie\Http\Middleware\TrimStrings;
use Slendie\Http\Middleware\VerifyCsrfToken;
use Slendie\Routing\Pipeline;
use Slendie\Routing\Route;
use Slendie\Routing\Router;
use Throwable;

/**
 * Ponto de entrada HTTP: arranca a aplicação, passa o pedido pelo middleware global e pelo Router
 * e converte qualquer excepção numa Response.
 */
class Kernel
{
    /**
     * Middleware global (todos os pedidos).
     *
     * @var list<string>
     */
    protected array $middleware = [
        TrimStrings::class,
        SecurityHeaders::class,
    ];

    /**
     * @var array<string, list<class-string>>
     */
    protected array $middlewareGroups = [
        'web' => [
            StartSession::class,
            VerifyCsrfToken::class,
            ShareErrorsFromSession::class,
        ],
        'api' => [
            HandleCors::class,
        ],
    ];

    /**
     * @var array<string, class-string>
     */
    protected array $middlewareAliases = [
        'csrf' => VerifyCsrfToken::class,
        'cors' => HandleCors::class,
        // 'throttle' fica fora (sem rate limiting nesta versão).
    ];

    public function __construct(protected Application $app, protected Router $router)
    {
        foreach ($this->middlewareGroups as $name => $middleware) {
            $this->router->middlewareGroup($name, $middleware);
        }

        foreach ($this->middlewareAliases as $alias => $class) {
            $this->router->aliasMiddleware($alias, $class);
        }
    }

    /**
     * Processa o pedido e devolve sempre uma Response (nunca deixa escapar excepções).
     */
    public function handle(Request $request): Response
    {
        $this->app->instance('request', $request);
        $this->app->instance(Request::class, $request);

        try {
            $this->app->bootstrap();

            $response = $this->sendRequestThroughRouter($request);
        } catch (Throwable $e) {
            $this->reportException($e);

            $response = $this->renderException($request, $e);
        }

        return $response->prepare($request);
    }

    protected function sendRequestThroughRouter(Request $request): Response
    {
        $response = (new Pipeline($this->app))
            ->send($request)
            ->through($this->middleware)
            ->then(fn (Request $request): Response => $this->router->dispatch($request));

        return $response instanceof Response ? $response : $this->router->toResponse($response);
    }

    /**
     * Depois de a resposta ser enviada: chama terminate() nos middlewares que o tenham.
     */
    public function terminate(Request $request, Response $response): void
    {
        $middleware = $this->middleware;
        $route = $request->route();

        if ($route instanceof Route) {
            $middleware = [...$middleware, ...$this->router->gatherRouteMiddleware($route)];
        }

        foreach (array_unique($middleware) as $name) {
            $instance = $this->app->make(explode(':', $name, 2)[0]);

            if (is_object($instance) && method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }

        $this->app->terminate();
    }

    // -------------------------------------------------------------------------
    // Configuração usada por providers e packages
    // -------------------------------------------------------------------------

    /** Acrescenta middleware global no fim. */
    public function pushMiddleware(string $middleware): static
    {
        if (! in_array($middleware, $this->middleware, true)) {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    /** Acrescenta middleware global no início. */
    public function prependMiddleware(string $middleware): static
    {
        if (! in_array($middleware, $this->middleware, true)) {
            array_unshift($this->middleware, $middleware);
        }

        return $this;
    }

    public function appendMiddlewareToGroup(string $group, string $middleware): static
    {
        $this->router->pushMiddlewareToGroup($group, $middleware);

        return $this;
    }

    public function prependMiddlewareToGroup(string $group, string $middleware): static
    {
        $this->router->prependMiddlewareToGroup($group, $middleware);

        return $this;
    }

    public function aliasMiddleware(string $alias, string $class): static
    {
        $this->router->aliasMiddleware($alias, $class);

        return $this;
    }

    /** @return list<string> */
    public function getGlobalMiddleware(): array
    {
        return $this->middleware;
    }

    /** @return array<string, list<string>> */
    public function getMiddlewareGroups(): array
    {
        return $this->router->getMiddlewareGroups();
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    // -------------------------------------------------------------------------

    protected function reportException(Throwable $e): void
    {
        try {
            $this->app->make(Handler::class)->report($e);
        } catch (Throwable) {
            // O log nunca pode esconder o erro original.
        }
    }

    protected function renderException(Request $request, Throwable $e): Response
    {
        try {
            return $this->app->make(Handler::class)->render($request, $e);
        } catch (Throwable) {
            return new Response('<!doctype html><meta charset="utf-8"><title>500</title><h1>500</h1><p>Server Error</p>', 500, ['Content-Type' => 'text/html; charset=UTF-8']);
        }
    }
}
