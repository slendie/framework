<?php

declare(strict_types=1);

namespace Slendie\Database\Query\Grammars;

use Slendie\Database\Query\Builder;
use Slendie\Database\Query\Grammar;

class PgsqlGrammar extends Grammar
{
    protected function dateBasedWhere(string $type, array $where): string
    {
        $column = $this->wrap($where['column']);

        $expression = $type === 'date'
            ? "{$column}::date"
            : 'extract('.strtoupper($type)." from {$column})";

        return $expression.' '.$where['operator'].' ?';
    }

    /** @param array<string, mixed> $where */
    protected function whereLike(Builder $query, array $where): string
    {
        $operator = ($where['not'] ? 'not ' : '').($where['caseSensitive'] ? 'like' : 'ilike');

        return $this->wrap($where['column']).' '.$operator.' ?';
    }

    public function compileInsertGetId(Builder $query, array $values, ?string $sequence): string
    {
        return $this->compileInsert($query, $values).' returning '.$this->wrap($sequence ?? 'id');
    }

    public function insertGetIdUsesReturning(): bool
    {
        return true;
    }

    public function compileInsertOrIgnore(Builder $query, array $values): string
    {
        return $this->compileInsert($query, $values).' on conflict do nothing';
    }

    public function compileTruncate(Builder $query): array
    {
        return ['truncate '.$this->wrapTable((string) $query->from).' restart identity cascade' => []];
    }
}
