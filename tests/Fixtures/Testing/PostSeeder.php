<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Testing;

use Slendie\Database\Seeders\Seeder;
use Slendie\Tests\Fixtures\Model\Post;

final class PostSeeder extends Seeder
{
    public function run(): void
    {
        Post::create(['title' => 'Primeiro', 'slug' => 'primeiro']);
        Post::create(['title' => 'Segundo', 'slug' => 'segundo']);
    }
}
