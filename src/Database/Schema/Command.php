<?php

declare(strict_types=1);

namespace Slendie\Database\Schema;

/**
 * Uma operação de um Blueprint (`create`, `unique`, `dropColumn`...); a grammar compila-a para SQL.
 */
class Command
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public string $name,
        public array $columns = [],
        public ?string $index = null,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $value = null,
    ) {
    }
}
