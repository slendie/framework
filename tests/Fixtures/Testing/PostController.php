<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Testing;

use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Support\Str;
use Slendie\Tests\Fixtures\Model\Post;

final class PostController
{
    public function store(Request $request): Response
    {
        $data = $request->validate(['title' => 'required|string|max:50']);

        $post = Post::create(['title' => $data['title'], 'slug' => Str::slug($data['title'])]);

        if ($request->expectsJson()) {
            return response()->json($post->toArray(), 201);
        }

        return redirect('/target')->with('status', 'created');
    }

    public function count(): Response
    {
        return response()->json(['count' => Post::query()->count()]);
    }

    public function destroy(int $id): Response
    {
        Post::query()->findOrFail($id)->delete();

        return response('', 204);
    }
}
