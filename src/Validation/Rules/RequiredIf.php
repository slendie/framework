<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

use Closure;
use Stringable;

/**
 * `Rule::requiredIf(fn () => $isCompany)` → `required` ou nada.
 */
class RequiredIf implements Stringable
{
    protected string $rule = 'required';

    public function __construct(protected bool|Closure $condition)
    {
    }

    public function __toString(): string
    {
        $applies = $this->condition instanceof Closure ? (bool) ($this->condition)() : $this->condition;

        return $applies ? $this->rule : '';
    }
}
