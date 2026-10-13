<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Testing;

use Slendie\Testing\DatabaseTransactions;

abstract class TransactionalTestCase extends FixtureTestCase
{
    use DatabaseTransactions;

    /** @var list<string|null> */
    protected array $connectionsToTransact = ['sqlite'];
}
