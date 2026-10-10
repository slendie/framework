<?php

declare(strict_types=1);

use Slendie\Container\Container;
use Slendie\Exceptions\HttpException;
use Slendie\Exceptions\NotFoundHttpException;
use Slendie\Exceptions\TokenMismatchException;
use Slendie\Foundation\Application;
use Slendie\Http\Cookie;
use Slendie\Http\RedirectResponse;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Http\ResponseFactory;
use Slendie\Routing\Redirector;
use Slendie\Routing\UrlGenerator;
use Slendie\Session\Session;
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

// -----------------------------------------------------------------------------
// HTTP (peça core-04-02)
// -----------------------------------------------------------------------------

if (! function_exists('request')) {
    /**
     * Sem argumentos devolve o Request actual; com chave devolve esse valor do input.
     *
     * @param  string|null  $key
     * @return ($key is null ? Request : mixed)
     */
    function request(?string $key = null, mixed $default = null): mixed
    {
        /** @var Request $request */
        $request = app('request');

        return $key === null ? $request : $request->input($key, $default);
    }
}

if (! function_exists('response')) {
    /**
     * Sem argumentos devolve a ResponseFactory (json(), redirect(), view(), noContent(), download()).
     *
     * @param  array<string, string>  $headers
     * @return ($content is null ? ResponseFactory : Response)
     */
    function response(?string $content = null, int $status = 200, array $headers = []): ResponseFactory|Response
    {
        $factory = app()->make(ResponseFactory::class);

        return $content === null ? $factory : $factory->make($content, $status, $headers);
    }
}

if (! function_exists('redirect')) {
    /**
     * Sem argumentos devolve o Redirector (route(), back(), to(), away()).
     *
     * @return ($to is null ? Redirector : RedirectResponse)
     */
    function redirect(?string $to = null, int $status = 302): Redirector|RedirectResponse
    {
        $redirector = app()->make(Redirector::class);

        return $to === null ? $redirector : $redirector->to($to, $status);
    }
}

if (! function_exists('back')) {
    function back(int $status = 302, string|false $fallback = false): RedirectResponse
    {
        return app()->make(Redirector::class)->back($status, $fallback);
    }
}

if (! function_exists('route')) {
    /**
     * @param  array<array-key, mixed>  $parameters
     */
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return app()->make(UrlGenerator::class)->route($name, $parameters, $absolute);
    }
}

if (! function_exists('url')) {
    /**
     * Sem argumentos devolve o UrlGenerator; com caminho devolve o URL absoluto.
     *
     * @param  list<scalar>  $extra
     * @return ($path is null ? UrlGenerator : string)
     */
    function url(?string $path = null, array $extra = [], ?bool $secure = null): UrlGenerator|string
    {
        $generator = app()->make(UrlGenerator::class);

        return $path === null ? $generator : $generator->to($path, $extra, $secure);
    }
}

if (! function_exists('asset')) {
    function asset(string $path, ?bool $secure = null): string
    {
        return app()->make(UrlGenerator::class)->asset($path, $secure);
    }
}

if (! function_exists('session')) {
    /**
     * Sem argumentos devolve a Session; com string lê; com array escreve.
     *
     * @param  string|array<string, mixed>|null  $key
     * @return ($key is null ? Session : mixed)
     */
    function session(string|array|null $key = null, mixed $default = null): mixed
    {
        $session = request()->session();

        if ($key === null) {
            return $session;
        }

        if (is_array($key)) {
            $session->put($key);

            return null;
        }

        return $session->get($key, $default);
    }
}

if (! function_exists('old')) {
    /** Valor submetido no pedido anterior (para repopular formulários). */
    function old(?string $key = null, mixed $default = null): mixed
    {
        $request = app('request');

        if (! $request instanceof Request || ! $request->hasSession()) {
            return $default;
        }

        return $request->session()->getOldInput($key, $default);
    }
}

if (! function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return request()->session()->token();
    }
}

if (! function_exists('csrf_field')) {
    /** Campo hidden `_token` para formulários. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="'.e(csrf_token()).'" autocomplete="off">';
    }
}

if (! function_exists('method_field')) {
    /** Campo hidden `_method` (PUT, PATCH, DELETE) para formulários HTML. */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="'.e(strtoupper($method)).'">';
    }
}

if (! function_exists('abort')) {
    /**
     * Interrompe o pedido com um erro HTTP.
     *
     * @param  array<string, string>  $headers
     *
     * @throws HttpException
     */
    function abort(int $code, string $message = '', array $headers = []): never
    {
        throw match ($code) {
            404 => new NotFoundHttpException($message, null, $headers),
            419 => (new TokenMismatchException($message !== '' ? $message : 'CSRF token mismatch.'))->setHeaders($headers),
            default => new HttpException($code, $message, null, $headers),
        };
    }
}

if (! function_exists('abort_if')) {
    /**
     * @param  array<string, string>  $headers
     */
    function abort_if(mixed $condition, int $code, string $message = '', array $headers = []): void
    {
        if ($condition) {
            abort($code, $message, $headers);
        }
    }
}

if (! function_exists('abort_unless')) {
    /**
     * @param  array<string, string>  $headers
     */
    function abort_unless(mixed $condition, int $code, string $message = '', array $headers = []): void
    {
        if (! $condition) {
            abort($code, $message, $headers);
        }
    }
}

if (! function_exists('cookie')) {
    /**
     * Cria uma Cookie (para `$response->cookie($cookie)`). $minutes = 0 → cookie de sessão.
     */
    function cookie(string $name, string $value = '', int $minutes = 0, string $path = '/', ?string $domain = null, ?bool $secure = null, bool $httpOnly = true, string $sameSite = 'lax'): Cookie
    {
        $request = app()->bound('request') ? app('request') : null;

        return Cookie::minutes(
            $name,
            $value,
            $minutes,
            $path,
            $domain,
            $secure ?? ($request instanceof Request && $request->isSecure()),
            $httpOnly,
            $sameSite,
        );
    }
}
