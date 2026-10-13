<?php

declare(strict_types=1);

namespace Slendie\Testing\Concerns;

use LogicException;
use PHPUnit\Framework\Assert;

/**
 * Ganchos genéricos de autenticação: a framework não traz auth; o package `slendie/auth` regista o serviço `auth`
 * (um AuthManager com `guard($name)`; cada guard tem `setUser()`, `check()` e `user()`).
 */
trait InteractsWithAuthentication
{
    public function actingAs(object $user, ?string $guard = null): static
    {
        $this->guardCall($guard, 'setUser', $user);

        return $this;
    }

    /** Alias de actingAs(). */
    public function be(object $user, ?string $guard = null): static
    {
        return $this->actingAs($user, $guard);
    }

    public function assertAuthenticated(?string $guard = null): static
    {
        Assert::assertTrue($this->guardCall($guard, 'check') === true, 'The user is not authenticated.');

        return $this;
    }

    public function assertGuest(?string $guard = null): static
    {
        Assert::assertFalse($this->guardCall($guard, 'check') === true, 'The user is authenticated.');

        return $this;
    }

    public function assertAuthenticatedAs(object $user, ?string $guard = null): static
    {
        $actual = $this->guardCall($guard, 'user');

        Assert::assertNotNull($actual, 'The current user is not authenticated.');
        Assert::assertTrue(
            $actual === $user
                || (method_exists($user, 'getKey') && is_object($actual) && $user::class === $actual::class && method_exists($actual, 'getKey') && $user->getKey() === $actual->getKey()),
            'The currently authenticated user is not who was expected.',
        );

        return $this;
    }

    /**
     * Chama um método do guard do package auth (`check`, `user`, `setUser`); falha com uma mensagem clara se o guard não o tiver.
     */
    private function guardCall(?string $name, string $method, mixed ...$arguments): mixed
    {
        if (! $this->app->bound('auth')) {
            throw new LogicException('Autenticação indisponível: instale slendie/auth (composer require slendie/auth) para usar actingAs().');
        }

        $auth = $this->app->make('auth');

        if (! is_object($auth) || ! method_exists($auth, 'guard')) {
            throw new LogicException('O serviço [auth] não tem o método guard(): confirme a versão do slendie/auth.');
        }

        $guard = $auth->guard($name);

        if (! is_object($guard)) {
            throw new LogicException('O guard pedido não existe.');
        }

        if (! method_exists($guard, $method)) {
            throw new LogicException('O guard de autenticação não tem o método '.$method.'().');
        }

        return $guard->{$method}(...$arguments);
    }
}
