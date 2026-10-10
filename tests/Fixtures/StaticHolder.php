<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class StaticHolder
{
    public static function build(Clock $clock): string
    {
        return 'static:'.$clock->now();
    }
}
