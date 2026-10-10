<?php

declare(strict_types=1);

namespace Slendie\Routing;

use Countable;
use IteratorAggregate;
use Slendie\Exceptions\MethodNotAllowedHttpException;
use Slendie\Exceptions\NotFoundHttpException;
use Slendie\Http\HttpMethod;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Traversable;

/**
 * @implements IteratorAggregate<int, Route>
 */
class RouteCollection implements Countable, IteratorAggregate
{
    /** @var list<Route> */
    protected array $routes = [];

    /** @var array<string, list<Route>> índice por método */
    protected array $byMethod = [];

    /** @var array<string, Route> */
    protected array $named = [];

    protected ?Route $fallback = null;

    public function add(Route $route): Route
    {
        $this->routes[] = $route;

        foreach ($route->methods() as $method) {
            $this->byMethod[$method->value][] = $route;
        }

        if ($route->getName() !== null) {
            $this->named[$route->getName()] = $route;
        }

        return $route;
    }

    public function setFallback(Route $route): Route
    {
        return $this->fallback = $route;
    }

    /**
     * @throws NotFoundHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function match(Request $request): Route
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->candidates($method) as $route) {
            if ($route->matchesPath($path)) {
                return $route;
            }
        }

        $allowed = $this->methodsAllowedFor($path, $method);

        if ($allowed !== []) {
            if ($method === HttpMethod::OPTIONS) {
                return $this->automaticOptions($path, $allowed);
            }

            throw new MethodNotAllowedHttpException($allowed, 'The '.$method->value.' method is not supported for route '.ltrim($path, '/').'. Supported methods: '.implode(', ', $allowed).'.');
        }

        if ($this->fallback !== null && in_array($method, [HttpMethod::GET, HttpMethod::HEAD], true)) {
            $this->fallback->matchesPath($path);

            return $this->fallback;
        }

        throw new NotFoundHttpException('The route '.ltrim($path, '/').' could not be found.');
    }

    /**
     * Rotas que podem responder ao método; HEAD cai em GET.
     *
     * @return list<Route>
     */
    private function candidates(HttpMethod $method): array
    {
        $routes = $this->byMethod[$method->value] ?? [];

        if ($method === HttpMethod::HEAD) {
            $routes = [...$routes, ...($this->byMethod[HttpMethod::GET->value] ?? [])];
        }

        return $routes;
    }

    /**
     * Métodos para os quais o caminho tem rota (para o header Allow e para o 405).
     *
     * @return list<string>
     */
    private function methodsAllowedFor(string $path, HttpMethod $except): array
    {
        $allowed = [];

        foreach ($this->byMethod as $method => $routes) {
            if ($method === $except->value) {
                continue;
            }

            foreach ($routes as $route) {
                if ($route->matchesPath($path)) {
                    $allowed[] = $method;

                    break;
                }
            }
        }

        if (in_array('GET', $allowed, true) && ! in_array('HEAD', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        if ($allowed !== [] && ! in_array('OPTIONS', $allowed, true)) {
            $allowed[] = 'OPTIONS';
        }

        sort($allowed);

        return $allowed;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function automaticOptions(string $path, array $allowed): Route
    {
        $route = new Route(HttpMethod::OPTIONS, $path, static fn (): Response => new Response('', 204, ['Allow' => implode(', ', $allowed)]));

        // Herda o middleware (ex.: grupo "api" com CORS) das rotas do mesmo caminho.
        foreach ($this->routes as $candidate) {
            if ($candidate->matchesPath($path)) {
                $route->middleware($candidate->middleware());

                break;
            }
        }

        $route->matchesPath($path);

        return $route;
    }

    public function getByName(string $name): ?Route
    {
        if (! isset($this->named[$name])) {
            $this->refreshNameLookups();
        }

        return $this->named[$name] ?? null;
    }

    public function hasNamedRoute(string $name): bool
    {
        return $this->getByName($name) !== null;
    }

    public function refreshNameLookups(): void
    {
        $this->named = [];

        foreach ($this->routes as $route) {
            if ($route->getName() !== null) {
                $this->named[$route->getName()] = $route;
            }
        }
    }

    /** @return list<Route> */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    public function getIterator(): Traversable
    {
        yield from $this->routes;
    }
}
