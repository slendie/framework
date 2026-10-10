<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class CircularA
{
    public function __construct(public CircularB $b)
    {
    }
}
