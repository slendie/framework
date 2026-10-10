<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class NeedsNullable
{
    public function __construct(public readonly ?GreeterContract $greeter = null)
    {
    }
}
