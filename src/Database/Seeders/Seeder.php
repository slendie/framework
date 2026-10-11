<?php

declare(strict_types=1);

namespace Slendie\Database\Seeders;

use InvalidArgumentException;
use LogicException;
use Slendie\Container\Container;

/**
 * Base dos seeders. `run()` pode pedir dependências por injecção (type-hints com valor por omissão ou
 * resolvíveis pelo container); `call()` encadeia outros seeders:
 *
 *     public function run(): void { $this->call([LeadSeeder::class]); }
 */
abstract class Seeder
{
    public function __construct(protected ?Container $container = null)
    {
    }

    public function setContainer(Container $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Corre outros seeders (por esta ordem) através do container.
     *
     * @param  class-string<Seeder>|list<class-string<Seeder>>  $classes
     */
    public function call(string|array $classes): static
    {
        foreach ((array) $classes as $class) {
            $this->resolve($class)->__invoke();
        }

        return $this;
    }

    /**
     * Como call(), mas com parâmetros nomeados para o `run()`.
     *
     * @param  class-string<Seeder>  $class
     * @param  array<string, mixed>  $parameters
     */
    public function callWith(string $class, array $parameters = []): static
    {
        $this->resolve($class)->__invoke($parameters);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __invoke(array $parameters = []): mixed
    {
        if (! method_exists($this, 'run')) {
            throw new LogicException('O seeder ['.static::class.'] tem de definir o método run().');
        }

        return $this->container()->call([$this, 'run'], $parameters);
    }

    /**
     * @param  class-string<Seeder>  $class
     */
    protected function resolve(string $class): Seeder
    {
        if (! is_subclass_of($class, self::class)) {
            throw new InvalidArgumentException("[{$class}] não é um seeder (tem de estender ".self::class.').');
        }

        return $this->container()->make($class)->setContainer($this->container());
    }

    protected function container(): Container
    {
        return $this->container ??= Container::getInstance();
    }
}
