<?php

declare(strict_types=1);

namespace Slendie\Routing;

use Closure;
use JsonSerializable;
use LogicException;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use Slendie\Container\Container;
use Slendie\Contracts\Arrayable;
use Slendie\Contracts\Renderable;
use Slendie\Contracts\ViewFactory;
use Slendie\Http\HttpMethod;
use Slendie\Http\JsonResponse;
use Slendie\Http\Middleware\SubstituteBindings;
use Slendie\Http\RedirectResponse;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Http\ViewResponse;
use Slendie\Support\Str;
use Stringable;
use UnexpectedValueException;

class Router
{
    /** @var array<string, string> alias => classe */
    protected array $middlewareAliases = [];

    /** @var array<string, list<string>> */
    protected array $middlewareGroups = [];

    /** @var list<array<string, mixed>> pilha de atributos de grupo */
    protected array $groupStack = [];

    protected ?Route $current = null;

    public function __construct(protected Container $container, protected RouteCollection $routes = new RouteCollection())
    {
    }

    // -------------------------------------------------------------------------
    // Registo de rotas
    // -------------------------------------------------------------------------

    /**
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function get(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute([HttpMethod::GET], $uri, $action);
    }

    /**
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function post(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute([HttpMethod::POST], $uri, $action);
    }

    /**
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function put(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute([HttpMethod::PUT], $uri, $action);
    }

    /**
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function patch(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute([HttpMethod::PATCH], $uri, $action);
    }

    /**
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function delete(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute([HttpMethod::DELETE], $uri, $action);
    }

    /**
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function options(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute([HttpMethod::OPTIONS], $uri, $action);
    }

    /**
     * Todos os métodos (HEAD vem com GET).
     *
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function any(string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute(
            [HttpMethod::GET, HttpMethod::POST, HttpMethod::PUT, HttpMethod::PATCH, HttpMethod::DELETE, HttpMethod::OPTIONS],
            $uri,
            $action,
        );
    }

    /**
     * @param  list<string|HttpMethod>  $methods
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function match(array $methods, string $uri, Closure|array|string $action): Route
    {
        return $this->addRoute($methods, $uri, $action);
    }

    /**
     * Rota GET que devolve uma view directamente.
     *
     * @param  array<string, mixed>  $data
     */
    public function view(string $uri, string $view, array $data = []): Route
    {
        return $this->get($uri, function () use ($view, $data): Renderable {
            if (! $this->container->bound('view')) {
                throw new LogicException('Route::view() precisa do motor de views (peça core-04-03).');
            }

            $factory = $this->container->make('view');

            if (! $factory instanceof ViewFactory) {
                throw new LogicException('O serviço [view] tem de implementar Slendie\Contracts\ViewFactory.');
            }

            return $factory->make($view, $data);
        });
    }

    public function redirect(string $uri, string $destination, int $status = 302): Route
    {
        return $this->any($uri, fn (): RedirectResponse => new RedirectResponse(
            $this->container->make(UrlGenerator::class)->to($destination),
            $status,
        ));
    }

    public function permanentRedirect(string $uri, string $destination): Route
    {
        return $this->redirect($uri, $destination, 301);
    }

