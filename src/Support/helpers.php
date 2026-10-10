<?php

declare(strict_types=1);

use Slendie\Container\Container;
use Slendie\Foundation\Application;
use Slendie\Support\Config;
use Slendie\Support\Env;
use Slendie\Support\Str;

if (! function_exists('app')) {
    /**
     * Devolve a aplicação ou resolve um serviço do container.
     *
     * @template T of object
     * @param  class-string<T>|string|null  $abstract
     * @param  array<string, mixed>  $parameters
     * @return ($abstract is null ? Application : ($abstract is class-string<T> ? T : mixed))
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $container = Container::getInstance();

        if ($abstract === null) {
            if (! $container instanceof Application) {
                throw new RuntimeException('Não existe nenhuma Application: cria uma com new Application($basePath).');
            }

            return $container;
        }

        return $container->make($abstract, $parameters);
    }
}

if (! function_exists('env')) {
    /**
     * Lê uma variável de ambiente. Usar apenas em config/*.php.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = Env::get($key, $default);

        return $value instanceof Closure ? $value() : $value;
    }
}

if (! function_exists('config')) {
    /**
     * Lê (string) ou escreve (array) configuração com dot notation.
     *
     * @param  string|array<string, mixed>|null  $key
     */
    function config(string|array|null $key = null, mixed $default = null): mixed
    {
        /** @var Config $config */
        $config = app(Config::class);

        if ($key === null) {
            return $config;
        }

        if (is_array($key)) {
            $config->set($key);

            return null;
        }

        return $config->get($key, $default);
    }
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return app()->appPath($path);
    }
}

if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}

if (! function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return app()->publicPath($path);
    }
}

if (! function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return app()->resourcePath($path);
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (! function_exists('lang_path')) {
    function lang_path(string $path = ''): string
    {
        return app()->langPath($path);
    }
}

if (! function_exists('value')) {
    /**
     * Devolve o valor, executando-o se for Closure.
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (! function_exists('tap')) {
    /**
     * @template T
     * @param  T  $value
     * @param  (callable(T): mixed)|null  $callback
     * @return T
     */
    function tap(mixed $value, ?callable $callback = null): mixed
    {
        if ($callback !== null) {
            $callback($value);
        }

        return $value;
    }
}

if (! function_exists('e')) {
    /**
     * Escapa HTML (usado por {{ }} no Blade).
     */
    function e(mixed $value, bool $doubleEncode = true): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (! is_scalar($value) && ! $value instanceof Stringable) {
            throw new InvalidArgumentException('e() só aceita escalares, Stringable, BackedEnum ou null; recebeu '.get_debug_type($value).'.');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }
}

if (! function_exists('class_basename')) {
    function class_basename(object|string $class): string
    {
        return Str::classBasename($class);
    }
}

if (! function_exists('dump')) {
    /**
     * @return array<array-key, mixed>
     */
    function dump(mixed ...$vars): array
    {
        $cli = in_array(PHP_SAPI, ['cli', 'phpdbg'], true);

        foreach ($vars as $var) {
            if ($cli) {
                var_dump($var);
            } else {
                echo '<pre style="background:#1e1e2e;color:#cdd6f4;padding:1rem;border-radius:.5rem;overflow:auto">';
                echo htmlspecialchars(print_r($var, true), ENT_QUOTES, 'UTF-8');
                echo '</pre>';
            }
        }

        return $vars;
    }
}

if (! function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        if (! in_array(PHP_SAPI, ['cli', 'phpdbg'], true) && ! headers_sent()) {
            http_response_code(500);
        }

        dump(...$vars);

        exit(1);
    }
}

if (! function_exists('blank')) {
    function blank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        if ($value instanceof Stringable) {
            return trim((string) $value) === '';
        }

        return empty($value);
    }
}

if (! function_exists('filled')) {
    function filled(mixed $value): bool
    {
        return ! blank($value);
    }
}

if (! function_exists('retry')) {
    /**
     * @template T
     * @param  callable(int): T  $callback
     * @return T
     */
    function retry(int $times, callable $callback, int $sleepMilliseconds = 0, ?callable $when = null): mixed
    {
        $attempts = 0;

        beginning:
        $attempts++;
        $times--;

        try {
            return $callback($attempts);
        } catch (Throwable $e) {
            if ($times < 1 || ($when !== null && ! $when($e))) {
                throw $e;
            }

            if ($sleepMilliseconds > 0) {
                usleep($sleepMilliseconds * 1000);
            }

            goto beginning;
        }
    }
}
