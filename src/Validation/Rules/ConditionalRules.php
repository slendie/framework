<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

use Closure;

/**
 * Resultado de `Rule::when()`: o parser escolhe o conjunto de regras com base nos dados.
 */
final class ConditionalRules
{
    /**
     * @param  string|list<mixed>  $rules
     * @param  string|list<mixed>  $default
     */
    public function __construct(
        private readonly bool|Closure $condition,
        private readonly string|array $rules,
        private readonly string|array $default = [],
    ) {
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return string|list<mixed>
     */
    public function rules(array $data): string|array
    {
        $passes = $this->condition instanceof Closure ? (bool) ($this->condition)($data) : $this->condition;

        return $passes ? $this->rules : $this->default;
    }
}
