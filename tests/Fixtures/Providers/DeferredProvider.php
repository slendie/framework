<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Providers;

use Slendie\Foundation\ServiceProvider;
use Slendie\Tests\Fixtures\Clock;

final class DeferredProvider extends ServiceProvider
{
    protected bool $defer = true;

    public static int $registered = 0;

    public function register(): void
    {
        self::$registered++;
        $this->app->singleton('fixture.clock', Clock::class);
    }

    public function provides(): array
    {
        return ['fixture.clock'];
    }
}
