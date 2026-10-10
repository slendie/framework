<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class Mailer
{
    public function __construct(public readonly Clock $clock, public readonly string $host = 'localhost')
    {
    }
}
