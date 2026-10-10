<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Http;

use Slendie\Http\Controller;
use Slendie\Http\Request;

class PostController extends Controller
{
    public function __construct(protected Greeting $greeting)
    {
        $this->middleware('tag:controller');
    }

    public function index(): string
    {
        return 'index:'.$this->greeting->text;
    }

    public function create(): string
    {
        return 'create';
    }

    public function store(Request $request): array
    {
        return ['stored' => $request->input('title')];
    }

    public function show(int $post, Request $request): string
    {
        return 'show:'.$post.':'.get_debug_type($post).':'.$request->path();
    }

    public function edit(string $post): string
    {
        return 'edit:'.$post;
    }

    public function update(string $post): string
    {
        return 'update:'.$post;
    }

    public function destroy(string $post): string
    {
        return 'destroy:'.$post;
    }
}
