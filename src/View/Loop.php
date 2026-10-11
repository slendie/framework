<?php

declare(strict_types=1);

namespace Slendie\View;

/**
 * A variável `$loop` dentro de @foreach/@forelse.
 */
final class Loop
{
    public int $index = 0;

    public int $iteration = 0;

    public ?int $remaining;

    public bool $first = true;

    public ?bool $last;

    public bool $even = true;

    public bool $odd = false;

    public function __construct(public readonly ?int $count, public readonly int $depth, public readonly ?Loop $parent)
    {
        $this->remaining = $count;
        $this->last = $count === null ? null : $count === 1;
    }

    public function advance(): void
    {
        $this->iteration++;
        $this->index = $this->iteration - 1;
        $this->first = $this->iteration === 1;
        $this->even = $this->iteration % 2 === 0;
        $this->odd = ! $this->even;

        if ($this->count !== null) {
            $this->remaining = $this->count - $this->iteration;
            $this->last = $this->iteration === $this->count;
        }
    }
}
