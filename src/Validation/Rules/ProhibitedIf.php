<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

/**
 * `Rule::prohibitedIf(fn () => ...)` → `prohibited` ou nada.
 */
class ProhibitedIf extends RequiredIf
{
    protected string $rule = 'prohibited';
}
