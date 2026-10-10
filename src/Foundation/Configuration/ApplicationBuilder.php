<?php

declare(strict_types=1);

namespace Slendie\Foundation\Configuration;

use Closure;
use Slendie\Foundation\Application;
use Slendie\Foundation\Exceptions\Handler;
use Slendie\Http\Kernel;

/**
 * Configuração fluente da aplicação (estilo Laravel 11) usada em bootstrap/app.php.
 */
class ApplicationBuilder
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Ficheiros de rotas. Por defeito: routes/web.php e routes/api.php.
     */
    public function withRouting(?string $web = null, ?string $api = null): static
    {
        $this->app->instance('routes.files', array_filter(['web' => $web, 'api' => $api], static fn (?string $file): bool => $file !== null));

        return $this;
    }

    /**
     * Personaliza o Kernel HTTP: `fn (Kernel $kernel) => $kernel->pushMiddleware(...)`.
     *
     * @param  Closure(Kernel): mixed  $callback
     */
    public function withMiddleware(Closure $callback): static
    {
        $this->app->resolving(Kernel::class, static function (Kernel $kernel) use ($callback): void {
            $callback($kernel);
        });

        return $this;
    }

    /**
     * Personaliza o Handler de excepções: `fn (Handler $handler) => $handler->dontReport([...])`.
     *
     * @param  Closure(Handler): mixed  $callback
     */
    public function withExceptions(Closure $callback): static
    {
        $this->app->singletonIf(Handler::class);
        $this->app->resolving(Handler::class, static function (Handler $handler) use ($callback): void {
            $callback($handler);
        });

        return $this;
    }

    /**
     * Providers adicionais, registados depois dos da framework.
     *
     * @param  list<class-string<\Slendie\Foundation\ServiceProvider>>  $providers
     */
    public function withProviders(array $providers): static
    {
        $extra = $this->app->bound('providers.extra') ? $this->app->make('providers.extra') : [];
        $extra = is_array($extra) ? $extra : [];

        $this->app->instance('providers.extra', [...$extra, ...$providers]);

        return $this;
    }

    public function create(): Application
    {
        return $this->app;
    }
}
