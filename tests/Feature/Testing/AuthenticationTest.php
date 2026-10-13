<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Slendie\Tests\Fixtures\Testing\FixtureTestCase;

uses(FixtureTestCase::class);

it('actingAs sem o package auth lança uma LogicException clara', function (): void {
    expect(fn () => $this->actingAs(new stdClass()))
        ->toThrow(LogicException::class, 'slendie/auth');
    expect(fn () => $this->assertGuest())->toThrow(LogicException::class, 'slendie/auth');
});

it('com um serviço auth compatível, actingAs/assertAuthenticated/assertGuest funcionam', function (): void {
    $guard = new class () {
        public ?object $user = null;

        public function setUser(object $user): void
        {
            $this->user = $user;
        }

        public function check(): bool
        {
            return $this->user !== null;
        }

        public function user(): ?object
        {
            return $this->user;
        }
    };

    $auth = new class ($guard) {
        public ?string $asked = null;

        public function __construct(private readonly object $guard)
        {
        }

        public function guard(?string $name = null): object
        {
            $this->asked = $name;

            return $this->guard;
        }
    };

    $this->swap('auth', $auth);

    $user = new class () {
        public function getKey(): int
        {
            return 5;
        }
    };

    $this->assertGuest();

    $this->actingAs($user, 'web');

    expect($auth->asked)->toBe('web');

    $this->assertAuthenticated()->assertAuthenticatedAs($user);

    $this->be($user);

    expect($auth->asked)->toBeNull();

    expect(fn () => $this->assertGuest())->toThrow(AssertionFailedError::class);
    expect(fn () => $this->assertAuthenticatedAs(new stdClass()))->toThrow(AssertionFailedError::class, 'not who was expected');

    $guard->user = null;

    expect(fn () => $this->assertAuthenticated())->toThrow(AssertionFailedError::class)
        ->and(fn () => $this->assertAuthenticatedAs($user))->toThrow(AssertionFailedError::class, 'not authenticated');
});

it('um serviço auth sem guard() ou um guard inexistente são recusados', function (): void {
    $this->swap('auth', new stdClass());

    expect(fn () => $this->actingAs(new stdClass()))->toThrow(LogicException::class, 'guard()');

    $this->swap('auth', new class () {
        public function guard(?string $name = null): mixed
        {
            return null;
        }
    });

    expect(fn () => $this->actingAs(new stdClass()))->toThrow(LogicException::class, 'guard pedido');
});
