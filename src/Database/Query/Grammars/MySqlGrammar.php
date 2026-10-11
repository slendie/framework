<?php

declare(strict_types=1);

namespace Slendie\Database\Query\Grammars;

use Slendie\Database\Query\Builder;
use Slendie\Database\Query\Grammar;

class MySqlGrammar extends Grammar
{
    protected function quote(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    protected function dateBasedWhere(string $type, array $where): string
    {
        $function = match ($type) {
            'date' => 'DATE',
            'year' => 'YEAR',
            'month' => 'MONTH',
            default => 'DAY',
        };

        return $function.'('.$this->wrap($where['column']).') '.$where['operator'].' ?';
    }

    /** @param array<string, mixed> $where */
    protected function whereLike(Builder $query, array $where): string
    {
        // A collation utf8mb4_unicode_ci é insensível por defeito; BINARY torna-o sensível.
        $operator = ($where['not'] ? 'not like' : 'like').($where['caseSensitive'] ? ' binary' : '');

        return $this->wrap($where['column']).' '.$operator.' ?';
    }

    public function compileRandom(): string
    {
        return 'RAND()';
    }

    protected function compileOffset(Builder $query): string
    {
        if ($query->offset !== null && $query->limit === null) {
            return 'limit 18446744073709551615 offset '.$query->offset;
        }

        return parent::compileOffset($query);
    }

    public function compileInsertOrIgnore(Builder $query, array $values): string
    {
        return (string) preg_replace('/^insert/i', 'insert ignore', $this->compileInsert($query, $values), 1);
    }
}