    /**
     * Acção para pedidos GET/HEAD sem rota correspondente.
     *
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function fallback(Closure|array|string $action): Route
    {
        $route = $this->createRoute([HttpMethod::GET, HttpMethod::HEAD], '/__fallback', $action);

        return $this->routes->setFallback($route);
    }

    /**
     * @param  list<string|HttpMethod>  $methods
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function addRoute(array $methods, string $uri, Closure|array|string $action): Route
    {
        return $this->routes->add($this->createRoute($methods, $uri, $action));
    }

    /**
     * @param  list<string|HttpMethod>  $methods
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    protected function createRoute(array $methods, string $uri, Closure|array|string $action): Route
    {
        $route = new Route($methods, $uri, $action);
        $group = $this->groupStack === [] ? [] : $this->groupStack[array_key_last($this->groupStack)];

        if (isset($group['prefix']) && is_string($group['prefix'])) {
            $route->prefix($group['prefix']);
        }

        if (isset($group['as']) && is_string($group['as'])) {
            $route->setNamePrefix($group['as']);
        }

        if (isset($group['controller']) && is_string($group['controller']) && class_exists($group['controller']) && is_string($action) && ! str_contains($action, '@')) {
            $route->setController($group['controller']);
        }

        if (isset($group['middleware']) && is_array($group['middleware'])) {
            /** @var list<string> $groupMiddleware */
            $groupMiddleware = $group['middleware'];
            $route->middleware($groupMiddleware);
        }

        if (isset($group['where']) && is_array($group['where'])) {
            /** @var array<string, string> $wheres */
            $wheres = $group['where'];
            $route->where($wheres);
        }

        return $route;
    }

    // -------------------------------------------------------------------------
    // Resources
    // -------------------------------------------------------------------------

    /**
     * Gera as 7 rotas REST de um recurso (posts.index, posts.create, ...).
     *
     * @param  class-string  $controller
     * @param  array{only?: list<string>, except?: list<string>, parameters?: array<string, string>}  $options
     * @return list<Route>
     */
    public function resource(string $name, string $controller, array $options = []): array
    {
        return $this->registerResource($name, $controller, $options, ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    }

    /**
     * Como resource() mas sem create/edit (APIs).
     *
     * @param  class-string  $controller
     * @param  array{only?: list<string>, except?: list<string>, parameters?: array<string, string>}  $options
     * @return list<Route>
     */
    public function apiResource(string $name, string $controller, array $options = []): array
    {
        return $this->registerResource($name, $controller, $options, ['index', 'store', 'show', 'update', 'destroy']);
    }

    /**
     * @param  class-string  $controller
     * @param  array{only?: list<string>, except?: list<string>, parameters?: array<string, string>}  $options
     * @param  list<string>  $actions
     * @return list<Route>
     */
    protected function registerResource(string $name, string $controller, array $options, array $actions): array
    {
        if (isset($options['only'])) {
            $actions = array_values(array_intersect($actions, $options['only']));
        }

        if (isset($options['except'])) {
            $actions = array_values(array_diff($actions, $options['except']));
        }

        // posts.comments => posts/{post}/comments/{comment}
        $name = trim(str_replace('/', '.', trim($name, '/')), '.');
        $segments = explode('.', $name);
        $last = array_pop($segments);
        $base = '';

        foreach ($segments as $segment) {
            $base .= $segment.'/{'.$this->resourceParameter($segment, $options).'}/';
        }

        $collection = $base.$last;
        $member = $collection.'/{'.$this->resourceParameter($last, $options).'}';

        $definitions = [
            'index' => [[HttpMethod::GET], $collection],
            'create' => [[HttpMethod::GET], $collection.'/create'],
            'store' => [[HttpMethod::POST], $collection],
            'show' => [[HttpMethod::GET], $member],
            'edit' => [[HttpMethod::GET], $member.'/edit'],
            'update' => [[HttpMethod::PUT, HttpMethod::PATCH], $member],
            'destroy' => [[HttpMethod::DELETE], $member],
        ];

        $routes = [];

        foreach ($actions as $action) {
            [$methods, $uri] = $definitions[$action];

            $routes[] = $this->addRoute($methods, $uri, [$controller, $action])->name($name.'.'.$action);
        }

        /** @var list<Route> $routes */
        return $routes;
    }

    /**
     * @param  array{parameters?: array<string, string>}  $options
     */
    protected function resourceParameter(string $segment, array $options): string
    {
        return $options['parameters'][$segment] ?? Str::snake(Str::singular($segment));
    }

    // -------------------------------------------------------------------------
    // Grupos
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $attributes  prefix, as, middleware, controller, where
     */
    public function group(array $attributes, Closure|string $routes): void
    {
        $this->withAttributes($attributes, function () use ($routes): void {
            if ($routes instanceof Closure) {
                $routes($this);

                return;
            }

            $this->loadRoutes($routes);
        });
    }

    /**
     * Executa $callback com os atributos de grupo activos.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withAttributes(array $attributes, Closure $callback): mixed
    {
        $parent = $this->groupStack === [] ? [] : $this->groupStack[array_key_last($this->groupStack)];
        $this->groupStack[] = RouteGroup::merge($attributes, $parent);

        try {
            return $callback();
        } finally {
            array_pop($this->groupStack);
        }
    }

    public function prefix(string $prefix): RouteGroup
    {
        return (new RouteGroup($this))->prefix($prefix);
    }

    /**
     * @param  string|list<string>  ...$middleware
     */
    public function middleware(string|array ...$middleware): RouteGroup
    {
        return (new RouteGroup($this))->middleware(...$middleware);
    }

    public function name(string $prefix): RouteGroup
    {
        return (new RouteGroup($this))->name($prefix);
    }

    /**
     * @param  class-string  $controller
     */
    public function controller(string $controller): RouteGroup
    {
        return (new RouteGroup($this))->controller($controller);
    }

    /**
     * Carrega um ficheiro de rotas; dentro dele `$router` e a facade Route estão disponíveis.
     */
    public function loadRoutes(string $file): void
    {
        $load = static function (Router $router, string $file): void {
            require $file;
        };

        $load($this, $file);
    }

    // -------------------------------------------------------------------------
    // Middleware
    // -------------------------------------------------------------------------

    public function aliasMiddleware(string $alias, string $class): static
    {
        $this->middlewareAliases[$alias] = $class;

        return $this;
    }

    /**
     * @param  list<string>  $middleware
     */
    public function middlewareGroup(string $name, array $middleware): static
    {
        $this->middlewareGroups[$name] = $middleware;

        return $this;
    }

    public function pushMiddlewareToGroup(string $group, string $middleware): static
    {
        $this->middlewareGroups[$group] ??= [];

        if (! in_array($middleware, $this->middlewareGroups[$group], true)) {
            $this->middlewareGroups[$group][] = $middleware;
        }

        return $this;
    }

    public function prependMiddlewareToGroup(string $group, string $middleware): static
    {
        $this->middlewareGroups[$group] ??= [];

        if (! in_array($middleware, $this->middlewareGroups[$group], true)) {
            array_unshift($this->middlewareGroups[$group], $middleware);
        }

        return $this;
    }

    /** @return array<string, string> */
    public function getMiddleware(): array
    {
        return $this->middlewareAliases;
    }

    /** @return array<string, list<string>> */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Expande grupos e aliases para `Classe[:params]`.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public function resolveMiddleware(array $names): array
    {
        $resolved = [];

        foreach ($names as $name) {
            $key = Str::before($name, ':');
            $parameters = str_contains($name, ':') ? Str::after($name, ':') : null;

            if (isset($this->middlewareGroups[$key])) {
                array_push($resolved, ...$this->resolveMiddleware($this->middlewareGroups[$key]));

                continue;
            }

            $class = $this->middlewareAliases[$key] ?? $key;
            $resolved[] = $parameters === null ? $class : $class.':'.$parameters;
        }

        return array_values(array_unique($resolved));
    }

    /**
     * Middleware efectivo da rota: grupos/aliases da rota + middleware do controller + SubstituteBindings.
     *
     * @return list<string>
     */
    public function gatherRouteMiddleware(Route $route): array
    {
        $names = $route->middleware();

        $class = $route->getControllerClass();

        if ($class !== null && method_exists($class, 'getMiddleware')) {
            $controller = $route->getControllerInstance() ?? $this->container->make($class);
            $route->setControllerInstance($controller);

            /** @var list<string> $fromController */
            $fromController = $controller->getMiddleware();
            $names = [...$names, ...$fromController];
        }

        $resolved = $this->resolveMiddleware($names);
        $resolved[] = SubstituteBindings::class;

        $excluded = $this->resolveMiddleware($route->excludedMiddleware());

        return array_values(array_filter(
            array_unique($resolved),
            static fn (string $middleware): bool => ! in_array(Str::before($middleware, ':'), array_map(static fn (string $e): string => Str::before($e, ':'), $excluded), true),
        ));
    }

    // -------------------------------------------------------------------------
    // Despacho
    // -------------------------------------------------------------------------

    public function dispatch(Request $request): Response
    {
        $this->container->instance('request', $request);
        $this->container->instance(Request::class, $request);

        $route = $this->routes->match($request);
        $route->setControllerInstance(null);

        $request->setRoute($route);
        $this->current = $route;

        $result = (new Pipeline($this->container))
            ->send($request)
            ->through($this->gatherRouteMiddleware($route))
            ->then(fn (Request $request): mixed => $this->toResponse($this->runRoute($request, $route)));

        return $this->toResponse($result);
    }

    /**
     * Executa a acção da rota com injecção de dependências e os parâmetros do URL.
     */
    public function runRoute(Request $request, Route $route): mixed
    {
        $action = $route->resolvedAction();
        $parameters = $this->coerceParameters($route->reflector(), $route->parameters());

        if ($action instanceof Closure) {
            return $this->container->call($action, $parameters);
        }

        $controller = $route->getControllerInstance() ?? $this->container->make($action[0]);
        $route->setControllerInstance($controller);

        return $this->container->call([$controller, $action[1]], $parameters);
    }

    /**
     * Converte parâmetros de rota (strings) para o tipo escalar declarado na acção.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function coerceParameters(ReflectionFunctionAbstract $function, array $parameters): array
    {
        foreach ($function->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            if (! isset($parameters[$name]) || ! is_string($parameters[$name]) || ! $type instanceof ReflectionNamedType) {
                continue;
            }

            $value = $parameters[$name];

            $parameters[$name] = match ($type->getName()) {
                'int' => preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value,
                'float' => is_numeric($value) ? (float) $value : $value,
                'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
                default => $value,
            };
        }

        return $parameters;
    }

    public function toResponse(mixed $result): Response
    {
        return match (true) {
            $result instanceof Response => $result,
            $result instanceof Renderable => new ViewResponse($result),
            $result instanceof Arrayable, $result instanceof JsonSerializable, is_array($result) => new JsonResponse($result),
            is_string($result), $result instanceof Stringable => new Response((string) $result),
            $result === null => new Response(''),
            default => throw new UnexpectedValueException('A acção devolveu um valor que não se converte em resposta: '.get_debug_type($result).'.'),
        };
    }

    // -------------------------------------------------------------------------
    // Informação
    // -------------------------------------------------------------------------

    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    public function current(): ?Route
    {
        return $this->current;
    }

    public function currentRouteName(): ?string
    {
        return $this->current?->getName();
    }

    public function has(string $name): bool
    {
        return $this->routes->hasNamedRoute($name);
    }

    /**
     * A rota actual tem um dos nomes (aceita `*`)?
     */
    public function is(string ...$patterns): bool
    {
        $name = $this->currentRouteName();

        return $name !== null && Str::is($patterns, $name);
    }
}
