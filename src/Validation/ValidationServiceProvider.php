<?php

declare(strict_types=1);

namespace Slendie\Validation;

use Slendie\Container\Container;
use Slendie\Database\DatabaseManager;
use Slendie\Foundation\Application;
use Slendie\Foundation\ServiceProvider;
use Slendie\Http\FormRequest;
use Slendie\Http\Request;
use Slendie\Support\Config;

/**
 * Regista o tradutor (`translator`), a fábrica de validadores (`validator`) e a resolução de FormRequest.
 */
class ValidationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Translator::class, function (Application $app): Translator {
            /** @var Config $config */
            $config = $app->make(Config::class);
            $locale = $config->get('app.locale', 'pt');
            $fallback = $config->get('app.fallback_locale', 'en');

            return new Translator(
                dirname(__DIR__, 2).'/lang',
                $app->langPath(),
                is_string($locale) && $locale !== '' ? $locale : 'pt',
                is_string($fallback) && $fallback !== '' ? $fallback : 'en',
            );
        });
        $this->app->alias(Translator::class, 'translator');

        $this->app->singleton(Factory::class, function (Application $app): Factory {
            $factory = new Factory($app->make(Translator::class));

            $factory->setPresenceVerifierResolver(
                static fn (): PresenceVerifier => new PresenceVerifier($app->make(DatabaseManager::class)),
            );

            return $factory;
        });
        $this->app->alias(Factory::class, 'validator');

        // Quando um FormRequest é resolvido (injecção num controller), preenche-o com o pedido actual e valida-o.
        $this->app->resolving(FormRequest::class, static function (FormRequest $form, Container $container): void {
            $request = $container->bound('request') ? $container->make('request') : null;

            if ($request instanceof Request) {
                $form->setContainer($container)->initializeFrom($request)->validateResolved();
            }
        });
    }

    public function boot(): void
    {
        $this->publishes([dirname(__DIR__, 2).'/lang' => $this->app->langPath()], 'slendie-lang');
    }
}
