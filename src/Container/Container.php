<?php

declare(strict_types=1);

namespace Slendie\Container;

use ArrayAccess;
use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Container de injecção de dependências com autowiring por reflection.
 *
 * Classes concretas resolvem-se sem binding; interfaces e abstractos exigem bind().
 *
 * @implements ArrayAccess<string, mixed>
 */
class Container implements ArrayAccess
{
    protected static ?Container $instance = null;

    /** @var array<string, array{concrete: Closure|string, shared: bool}> */
    protected array $bindings = [];

    /** @var array<string, mixed> */
    protected array $instances = [];

    /** @var array<string, string> alias => abstract */
    protected array $aliases = [];

    /** @var array<string, list<Closure>> */
    protected array $extenders = [];

    /** @var array<string, list<Closure>> */
    protected array $resolvingCallbacks = [];

    /** @var array<string, true> */
    protected array $resolved = [];

    /** @var list<string> pilha de abstractos em construção (detecção de ciclos) */
    protected array $buildStack = [];

    // -------------------------------------------------------------------------
    // Instância global
    // -------------------------------------------------------------------------

    public static function getInstance(): Container
    {
        return static::$instance ??= new self();
    }

    public static function setInstance(?Container $container): ?Container
    {
        return static::$instance = $container;
    }

    // -------------------------------------------------------------------------
    // Registo
    // -------------------------------------------------------------------------

    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $abstract = $this->normalize($abstract);
        unset($this->instances[$abstract], $this->aliases[$abstract]);

        $concrete ??= $abstract;

