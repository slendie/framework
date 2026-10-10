<?php

declare(strict_types=1);

namespace Slendie\Routing;

use Slendie\Foundation\Application;
use Slendie\Http\RedirectResponse;
use Slendie\Http\Request;

class Redirector
{
    public function __construct(protected Application $app, protected UrlGenerator $url)
    {
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function to(string $path, int $status = 302, array $headers = [], ?bool $secure = null): RedirectResponse
    {
        return $this->createRedirect($this->url->to($path, [], $secure), $status, $headers);
    }

    /** Redireciona para um URL externo sem o tocar. */
    public function away(string $url, int $status = 302): RedirectResponse
    {
        return $this->createRedirect($url, $status);
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function route(string $name, array $parameters = [], int $status = 302): RedirectResponse
    {
        return $this->createRedirect($this->url->route($name, $parameters), $status);
    }

    public function back(int $status = 302, string|false $fallback = false): RedirectResponse
    {
        return $this->createRedirect($this->url->previous($fallback), $status);
    }

    public function refresh(int $status = 302): RedirectResponse
    {
        return $this->createRedirect($this->url->full(), $status);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function createRedirect(string $url, int $status, array $headers = []): RedirectResponse
    {
        $redirect = new RedirectResponse($url, $status);
        $redirect->withHeaders($headers);

        if ($this->app->bound('request')) {
            $request = $this->app->make('request');

            if ($request instanceof Request && $request->hasSession()) {
                $redirect->setSession($request->session());
            }
        }

        return $redirect;
    }
}
