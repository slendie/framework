<?php

declare(strict_types=1);

return [
    'name' => 'TestingFixture',
    'env' => env('APP_ENV', 'production'),
    'debug' => true,
    'url' => 'http://localhost',
    'timezone' => 'UTC',
    'locale' => 'en',
    'key' => 'base64:dGVzdGluZy1rZXktdGVzdGluZy1rZXktMTIzNDU2Nzg=',
    'providers' => [
        Slendie\Tests\Fixtures\Console\CommandProvider::class,
    ],
];
