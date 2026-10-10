<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Slendie'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'nested' => [
        'level' => ['deep' => 'value'],
    ],
    'providers' => [
        Slendie\Tests\Fixtures\Providers\ConfigListedProvider::class,
    ],
];
