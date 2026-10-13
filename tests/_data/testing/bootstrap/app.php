<?php

declare(strict_types=1);

use Slendie\Foundation\Application;

return (new Application(dirname(__DIR__)))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
    )
    ->create();
