<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

use Closure;

/**
 * `Rule::unique('users')->ignore($user->id)` — o valor não pode existir já na tabela.
 */
class Unique extends DatabaseRule
{
    protected mixed $ignore = null;

    protected ?string $idColumn = null;

    /**
     * Ignora a linha com este id (útil ao actualizar). Aceita um objecto com `getKey()` (Model).
     */
    public function ignore(mixed $id, ?string $idColumn = null): static
    {
        if (is_object($id) && method_exists($id, 'getKey')) {
            $id = $id->getKey();
        }

        $this->ignore = $id;
        $this->idColumn = $idColumn;

        return $this;
    }

    /** Ignora os registos apagados (soft delete). */
    public function withoutTrashed(string $column = 'deleted_at'): static
    {
        return $this->whereNull($column);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validator = $this->validator();

        if (! is_scalar($value)) {
            $fail($validator->failMessage($attribute, 'unique'));

            return;
        }

        $count = $validator->getPresenceVerifier()->getCount(
            $this->table,
            $this->resolveColumn($attribute),
            $value,
            $this->ignore,
            $this->idColumn,
            $this->wheres,
        );

        if ($count > 0) {
            $fail($validator->failMessage($attribute, 'unique'));
        }
    }
}
