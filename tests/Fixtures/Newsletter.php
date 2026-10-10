<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class Newsletter
{
    public function __construct(public readonly Mailer $mailer, public readonly GreeterContract $greeter)
    {
    }

    public function send(string $to, Clock $clock, int $times = 1): string
    {
        return str_repeat($this->greeter->greet($to).' '.$clock->now().' ', $times);
    }
}
