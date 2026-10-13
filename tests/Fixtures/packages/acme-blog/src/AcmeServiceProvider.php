<?php

declare(strict_types=1);

namespace Acme\Blog;

use Slendie\Foundation\ServiceProvider;

/** Usa todas as capacidades que um package pode ter. */
final class AcmeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/acme.php', 'acme');
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);

        $this->loadRoutesFrom($root.'/routes/web.php');
        $this->loadViewsFrom($root.'/resources/views', 'acme');
        $this->loadMigrationsFrom($root.'/database/migrations');
        $this->loadTranslationsFrom($root.'/lang', 'acme');

        $this->callAfterResolving('blade.compiler', static function ($blade): void {
            $blade->directive('acmeName', static fn (string $expression): string => "<?php echo strtoupper({$expression}); ?>");
            $blade->if('acme', static fn (): bool => true);
            $blade->if('acmerole', static fn (string $role): bool => $role === 'editor');
        });

        $this->router()->aliasMiddleware('acme', AcmeMiddleware::class);
        $this->router()->pushMiddlewareToGroup('web', AcmeGroupMiddleware::class);

        $this->commands([HelloCommand::class]);

        $this->publishes([$root.'/config/acme.php' => $this->app->configPath('acme.php')], 'acme-config');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/acme')], 'acme-views');
        $this->publishesMigrations([$root.'/database/migrations' => $this->app->databasePath('migrations')], 'acme-migrations');
    }
}
