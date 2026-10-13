<?php

declare(strict_types=1);

namespace Slendie\Testing\Concerns;

use Closure;
use PHPUnit\Framework\Assert;
use Throwable;

trait InteractsWithExceptionHandling
{
    /**
     * As excepções deixam de ser convertidas em páginas de erro: o teste vê a excepção original.
     */
    protected function withoutExceptionHandling(): static
    {
        $this->app->instance('testing.exceptions.throw', true);

        return $this;
    }

    protected function withExceptionHandling(): static
    {
        $this->app->forgetInstance('testing.exceptions.throw');

        return $this;
    }

    /**
     * Garante que o callback lança a excepção indicada (e, opcionalmente, com a mensagem indicada).
     *
     * @param  class-string<Throwable>  $class
     */
    protected function assertThrows(Closure $callback, string $class = Throwable::class, ?string $message = null): static
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Assert::assertInstanceOf($class, $e, "Expected exception [{$class}] but [".$e::class.'] was thrown.');

            if ($message !== null) {
                Assert::assertStringContainsString($message, $e->getMessage(), 'The exception message does not contain the expected text.');
            }

            return $this;
        }

        Assert::fail("Failed asserting that exception of type [{$class}] was thrown.");
    }
}