        $this->bindings[$abstract] = ['concrete' => $concrete, 'shared' => $shared];
    }

    public function bindIf(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        if (! $this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function singletonIf(string $abstract, Closure|string|null $concrete = null): void
    {
        if (! $this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }

    /**
     * @template T
     * @param  T  $instance
     * @return T
     */
    public function instance(string $abstract, mixed $instance): mixed
    {
        $abstract = $this->normalize($abstract);
        unset($this->aliases[$abstract]);

        $this->instances[$abstract] = $instance;
        $this->resolved[$abstract] = true;

        return $instance;
    }

    public function alias(string $abstract, string $alias): void
    {
        if ($alias === $abstract) {
            throw new BindingResolutionException("[{$abstract}] não pode ser alias de si próprio.");
        }

        $this->aliases[$alias] = $this->normalize($abstract);
    }

    /**
     * Decora o serviço depois de resolvido. O closure recebe (instância, container).
     */
    public function extend(string $abstract, Closure $closure): void
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);

            return;
        }

        $this->extenders[$abstract][] = $closure;
    }

    /**
     * Callback chamado sempre que $abstract é resolvido. Recebe (instância, container).
     */
    public function resolving(string $abstract, Closure $callback): void
    {
        $this->resolvingCallbacks[$this->getAlias($abstract)][] = $callback;
    }

    // -------------------------------------------------------------------------
    // Consulta
    // -------------------------------------------------------------------------

    public function bound(string $abstract): bool
    {
        $abstract = $this->normalize($abstract);

        return isset($this->bindings[$abstract])
            || isset($this->instances[$abstract])
            || isset($this->aliases[$abstract]);
    }

    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    public function resolved(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);

        return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
    }

    public function isShared(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);

        return isset($this->instances[$abstract])
            || (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared']);
    }

    public function getAlias(string $abstract): string
    {
        $abstract = $this->normalize($abstract);

        $seen = [];
        while (isset($this->aliases[$abstract])) {
            if (isset($seen[$abstract])) {
                throw new BindingResolutionException("Ciclo de aliases detectado em [{$abstract}].");
            }
            $seen[$abstract] = true;
            $abstract = $this->aliases[$abstract];
        }

        return $abstract;
    }

    // -------------------------------------------------------------------------
    // Resolução
    // -------------------------------------------------------------------------

    /**
     * @template T of object
     * @param  class-string<T>|string  $abstract
     * @param  array<string, mixed>  $parameters  parâmetros por nome, com prioridade sobre o autowiring
     * @return ($abstract is class-string<T> ? T : mixed)
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract]) && $parameters === []) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;

        $delegated = ! ($concrete instanceof Closure) && $concrete !== $abstract;

        $object = $delegated
            ? $this->make($concrete, $parameters)
            : $this->build($concrete, $parameters, $abstract);

        foreach ($this->extenders[$abstract] ?? [] as $extender) {
            $object = $extender($object, $this);
        }

        if ($this->isShared($abstract) && $parameters === []) {
            $this->instances[$abstract] = $object;
        }

        // Quando delegámos para outro abstract, os callbacks por tipo já correram lá.
        $this->fireResolvingCallbacks($abstract, $object, includeTypes: ! $delegated);
        $this->resolved[$abstract] = true;

        return $object;
    }

    /**
     * Chama um callable resolvendo as dependências pelo container.
     *
     * Aceita Closure, [objecto|classe, 'método'], 'Classe@método', 'Classe::método' ou classe invocável.
     *
     * @param  callable|array{0: object|string, 1?: string}|string  $callback
     * @param  array<string, mixed>  $parameters
     */
    public function call(callable|array|string $callback, array $parameters = []): mixed
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            $callback = explode('@', $callback, 2);
        } elseif (is_string($callback) && str_contains($callback, '::')) {
            $callback = explode('::', $callback, 2);
        } elseif (is_string($callback) && class_exists($callback) && method_exists($callback, '__invoke')) {
            $callback = [$callback, '__invoke'];
        }

        if (is_array($callback)) {
            [$target, $method] = [$callback[0], $callback[1] ?? '__invoke'];

            if (is_string($target)) {
                $target = $this->make($target);
            }

            if (! is_object($target) || ! is_string($method)) {
                throw new BindingResolutionException('Callable inválido passado a Container::call().');
            }

            $reflection = new ReflectionMethod($target, $method);
            $arguments = $this->resolveDependencies($reflection, $parameters);

            return $reflection->invokeArgs($reflection->isStatic() ? null : $target, $arguments);
        }

        if (! is_callable($callback)) {
            throw new BindingResolutionException('Callable inválido passado a Container::call().');
        }

        $closure = $callback instanceof Closure ? $callback : Closure::fromCallable($callback);
        $reflection = new ReflectionFunction($closure);

        return $reflection->invokeArgs($this->resolveDependencies($reflection, $parameters));
    }

    /**
     * Instancia uma classe concreta ou executa um closure de fábrica.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function build(Closure|string $concrete, array $parameters, string $abstract): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        if (in_array($concrete, $this->buildStack, true)) {
            $chain = implode(' -> ', [...$this->buildStack, $concrete]);
            throw new CircularDependencyException("Dependência circular detectada: {$chain}");
        }

        if (! class_exists($concrete) && ! interface_exists($concrete)) {
            throw new BindingResolutionException("A classe [{$concrete}] não existe.");
        }

        $reflector = new ReflectionClass($concrete);

        if (! $reflector->isInstantiable()) {
            $context = $this->buildStack === [] ? '' : ' (necessária em '.end($this->buildStack).')';
            throw new BindingResolutionException("[{$concrete}] não é instanciável: faça bind() de uma implementação{$context}.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $this->buildStack[] = $concrete;

        try {
            $arguments = $this->resolveDependencies($constructor, $parameters);
        } finally {
            array_pop($this->buildStack);
        }

        return $reflector->newInstanceArgs($arguments);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<mixed>
     */
    protected function resolveDependencies(ReflectionFunctionAbstract $function, array $parameters): array
    {
        $results = [];

        foreach ($function->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $results[] = $parameters[$name];

                continue;
            }

            $class = $this->getParameterClassName($parameter);

            if ($class !== null) {
                // Permite passar a instância por tipo em vez de por nome.
                foreach ($parameters as $value) {
                    if ($value instanceof $class) {
                        $results[] = $value;

                        continue 2;
                    }
                }

                $results[] = $this->resolveClass($parameter, $class);

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $results[] = $parameter->getDefaultValue();

                continue;
            }

            if ($parameter->isVariadic()) {
                continue;
            }

            if ($parameter->allowsNull()) {
                $results[] = null;

                continue;
            }

            $owner = $function instanceof ReflectionMethod
                ? $function->getDeclaringClass()->getName().'::'.$function->getName()
                : ($function->getName() === '{closure}' ? 'closure' : $function->getName());

            throw new BindingResolutionException(
                "Impossível resolver o parâmetro \${$name} em {$owner}: sem tipo de classe, sem default e não foi passado.",
            );
        }

        return $results;
    }

    protected function resolveClass(ReflectionParameter $parameter, string $class): mixed
    {
        try {
            return $this->make($class);
        } catch (BindingResolutionException $e) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($parameter->allowsNull()) {
                return null;
            }

            throw $e;
        }
    }

    protected function getParameterClassName(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();
        $class = $parameter->getDeclaringClass();

        if ($class !== null) {
            if ($name === 'self') {
                return $class->getName();
            }

            if ($name === 'parent' && $class->getParentClass() !== false) {
                return $class->getParentClass()->getName();
            }
        }

        return $name;
    }

    protected function fireResolvingCallbacks(string $abstract, mixed $object, bool $includeTypes = true): void
    {
        foreach ($this->resolvingCallbacks[$abstract] ?? [] as $callback) {
            $callback($object, $this);
        }

        if (! $includeTypes || ! is_object($object)) {
            return;
        }

        foreach ($this->resolvingCallbacks as $type => $callbacks) {
            if ($type !== $abstract && (class_exists($type) || interface_exists($type)) && $object instanceof $type) {
                foreach ($callbacks as $callback) {
                    $callback($object, $this);
                }
            }
        }
    }

    protected function normalize(string $abstract): string
    {
        return ltrim($abstract, '\\');
    }

    // -------------------------------------------------------------------------
    // Manutenção
    // -------------------------------------------------------------------------

    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$this->getAlias($abstract)]);
    }

    public function forgetInstances(): void
    {
        $this->instances = [];
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->extenders = [];
        $this->resolvingCallbacks = [];
        $this->resolved = [];
        $this->buildStack = [];
    }

    /** @return array<string, array{concrete: Closure|string, shared: bool}> */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    public function offsetExists(mixed $offset): bool
    {
        return $this->bound((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->make((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->bind((string) $offset, $value instanceof Closure ? $value : fn () => $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $key = $this->normalize((string) $offset);
        unset($this->bindings[$key], $this->instances[$key], $this->resolved[$key]);
    }
}
