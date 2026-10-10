<?php

declare(strict_types=1);

namespace Slendie\Foundation\Bootstrap;

use Slendie\Foundation\Application;

final class BootProviders
{
    public function bootstrap(Application $app): void
    {
        $app->boot();
    }
}
