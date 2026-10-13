<?php

declare(strict_types=1);

namespace Slendie\Testing\Concerns;

use Slendie\Support\Facade;

/**
 * Substituir serviços do container por dobles de teste (classes anónimas): não há Mockery nesta versão.
 */
trait InteractsWithContainer
{
    /**
     * Troca um serviço (e a instância já resolvida da facade, se existir) por outro.
     *
     *     $this->swap(Mailer::class, new class implements Mailer { ... });
     *
     * @template T of object
     *
     * @param  T  $instance
     * @return T
     */
    protected function swap(string $abstract, object $instance): object
    {
        return $this->instance($abstract, $instance);
    }

    /**
     * Regista uma instância no container.
     *
     * @template T of object
     *
     * @param  T  $instance
     * @return T
     */
    protected function instance(string $abstract, object $instance): object
    {
        $this->app->instance($abstract, $instance);
        Facade::clearResolvedInstance($abstract);

        return $instance;
    }
}
