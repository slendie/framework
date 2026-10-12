<?php

declare(strict_types=1);

namespace Slendie\Console;

/** Opção `--nome` de um comando: booleana, com valor ou repetível (array). */
final class InputOption
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $shortcut = null,
        public readonly bool $acceptsValue = false,
        public readonly bool $isArray = false,
        public readonly mixed $default = null,
        public readonly string $description = '',
    ) {
    }
}
