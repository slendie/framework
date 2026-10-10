<?php

declare(strict_types=1);

namespace Slendie\Routing;

use Closure;
use InvalidArgumentException;
use LogicException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Slendie\Http\HttpMethod;
use Slendie\Http\Request;

class Route
{
    /** @var list<HttpMethod> */
    protected array $methods;

    protected string $uri;

    /** @var Closure|array{0: class-string, 1: string}|string */
    protected Closure|array|string $action;

    protected ?string $name = null;

    protected string $namePrefix = '';

    /** @var list<string> */
    protected array $middleware = [];

    /** @var list<string> */
    protected array $excludedMiddleware = [];

    /** @var array<string, string> */
    protected array $wheres = [];

    /** @var array<string, mixed> */
    protected array $defaults = [];

    /** @var array<string, mixed> */
    protected array $parameters = [];

    /** @var class-string|null */
    protected ?string $controllerClass = null;

    protected ?object $controllerInstance = null;

    protected ?CompiledRoute $compiled = null;

    /**
     * @param  HttpMethod|string|list<HttpMethod|string>  $methods
     * @param  Closure|array{0: class-string, 1: string}|string  $action
     */
    public function __construct(HttpMethod|string|array $methods, string $uri, Closure|array|string $action)
    {
        $this->methods = array_values(array_unique(
            array_map(
                static fn (HttpMethod|string $m): HttpMethod => $m instanceof HttpMethod ? $m : HttpMethod::fromString($m),
                is_array($methods) ? $methods : [$methods],
            ),
            SORT_REGULAR,
        ));

        $this->uri = $this->normalizeUri($uri);
        $this->action = $action;
    }

    // -------------------------------------------------------------------------
    // Definição
    // -------------------------------------------------------------------------

