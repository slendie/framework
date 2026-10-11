<?php

declare(strict_types=1);

namespace Slendie\View;

use Slendie\Foundation\Application;
use Slendie\Foundation\ServiceProvider;
use Slendie\Support\Config;
use Slendie\View\Compiler\BladeCompiler;

/**
 * Regista Finder, BladeCompiler, Engine e Factory (`view`).
 *
 * Configuração opcional em `config/view.php`: `paths` (por omissão `resources/views`) e `compiled`
 * (por omissão `storage/framework/views`).
 */
class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Finder::class, function (Application $app): Finder {
            $paths = $this->config('view.paths');

            $paths = is_array($paths) && $paths !== []
                ? array_values(array_filter($paths, is_string(...)))
                : [$app->resourcePath('views')];

            return new Finder($paths, [
                // O projecto pode sobrepor qualquer página de erro da framework em resources/views/errors.
                'errors' => [$app->resourcePath('views/errors'), dirname(__DIR__, 2).'/resources/views/errors'],
            ]);
        });

        $this->app->singleton(BladeCompiler::class, function (Application $app): BladeCompiler {
            $compiled = $this->config('view.compiled');

            return new BladeCompiler(
                is_string($compiled) && $compiled !== '' ? $compiled : $app->storagePath('framework/views'),
                $app->isDebug(),
            );
        });
        $this->app->alias(BladeCompiler::class, 'blade.compiler');

        $this->app->singleton(Engine::class, fn (Application $app): Engine => new Engine($app->make(BladeCompiler::class)));

        $this->app->singleton(Factory::class, function (Application $app): Factory {
            // `$errors` existe sempre nas views; o ShareErrorsFromSession substitui-o pelo do pedido.
            return new Factory(
                $app->make(Finder::class),
                $app->make(Engine::class),
                ['errors' => new ViewErrorBag()],
                $app,
            );
        });
        $this->app->alias(Factory::class, 'view');
    }

    protected function config(string $key): mixed
    {
        return $this->app->bound(Config::class) ? $this->app->make(Config::class)->get($key) : null;
    }
}
