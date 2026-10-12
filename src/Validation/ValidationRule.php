<?php

declare(strict_types=1);

namespace Slendie\Validation;

use Closure;

/**
 * Regra de validação como objecto. Chamar `$fail('mensagem')` para reprovar (`:attribute` é substituído).
 *
 * Uma regra com a propriedade pública `$implicit = true` corre mesmo quando o valor está vazio/ausente.
 */
interface ValidationRule
{
    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void;
}