    /** @return list<HttpMethod> */
    public function methods(): array
    {
        return $this->methods;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function prefix(string $prefix): static
    {
        $prefix = trim($prefix, '/');

        if ($prefix !== '') {
            $this->uri = $this->normalizeUri($prefix.'/'.ltrim($this->uri, '/'));
            $this->compiled = null;
        }

        return $this;
    }

    /** Define o nome (concatenado ao prefixo de nome do grupo). */
    public function name(string $name): static
    {
        $this->name = $this->namePrefix.$name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /** Prefixo de nome herdado do grupo (aplicado por name()). */
    public function setNamePrefix(string $prefix): static
    {
        $this->namePrefix = $prefix;

        return $this;
    }

    /**
     * Adiciona middleware; sem argumentos devolve a lista.
     *
     * @param  string|list<string>  ...$middleware
     * @return ($middleware is array{} ? list<string> : static)
     */
    public function middleware(string|array ...$middleware): static|array
    {
        if ($middleware === []) {
            return $this->middleware;
        }

        foreach ($middleware as $item) {
            foreach ((array) $item as $name) {
                if (! in_array($name, $this->middleware, true)) {
                    $this->middleware[] = $name;
                }
            }
        }

        return $this;
    }

    /**
     * Retira middleware (nome, alias ou classe) desta rota.
     *
     * @param  string|list<string>  ...$middleware
     */
    public function withoutMiddleware(string|array ...$middleware): static
    {
        foreach ($middleware as $item) {
            foreach ((array) $item as $name) {
                $this->excludedMiddleware[] = $name;
            }
        }

        return $this;
    }

    /** @return list<string> */
    public function excludedMiddleware(): array
    {
        return $this->excludedMiddleware;
    }

    /**
     * @param  array<string, string>|string  $name
     */
    public function where(array|string $name, ?string $expression = null): static
    {
        foreach (is_array($name) ? $name : [$name => $expression] as $key => $pattern) {
            if ($pattern === null) {
                throw new InvalidArgumentException("Falta a expressão regular para o parâmetro [{$key}].");
            }

            $this->wheres[$key] = $pattern;
        }

        $this->compiled = null;

        return $this;
    }

    public function whereNumber(string ...$parameters): static
    {
        return $this->whereAll($parameters, '[0-9]+');
    }

    public function whereAlpha(string ...$parameters): static
    {
        return $this->whereAll($parameters, '[a-zA-Z]+');
    }

    public function whereAlphaNumeric(string ...$parameters): static
    {
        return $this->whereAll($parameters, '[a-zA-Z0-9]+');
    }

    public function whereUuid(string ...$parameters): static
    {
        return $this->whereAll($parameters, '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
    }

    /**
     * @param  list<string>  $values
     */
    public function whereIn(string $parameter, array $values): static
    {
        return $this->where($parameter, implode('|', array_map(static fn (string $v): string => preg_quote($v, '#'), $values)));
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function defaults(array $defaults): static
    {
        $this->defaults = [...$this->defaults, ...$defaults];

        return $this;
    }

    /**
     * @param  array<array-key, string>  $names
     */
    private function whereAll(array $names, string $pattern): static
    {
        return $this->where(array_fill_keys($names, $pattern));
    }

    // -------------------------------------------------------------------------
    // Acção
    // -------------------------------------------------------------------------

    /**
     * Classe do controller quando a acção é só o nome de um método (Route::controller()).
     *
     * @param  class-string  $class
     */
    public function setController(string $class): static
    {
        $this->controllerClass = $class;

        return $this;
    }

    /** @return Closure|array{0: class-string, 1: string}|string */
    public function getAction(): Closure|array|string
    {
        return $this->action;
    }

    /**
     * Normaliza a acção para Closure ou [classe, método].
     *
     * @return Closure|array{0: class-string, 1: string}
     */
    public function resolvedAction(): Closure|array
    {
        $action = $this->action;

        if ($action instanceof Closure) {
            return $action;
        }

        if (is_array($action)) {
            return $action;
        }

        if (str_contains($action, '@')) {
            [$class, $method] = explode('@', $action, 2);

            if (! class_exists($class)) {
                throw new LogicException("A classe [{$class}] da rota [{$this->uri}] não existe.");
            }

            return [$class, $method];
        }

        if ($this->controllerClass !== null) {
            return [$this->controllerClass, $action];
        }

        if (! class_exists($action)) {
            throw new LogicException("A acção [{$action}] da rota [{$this->uri}] não é uma classe existente.");
        }

        if (! method_exists($action, '__invoke')) {
            throw new LogicException("O controller [{$action}] não é invocável: define __invoke() ou usa [Classe::class, 'método'].");
        }

        return [$action, '__invoke'];
    }

    public function getActionName(): string
    {
        $action = $this->resolvedAction();

        return $action instanceof Closure ? 'Closure' : $action[0].'@'.$action[1];
    }

    public function getControllerClass(): ?string
    {
        $action = $this->resolvedAction();

        return $action instanceof Closure ? null : $action[0];
    }

    public function getControllerInstance(): ?object
    {
        return $this->controllerInstance;
    }

    public function setControllerInstance(?object $controller): static
    {
        $this->controllerInstance = $controller;

        return $this;
    }

    /** Reflection da função/método da acção (para coerção e binding de parâmetros). */
    public function reflector(): ReflectionFunctionAbstract
    {
        $action = $this->resolvedAction();

        return $action instanceof Closure
            ? new ReflectionFunction($action)
            : new ReflectionMethod($action[0], $action[1]);
    }

    // -------------------------------------------------------------------------
    // Compilação e match
    // -------------------------------------------------------------------------

    public function compile(): CompiledRoute
    {
        if ($this->compiled !== null) {
            return $this->compiled;
        }

        $names = [];
        $optional = [];
        $regex = '';

        foreach (explode('/', trim($this->uri, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }

            // Segmento inteiramente opcional: /{param?}
            if (preg_match('/^\{(\w+)\?\}$/', $segment, $m) === 1) {
                $names[] = $optional[] = $m[1];
                $regex .= '(?:/(?P<'.$m[1].'>'.$this->constraint($m[1]).'))?';

                continue;
            }

            $regex .= '/'.preg_replace_callback(
                '/\{(\w+)\}|([^{]+)/',
                function (array $parts) use (&$names): string {
                    if (isset($parts[2])) {
                        return preg_quote($parts[2], '#');
                    }

                    $names[] = $parts[1];

                    return '(?P<'.$parts[1].'>'.$this->constraint($parts[1]).')';
                },
                $segment,
            );
        }

        return $this->compiled = new CompiledRoute('#^'.$regex.'/?$#u', $names, $optional);
    }

    private function constraint(string $name): string
    {
        return str_replace('#', '\#', $this->wheres[$name] ?? '[^/]+');
    }

    /** O caminho coincide com esta rota (independentemente do método)? Regista os parâmetros se sim. */
    public function matchesPath(string $path): bool
    {
        $parameters = $this->compile()->match($path);

        if ($parameters === null) {
            return false;
        }

        $this->parameters = [...$this->defaults, ...$parameters];

        return true;
    }

    public function matches(Request $request): bool
    {
        $method = $request->method();

        $allowed = in_array($method, $this->methods, true)
            || ($method === HttpMethod::HEAD && in_array(HttpMethod::GET, $this->methods, true));

        return $allowed && $this->matchesPath($request->path());
    }

    // -------------------------------------------------------------------------
    // Parâmetros
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return $this->parameters;
    }

    public function parameter(string $name, mixed $default = null): mixed
    {
        return $this->parameters[$name] ?? $default;
    }

    public function setParameter(string $name, mixed $value): static
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    /** @return list<string> */
    public function parameterNames(): array
    {
        return $this->compile()->parameterNames;
    }

    private function normalizeUri(string $uri): string
    {
        return '/'.trim($uri, '/');
    }
}
