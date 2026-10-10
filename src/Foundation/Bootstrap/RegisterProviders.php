<?php

declare(strict_types=1);

namespace Slendie\Foundation\Bootstrap;

use LogicException;
use Slendie\Foundation\Application;
use Slendie\Foundation\ServiceProvider;
use Slendie\Support\Config;

/**
 * Regista os providers da framework, os de bootstrap/providers.php e os de config('app.providers').
 * A descoberta de packages (vendor) é acrescentada na peça 09.
 */
final class RegisterProviders
{
    public function bootstrap(Application $app): void
    {
        foreach ($this->collect($app) as $provider) {
            $app->register($provider);
        }
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    private function collect(Application $app): array
    {
        $providers = $app->getFrameworkProviders();

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

        // TODO(core-04-09): juntar PackageDiscovery::discover(vendor).

        return array_values(array_unique($providers));
    }
}
