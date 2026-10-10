<?php

declare(strict_types=1);

return [
    /*
    | Driver: "file" (storage/framework/sessions) ou "array" (memória; testes).
    */
    'driver' => env('SESSION_DRIVER', 'file'),

    /* Minutos até a sessão expirar por inactividade. */
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'cookie' => env('SESSION_COOKIE', 'slendie_session'),

    'path' => '/',

    'domain' => env('SESSION_DOMAIN'),

    /* null = automático (true se o pedido for HTTPS). */
    'secure' => env('SESSION_SECURE_COOKIE'),

    'http_only' => true,

    'same_site' => 'lax',

    /* Pasta dos ficheiros de sessão (null = storage/framework/sessions). */
    'files' => null,
];
