<?php

declare(strict_types=1);

namespace Slendie\Foundation\Bootstrap;

use LogicException;
use Slendie\Foundation\Application;
use Slendie\Foundation\PackageDiscovery;
use Slendie\Foundation\ServiceProvider;
use Slendie\Support\Config;

/**
 * Regista os providers da framework, os dos packages descobertos (vendor), os de bootstrap/providers.php
 * e os de config('app.providers').
 */
final class RegisterProviders
{
    public function bootstrap(Application $app): void
    {
        $manifest = (new PackageDiscovery())->load($app->basePath('vendor'), $app->bootstrapPath('cache/packages.php'));

        foreach ($manifest['aliases'] as $alias => $class) {
            if (! class_exists($alias, false) && class_exists($class)) {
                class_alias($class, $alias);
            }
        }

        foreach ($this->collect($app, $manifest['providers']) as $provider) {
            $app->register($provider);
        }
    }

    /**
     * @param  list<string>  $discovered
     * @return list<class-string<ServiceProvider>>
     */
    private function collect(Application $app, array $discovered): array
    {
        /** @var list<class-string<ServiceProvider>> $providers */
        $providers = [...$app->getFrameworkProviders(), ...$discovered];

        $file = $app->bootstrapPath('providers.php');

        if (is_file($file)) {
            $fromFile = require $file;

            if (! is_array($fromFile)) {
                throw new LogicException("[{$file}] deve devolver um array de providers.");
            }

            /** @var list<class-string<ServiceProvider>> $fromFile */
            $providers = [...$providers, ...$fromFile];
        }

        if ($app->bound(Config::class)) {
            $fromConfig = $app->make(Config::class)->get('app.providers', []);

            if (is_array($fromConfig)) {
                /** @var list<class-string<ServiceProvider>> $fromConfig */
                $providers = [...$providers, ...$fromConfig];
            }
        }

        if ($app->bound('providers.extra')) {
            $extra = $app->make('providers.extra');

            if (is_array($extra)) {
                /** @var list<class-string<ServiceProvider>> $extra */
                $providers = [...$providers, ...$extra];
            }
        }

        return array_values(array_unique($providers));
    }
}
