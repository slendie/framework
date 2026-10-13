<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Testing;

use Slendie\Testing\RefreshDatabase;

/** RefreshDatabase com semeador: o PostSeeder corre na primeira migração do processo. */
abstract class SeededTestCase extends FixtureTestCase
{
    use RefreshDatabase;

    protected string $seeder = PostSeeder::class;
}
