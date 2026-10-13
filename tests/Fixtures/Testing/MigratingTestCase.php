<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Testing;

use Slendie\Testing\DatabaseMigrations;

abstract class MigratingTestCase extends FixtureTestCase
{
    use DatabaseMigrations;
}
