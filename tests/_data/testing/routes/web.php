<?php

declare(strict_types=1);

use Slendie\Http\Request;
use Slendie\Support\Facades\Route;
use Slendie\Tests\Fixtures\Http\TagMiddleware;
use Slendie\Tests\Fixtures\Testing\PostController;

Route::view('/', 'home', ['title' => 'Olá Mundo', 'items' => [1, 2, 3]]);

Route::get('/text', fn () => 'plain <b>text</b> & more');
Route::get('/json', fn () => response()->json([
    'name' => 'Ana',
    'tags' => ['a', 'b'],
    'nested' => ['x' => 1, 'y' => 2],
    'users' => [['id' => 1, 'email' => 'a@x.pt'], ['id' => 2, 'email' => 'b@x.pt']],
]));
Route::get('/list', fn () => response()->json([1, 2, 3]));
Route::get('/empty', fn () => response('', 204));
Route::get('/boom', fn () => throw new RuntimeException('kaboom'));
Route::get('/old', fn () => redirect('/target'));
Route::get('/chain', fn () => redirect('/old'));
Route::get('/target', fn () => 'target page')->name('target');
Route::get('/cookie', fn () => response('c')->cookie('flavour', 'choc', 60));
Route::get('/header', fn () => response('h', 200, ['X-Test' => 'yes']));
Route::get('/whoami', fn (Request $request) => response()->json([
    'ua' => $request->userAgent(),
    'auth' => $request->header('Authorization'),
    'accept' => $request->header('Accept'),
    'cookie' => $request->cookie('jar'),
    'query' => $request->query(),
    'referer' => $request->header('Referer'),
]));
Route::get('/session', fn () => response()->json(['user_id' => session('user_id'), 'all' => session()->all()]));
Route::get('/back', fn () => back());
Route::get('/tagged', fn () => 'ok')->middleware(TagMiddleware::class);

Route::post('/echo', fn (Request $request) => response()->json($request->all()));
Route::put('/echo', fn (Request $request) => response()->json(['method' => 'PUT'] + $request->all()));
Route::patch('/echo', fn (Request $request) => response()->json(['method' => 'PATCH'] + $request->all()));
Route::delete('/echo', fn (Request $request) => response()->json(['method' => 'DELETE'] + $request->all()));
Route::options('/echo', fn () => response('', 204, ['Allow' => 'GET, POST']));
Route::post('/flash', fn () => redirect('/target')->with('status', 'saved')->withInput());
Route::post('/fail', function (Request $request) {
    $request->validate(['email' => 'required|email', 'name' => 'required']);

    return 'never';
});
Route::post('/upload', function (Request $request) {
    $file = $request->file('avatar');

    return response()->json($file === null ? ['received' => false] : [
        'received' => true,
        'valid' => $file->isValid(),
        'name' => $file->clientName(),
        'size' => $file->size(),
        'mime' => $file->mimeType(),
    ]);
});

Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
Route::get('/posts/count', [PostController::class, 'count']);
Route::delete('/posts/{id}', [PostController::class, 'destroy']);
