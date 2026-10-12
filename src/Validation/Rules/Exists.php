<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

use Closure;

/**
 * `Rule::exists('categories', 'id')->where('active', 1)` — o valor tem de existir na tabela.
 */
class Exists extends DatabaseRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validator = $this->validator();
        $verifier = $validator->getPresenceVerifier();
        $column = $this->resolveColumn($attribute);

        if (is_array($value)) {
            $distinct = array_values(array_unique($value, SORT_REGULAR));
            $found = $verifier->getMultiCount($this->table, $column, $distinct, $this->wheres);
            $ok = $distinct !== [] && $found >= count($distinct);
        } elseif (is_scalar($value)) {
            $ok = $verifier->getCount($this->table, $column, $value, null, null, $this->wheres) > 0;
        } else {
            $ok = false;
        }

        if (! $ok) {
            $fail($validator->failMessage($attribute, 'exists'));
        }
    }
}
