<?php

declare(strict_types=1);

namespace Slendie\Database\Query\Grammars;

use Slendie\Database\Query\Builder;
use Slendie\Database\Query\Grammar;

class SqliteGrammar extends Grammar
{
    protected function dateBasedWhere(string $type, array $where): string
    {
        $column = $this->wrap($where['column']);

        $expression = match ($type) {
            'date' => "date({$column})",
            'year' => "strftime('%Y', {$column})",
            'month' => "strftime('%m', {$column})",
            default => "strftime('%d', {$column})",
        };

        return $expression.' '.$where['operator'].' cast(? as text)';
    }

    public function prepareDateBinding(string $type, mixed $value): mixed
    {
        $value = parent::prepareDateBinding($type, $value);

        // strftime devolve texto com zeros à esquerda: '2026', '05', '09'.
        return match ($type) {
            'year' => (string) $value,
            'month', 'day' => str_pad((string) $value, 2, '0', STR_PAD_LEFT),
            default => $value,
        };
    }

    /** @param array<string, mixed> $where */
    protected function whereLike(Builder $query, array $where): string
    {
        // LIKE é insensível a maiúsculas (ASCII); para sensível usa-se GLOB.
        if ($where['caseSensitive']) {
            return $this->wrap($where['column']).($where['not'] ? ' not glob ?' : ' glob ?');
        }

        return parent::whereLike($query, $where);
    }

    public function prepareLikeBinding(string $value, bool $caseSensitive): string
    {
        if (! $caseSensitive) {
            return $value;
        }

        return strtr($value, ['*' => '[*]', '?' => '[?]', '[' => '[[]', '%' => '*', '_' => '?']);
    }

    protected function compileOffset(Builder $query): string
    {
        // O SQLite não aceita OFFSET sem LIMIT.
        if ($query->offset !== null && $query->limit === null) {
            return 'limit -1 offset '.$query->offset;
        }

        return parent::compileOffset($query);
    }

    public function compileInsertOrIgnore(Builder $query, array $values): string
    {
        return (string) preg_replace('/^insert/i', 'insert or ignore', $this->compileInsert($query, $values), 1);
    }

    public function compileTruncate(Builder $query): array
    {
        $table = $this->wrapTable((string) $query->from);

        return [
            "delete from {$table}" => [],
            'delete from sqlite_sequence where name = ?' => [(string) $query->from],
        ];
    }
}
