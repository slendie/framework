<?php

declare(strict_types=1);

namespace Slendie\Validation;

use Closure;
use Slendie\Database\DatabaseManager;
use Slendie\Database\Query\Builder;

/**
 * Consultas de `exists` e `unique` à base de dados.
 */
class PresenceVerifier
{
    public function __construct(protected DatabaseManager $db)
    {
    }

    /**
     * Número de linhas em que `$column = $value` (ignorando a linha `$idColumn = $excludeId`).
     *
     * @param  list<Closure(Builder): mixed>  $extra  condições adicionais
     */
    public function getCount(string $table, string $column, mixed $value, mixed $excludeId = null, ?string $idColumn = null, array $extra = []): int
    {
        $query = $this->db->table($table)->where($column, '=', $value);

        if ($excludeId !== null && $excludeId !== 'NULL') {
            $query->where($idColumn ?? 'id', '<>', $excludeId);
        }

        foreach ($extra as $condition) {
            $condition($query);
        }

        return $query->count();
    }

    /**
     * Quantos dos valores existem na coluna.
     *
     * @param  list<mixed>  $values
     * @param  list<Closure(Builder): mixed>  $extra
     */
    public function getMultiCount(string $table, string $column, array $values, array $extra = []): int
    {
        $values = array_values(array_unique($values, SORT_REGULAR));

        if ($values === []) {
            return 0;
        }

        $query = $this->db->table($table)->whereIn($column, $values);

        foreach ($extra as $condition) {
            $condition($query);
        }

        return $query->count();
    }
}
