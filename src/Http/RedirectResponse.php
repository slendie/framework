<?php

declare(strict_types=1);

namespace Slendie\Http;

use InvalidArgumentException;
use RuntimeException;
use Slendie\Container\Container;
use Slendie\Session\Session;

class RedirectResponse extends Response
{
    protected ?Session $session = null;

    public function __construct(protected string $url, int $status = 302)
    {
        if ($url === '') {
            throw new InvalidArgumentException('O URL de redirecionamento não pode ser vazio.');
        }

        if ($status < 300 || $status > 399) {
            throw new InvalidArgumentException("O estado {$status} não é um redirecionamento.");
        }

        $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        parent::__construct(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=\''.$escaped.'\'"><title>Redirecting</title></head>'
            .'<body>Redirecting to <a href="'.$escaped.'">'.$escaped.'</a>.</body></html>',
            $status,
            ['Location' => $url],
        );
    }

    public function getTargetUrl(): string
    {
        return $this->url;
    }

    public function setSession(Session $session): static
    {
        $this->session = $session;

        return $this;
    }

    /**
     * Guarda dados na sessão (flash) para o próximo pedido.
     *
     * @param  string|array<string, mixed>  $key
     */
    public function with(string|array $key, mixed $value = null): static
    {
        $session = $this->session();

        foreach (is_array($key) ? $key : [$key => $value] as $k => $v) {
            $session->flash($k, $v);
        }

        return $this;
    }

    /**
     * Guarda o input do pedido (ou o array dado) para repopular o formulário com old().
     *
     * @param  array<string, mixed>|null  $input
     */
    public function withInput(?array $input = null): static
    {
        if ($input === null) {
            $container = Container::getInstance();
            $request = $container->bound('request') ? $container->make('request') : null;
            $input = $request instanceof Request
                ? $request->except(['password', 'password_confirmation', '_token'])
                : [];
        }

        $this->session()->flashInput($input);

        return $this;
    }

    /**
     * Guarda erros em flash `errors` (a MessageBag chega na peça 08; por agora um array).
     *
     * @param  array<string, mixed>|string  $errors
     */
    public function withErrors(array|string $errors, string $bag = 'default'): static
    {
        $errors = is_string($errors) ? ['error' => [$errors]] : $errors;

        $session = $this->session();
        $current = $session->get('errors', []);
        $current = is_array($current) ? $current : [];
        $current[$bag] = $errors;

        $session->flash('errors', $current);

        return $this;
    }

    protected function session(): Session
    {
        if ($this->session !== null) {
            return $this->session;
        }

        $container = Container::getInstance();

        if ($container->bound('request')) {
            $request = $container->make('request');

            if ($request instanceof Request && $request->hasSession()) {
                return $this->session = $request->session();
            }
        }

        throw new RuntimeException('Não existe sessão: o redirect precisa do middleware StartSession (grupo "web") para usar with()/withInput()/withErrors().');
    }
}
