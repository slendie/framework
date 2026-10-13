<?php

declare(strict_types=1);

namespace Slendie\Testing\Concerns;

use SessionHandlerInterface;
use Slendie\Session\ArraySessionHandler;
use Slendie\Session\Session;
use Slendie\Support\Config;

/**
 * Sessão do cliente de teste: um handler em memória partilhado entre os pedidos do mesmo teste, ligado ao
 * "browser" simulado por uma cookie de sessão.
 */
trait InteractsWithSession
{
    protected ?ArraySessionHandler $sessionHandler = null;

    protected ?string $sessionId = null;

    /**
     * Prepara a sessão do próximo pedido: `withSession(['user_id' => 1])`. As chaves ficam gravadas antes do pedido.
     *
     * @param  array<string, mixed>  $data
     */
    public function withSession(array $data): static
    {
        $session = $this->startSession();
        $session->put($data);
        $session->save();

        return $this;
    }

    /** Esquece todos os dados da sessão (mantém o mesmo id/cookie). */
    public function flushSession(): static
    {
        if ($this->sessionId !== null) {
            $this->sessionHandler()->destroy($this->sessionId);
        }

        return $this;
    }

    /**
     * Garante que existe uma sessão iniciada (com o token CSRF) e a cookie que a liga ao cliente.
     */
    public function startSession(): Session
    {
        $session = new Session($this->sessionCookieName(), $this->sessionHandler(), $this->sessionId);
        $session->start();

        $this->sessionId = $session->id();
        $this->cookies[$this->sessionCookieName()] = $this->sessionId;

        return $session;
    }

    /**
     * Sem argumentos devolve um retrato da sessão do cliente (ou null se ainda não existe); com chave devolve o valor.
     * Não grava.
     */
    public function session(?string $key = null, mixed $default = null): mixed
    {
        $session = $this->currentSession();

        if ($key === null) {
            return $session;
        }

        return $session === null ? $default : $session->get($key, $default);
    }

    protected function currentSession(): ?Session
    {
        if ($this->sessionId === null) {
            return null;
        }

        $session = new Session($this->sessionCookieName(), $this->sessionHandler(), $this->sessionId);
        $session->start();

        return $session;
    }

    protected function sessionHandler(): ArraySessionHandler
    {
        if ($this->sessionHandler === null) {
            $this->sessionHandler = new ArraySessionHandler();
            $this->app->instance('session.handler', $this->sessionHandler);
            $this->app->instance(SessionHandlerInterface::class, $this->sessionHandler);
        }

        return $this->sessionHandler;
    }

    protected function sessionCookieName(): string
    {
        $name = $this->app->make(Config::class)->get('session.cookie', 'slendie_session');

        return is_string($name) && $name !== '' ? $name : 'slendie_session';
    }
}
