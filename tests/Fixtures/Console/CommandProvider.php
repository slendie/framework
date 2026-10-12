<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Console;

use Slendie\Foundation\ServiceProvider;

/** Provider de um package: regista comandos e ficheiros publicáveis. */
final class CommandProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\Slendie\Tests\Fixtures\GreeterContract::class, \Slendie\Tests\Fixtures\Greeter::class);
    }

    public function boot(): void
    {
        $this->commands([GreetCommand::class, AskCommand::class]);

        $this->publishes([
            __DIR__.'/publish/config.php' => $this->app->basePath('config/blog.php'),
            __DIR__.'/publish/views' => $this->app->basePath('resources/views/vendor/blog'),
        ], 'blog-config');
    }
}
