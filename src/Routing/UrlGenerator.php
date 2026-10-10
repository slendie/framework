<?php

declare(strict_types=1);

namespace Slendie\Routing;

use Slendie\Exceptions\UrlGenerationException;
use Slendie\Foundation\Application;
use Slendie\Http\Request;
use Slendie\Support\Config;

class UrlGenerator
{
    public function __construct(protected Application $app, protected RouteCollection $routes)
    {
    }

    /**
     * URL absoluto para um caminho; $extra acrescenta segmentos ao caminho.
     *
     * @param  list<scalar>  $extra
     */
    public function to(string $path, array $extra = [], ?bool $secure = null): string
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $root = $this->root();

        if ($secure === true) {
            $root = (string) preg_replace('#^http://#', 'https://', $root);
        } elseif ($secure === false) {
            $root = (string) preg_replace('#^https://#', 'http://', $root);
        }

        $segments = array_map(static fn (mixed $s): string => rawurlencode((string) $s), $extra);
        $tail = trim(implode('/', [trim($path, '/'), ...$segments]), '/');

        return $root.($tail === '' ? '' : '/'.$tail);
    }

    /**
     * URL de uma rota nomeada. Parâmetros nomeados preenchem {param}; os restantes vão para a query string.
     * Também aceita lista posicional.
     *
     * @param  array<array-key, mixed>  $parameters
     *
     * @throws UrlGenerationException
     */
    public function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $route = $this->routes->getByName($name)
            ?? throw new UrlGenerationException("Route [{$name}] not defined.");

        $names = $route->parameterNames();
        $optional = $route->compile()->optionalNames;

        // Parâmetros posicionais ocupam, por ordem, os nomes ainda por preencher.
        $named = [];
        $positional = [];

        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                $positional[] = $value;
            } else {
                $named[$key] = $value;
            }
        }

        foreach ($names as $param) {
            if (! array_key_exists($param, $named) && $positional !== []) {
                $named[$param] = array_shift($positional);
            }
        }

        $uri = $route->uri();

        foreach ($names as $param) {
            if (array_key_exists($param, $named) && $named[$param] !== null) {
                $value = rawurlencode($this->routeKey($named[$param]));
                $uri = str_replace(['{'.$param.'}', '{'.$param.'?}'], $value, $uri);
                unset($named[$param]);

                continue;
            }

            if (in_array($param, $optional, true)) {
                $uri = (string) preg_replace('#/\{'.preg_quote($param, '#').'\?\}#', '', $uri);

                continue;
            }

            throw new UrlGenerationException("Missing required parameter [{$param}] for route [{$name}].");
        }

        $uri = $uri === '' ? '/' : $uri;
        $query = $named === [] ? '' : '?'.http_build_query($named + $positional, '', '&', PHP_QUERY_RFC3986);

        $path = $uri === '/' ? '' : $uri;

        return ($absolute ? $this->root() : ($path === '' ? '/' : '')).$path.$query;
    }

    public function asset(string $path, ?bool $secure = null): string
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        return $this->to(ltrim($path, '/'), secure: $secure);
    }

    public function current(): string
    {
        return $this->request()?->url() ?? $this->root();
    }

    public function full(): string
    {
        return $this->request()?->fullUrl() ?? $this->root();
    }

    /**
     * URL anterior (Referer ou o guardado na sessão); $fallback quando não há.
     */
    public function previous(string|false $fallback = false): string
    {
        $request = $this->request();

        $referer = $request?->header('Referer');

        if ($referer !== null && $referer !== '') {
            return $referer;
        }

        if ($request !== null && $request->hasSession()) {
            $previous = $request->session()->previousUrl();

            if ($previous !== null) {
                return $previous;
            }
        }

        return $fallback !== false ? $this->to($fallback) : $this->to('/');
    }

    /** URL base: `app.url` ou o do pedido actual. */
    public function root(): string
    {
        $configured = $this->app->bound(Config::class) ? $this->app->make(Config::class)->get('app.url') : null;

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        return $this->request()?->root() ?? 'http://localhost';
    }

    protected function routeKey(mixed $value): string
    {
        if (is_object($value) && method_exists($value, 'getRouteKey')) {
            $value = $value->getRouteKey();
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }

    protected function isValidUrl(string $path): bool
    {
        return preg_match('~^(?:[a-z][a-z0-9+.-]*://|//|#|mailto:|tel:|sms:|javascript:)~i', $path) === 1;
    }

    protected function request(): ?Request
    {
        if (! $this->app->bound('request')) {
            return null;
        }

        $request = $this->app->make('request');

        return $request instanceof Request ? $request : null;
    }
}
