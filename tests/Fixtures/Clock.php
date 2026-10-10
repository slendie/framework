<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class Clock
{
    public function now(): string
    {
        return '2026-10-10';
    }
}
