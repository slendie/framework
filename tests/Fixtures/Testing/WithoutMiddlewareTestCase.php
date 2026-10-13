<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Testing;

use Slendie\Testing\WithoutMiddleware;

abstract class WithoutMiddlewareTestCase extends FixtureTestCase
{
    use WithoutMiddleware;
}
