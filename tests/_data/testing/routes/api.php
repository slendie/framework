<?php

declare(strict_types=1);

use Slendie\Support\Facades\Route;

Route::get('/ping', fn () => response()->json([
    'pong' => true,
    'items' => [['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']],
    'meta' => ['total' => 2],
]));
