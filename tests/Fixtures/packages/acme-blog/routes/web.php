<?php

declare(strict_types=1);

use Slendie\Support\Facades\Route;

Route::get('/acme', fn () => view('acme::index', ['who' => 'mundo']))->name('acme.index');
Route::get('/acme/guarded', fn () => 'guarded')->middleware('acme');
