<?php

declare(strict_types=1);

namespace Slendie\Console;

/** Argumento posicional de um comando. */
final class InputArgument
{
    public function __construct(
        public readonly string $name,
        public readonly bool $required = false,
        public readonly bool $isArray = false,
        public readonly mixed $default = null,
        public readonly string $description = '',
    ) {
    }
}
