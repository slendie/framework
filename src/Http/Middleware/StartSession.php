<?php

declare(strict_types=1);

namespace Slendie\Http\Middleware;

use Closure;
use SessionHandlerInterface;
use Slendie\Foundation\Application;
use Slendie\Http\Cookie;
use Slendie\Http\HttpMethod;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Session\Session;
use Slendie\Support\Config;
use Slendie\Support\Facade;

/**
 * Inicia a sessão (id vindo da cookie), grava-a no fim do pedido e envia a cookie.
 */
class StartSession
{
    public function __construct(protected Application $app, protected Config $config)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->startSession($request);

        try {
            $response = $next($request);
        } finally {
            $this->storePreviousUrl($request, $session);
            $session->save();
        }

        return $this->addCookieToResponse($request, $session, $response);
    }

    /** Garante que nada fica por gravar (ex.: alterações feitas depois do handle). */
    public function terminate(Request $request, Response $response): void
    {
        if ($request->hasSession() && $request->session()->isDirty()) {
            $request->session()->save();
        }
    }

    protected function startSession(Request $request): Session
    {
        /** @var SessionHandlerInterface $handler */
        $handler = $this->app->make('session.handler');

        $cookie = $request->cookie($this->cookieName());

        $session = new Session($this->cookieName(), $handler, is_string($cookie) ? $cookie : null);
        $session->start();

        $request->setSession($session);
        $this->app->instance('session', $session);
        Facade::clearResolvedInstance('session');

        $this->collectGarbage($handler);

        return $session;
    }

    protected function storePreviousUrl(Request $request, Session $session): void
    {
        if ($request->method() === HttpMethod::GET && ! $request->ajax() && ! $request->isPrefetch() && ! $request->expectsJson()) {
            $session->setPreviousUrl($request->fullUrl());
        }
    }

    protected function addCookieToResponse(Request $request, Session $session, Response $response): Response
    {
        $lifetime = $this->config->get('session.lifetime', 120);
        $secure = $this->config->get('session.secure');
        $domain = $this->config->get('session.domain');
        $sameSite = $this->config->get('session.same_site', 'lax');

        $response->cookie(new Cookie(
            $this->cookieName(),
            $session->id(),
            time() + (is_numeric($lifetime) ? (int) $lifetime : 120) * 60,
            is_string($this->config->get('session.path')) ? (string) $this->config->get('session.path') : '/',
            is_string($domain) && $domain !== '' ? $domain : null,
            $secure === null ? $request->isSecure() : (bool) $secure,
            (bool) $this->config->get('session.http_only', true),
            is_string($sameSite) ? $sameSite : 'lax',
        ));

        return $response;
    }

    /** Limpeza ocasional (2% dos pedidos) de sessões expiradas. */
    protected function collectGarbage(SessionHandlerInterface $handler): void
    {
        if (random_int(1, 100) > 2) {
            return;
        }

        $lifetime = $this->config->get('session.lifetime', 120);
        $handler->gc((is_numeric($lifetime) ? (int) $lifetime : 120) * 60);
    }

    protected function cookieName(): string
    {
        $name = $this->config->get('session.cookie', 'slendie_session');

        return is_string($name) && $name !== '' ? $name : 'slendie_session';
    }
}
