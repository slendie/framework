<?php

declare(strict_types=1);

namespace Slendie\Console;

use Closure;

/**
 * Comando definido por uma closure (`routes/console.php`). Dentro da closure `$this` é o comando,
 * e os argumentos/opções chegam como parâmetros com o mesmo nome (mais o resto por injecção).
 */
final class ClosureCommand extends Command
{
    public function __construct(string $signature, private readonly Closure $callback)
    {
        $this->signature = $signature;
    }

    /** Descrição mostrada em `list`. */
    public function purpose(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function describe(string $description): static
    {
        return $this->purpose($description);
    }

    protected function execute(): mixed
    {
        $callback = Closure::bind($this->callback, $this, static::class) ?? $this->callback;

        return $this->container()->call($callback, [...$this->arguments(), ...$this->options()]);
    }
}
