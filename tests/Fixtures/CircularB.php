<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class CircularB
{
    public function __construct(public CircularA $a)
    {
    }
}
