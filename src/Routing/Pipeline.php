<?php

declare(strict_types=1);

namespace Slendie\Routing;

use Closure;
use InvalidArgumentException;
use Slendie\Container\Container;

/**
 * Pipeline "cebola": o passable atravessa os pipes e chega ao destino.
 *
 * Cada pipe pode ser uma Closure `fn ($passable, $next)`, um objecto com `handle($passable, $next, ...$params)`
 * ou uma string `Classe` / `Classe:param1,param2` resolvida pelo container.
 */
class Pipeline
{
    protected mixed $passable = null;

    /** @var list<Closure|object|string> */
    protected array $pipes = [];

    protected string $method = 'handle';

    public function __construct(protected ?Container $container = null)
    {
    }

    public function send(mixed $passable): static
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * @param  list<Closure|object|string>|Closure|object|string  $pipes
     */
    public function through(array|object|string $pipes): static
    {
        $this->pipes = is_array($pipes) ? array_values($pipes) : [$pipes];

        return $this;
    }

    public function via(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            static fn (mixed $passable): mixed => $destination($passable),
        );

        return $pipeline($this->passable);
    }

    protected function carry(): Closure
    {
        return fn (Closure $stack, object|string $pipe): Closure => function (mixed $passable) use ($stack, $pipe): mixed {
            if ($pipe instanceof Closure) {
                return $pipe($passable, $stack);
            }

            $parameters = [];

            if (is_string($pipe)) {
                [$name, $parameters] = $this->parsePipeString($pipe);

                $pipe = ($this->container ?? Container::getInstance())->make($name);
            }

            if (! is_object($pipe) || ! method_exists($pipe, $this->method)) {
                throw new InvalidArgumentException('O pipe tem de ter o método '.$this->method.'().');
            }

            return $pipe->{$this->method}($passable, $stack, ...$parameters);
        };
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    protected function parsePipeString(string $pipe): array
    {
        [$name, $parameters] = array_pad(explode(':', $pipe, 2), 2, '');

        return [$name, $parameters === '' ? [] : explode(',', $parameters)];
    }
}
