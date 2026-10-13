<?php

declare(strict_types=1);

return [
    /*
    | Canal por omissão: uma das chaves de "channels".
    */
    'default' => env('LOG_CHANNEL', 'single'),

    /*
    | Drivers: single, daily, stderr, null, stack (lista de canais). Novos drivers: Log::extend().
    | "level" é opcional (por omissão debug): só regista esse nível ou mais grave.
    */
    'channels' => [
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/slendie.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/slendie.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        'stderr' => [
            'driver' => 'stderr',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'null',
        ],

        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],
    ],
];
