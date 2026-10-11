<?php

declare(strict_types=1);

namespace Slendie\Database\Query;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Transforma o estado de um Builder em SQL. Cada driver sobrepõe só o que diverge
 * (quoting, INSERT IGNORE, TRUNCATE, funções de datas, ILIKE, RANDOM()).
 *
 * Todos os identificadores passam por wrap(), que os valida: nenhum texto livre chega ao SQL
 * a não ser através de uma Expression explícita.
 */
abstract class Grammar
{
    public const IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?(\s+as\s+[A-Za-z_][A-Za-z0-9_]*)?$/i';

    /** @var list<string> */
    public const OPERATORS = ['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like', 'ilike', 'in', 'not in', 'is', 'is not', 'between'];

    /** @var list<string> */
    public const AGGREGATES = ['count', 'sum', 'avg', 'min', 'max'];

    // -------------------------------------------------------------------------
    // Identificadores e parâmetros
    // -------------------------------------------------------------------------

    /**
     * @throws InvalidArgumentException se o identificador não for seguro
     */
    public static function assertIdentifier(string $identifier): string
    {
        if ($identifier === '*' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.\*$/', $identifier) === 1) {
            return $identifier;
        }

        if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new InvalidArgumentException("Identificador SQL inválido [{$identifier}]. Usa só letras, números e _ (ou DB::raw() para SQL cru).");
        }

        return $identifier;
    }

    /**
     * Devolve o operador normalizado (minúsculas, espaços únicos).
     *
     * @throws InvalidArgumentException
     */
    public static function assertOperator(string $operator): string
    {
        $operator = strtolower(trim((string) preg_replace('/\s+/', ' ', $operator)));

        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Operador SQL inválido [{$operator}].");
        }

        return $operator;
    }

    public function wrap(string|Expression $value): string
    {
        if ($value instanceof Expression) {
            return (string) $value->getValue();
        }

        static::assertIdentifier($value);

        if (preg_match('/^(.+?)\s+as\s+(.+)$/i', $value, $m) === 1) {
            return $this->wrap($m[1]).' as '.$this->wrapSegment($m[2]);
        }

        return implode('.', array_map($this->wrapSegment(...), explode('.', $value)));
    }

    public function wrapTable(string|Expression $table): string
    {
        return $this->wrap($table);
    }

    protected function wrapSegment(string $segment): string
    {
        return $segment === '*' ? $segment : $this->quote($segment);
    }

    /** Cita um único nome (já validado). */
    protected function quote(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    /**
     * Placeholder de um valor: `?`, ou o SQL cru se for uma Expression.
     */
    public function parameter(mixed $value): string
    {
        return $value instanceof Expression ? (string) $value->getValue() : '?';
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    public function parameterize(array $values): string
    {
        return implode(', ', array_map($this->parameter(...), $values));
    }

    /**
     * @param  array<array-key, string|Expression>  $columns
     */
    public function columnize(array $columns): string
    {
        return implode(', ', array_map($this->wrap(...), $columns));
    }

    /**
     * Valores com Expression não geram binding.
     *
     * @param  array<array-key, mixed>  $values
     * @return list<mixed>
     */
    public function bindingsFor(array $values): array
    {
        $bindings = [];

        foreach ($values as $value) {
            if (! $value instanceof Expression) {
                $bindings[] = $value;
            }
        }

        return $bindings;
    }

    // -------------------------------------------------------------------------
    // SELECT
    // -------------------------------------------------------------------------

    public function compileSelect(Builder $query): string
    {
        $parts = ['select'];

        if ($query->distinct && $query->aggregate === null) {
            $parts[] = 'distinct';
        }

        $parts[] = $this->compileColumns($query);

        if ($query->from !== null) {
            $parts[] = 'from '.$this->wrapTable($query->from);
        }

        foreach ([
            $this->compileJoins($query),
            $this->compileWheres($query),
            $this->compileGroups($query),
            $this->compileHavings($query),
            $this->compileOrders($query),
            $this->compileLimit($query),
            $this->compileOffset($query),
        ] as $part) {
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(' ', $parts);
    }

    protected function compileColumns(Builder $query): string
    {
        if ($query->aggregate !== null) {
            ['function' => $function, 'columns' => $columns] = $query->aggregate;

            $column = $this->columnize($columns);

            if ($query->distinct && $column !== '*') {
                $column = 'distinct '.$column;
            }

            return $function.'('.$column.') as '.$this->quote('aggregate');
        }

        return $query->columns === [] ? '*' : $this->columnize($query->columns);
    }

    protected function compileJoins(Builder $query): string
    {
        $sql = [];

        foreach ($query->joins as $join) {
            $table = $this->wrapTable($join->table);

            if ($join->type === 'cross') {
                $sql[] = "cross join {$table}";

                continue;
            }

            $on = $this->compileWheresToArray($join);
            $sql[] = "{$join->type} join {$table}".($on !== [] ? ' on '.$this->removeLeadingBoolean(implode(' ', $on)) : '');
        }

        return implode(' ', $sql);
    }

    protected function compileWheres(Builder $query): string
    {
        $sql = $this->compileWheresToArray($query);

        return $sql === [] ? '' : 'where '.$this->removeLeadingBoolean(implode(' ', $sql));
    }

    /**
     * @return list<string> cada item começa por `and`/`or`
     */
    protected function compileWheresToArray(Builder $query): array
    {
        $sql = [];

        foreach ($query->wheres as $where) {
            $method = 'where'.$where['type'];
            $sql[] = $where['boolean'].' '.$this->{$method}($query, $where);
        }

        return $sql;
    }

    protected function removeLeadingBoolean(string $sql): string
    {
        return (string) preg_replace('/^(and|or)\s+/i', '', $sql, 1);
    }

    /** @param array<string, mixed> $where */
    protected function whereBasic(Builder $query, array $where): string
    {
        return $this->wrap($where['column']).' '.$where['operator'].' '.$this->parameter($where['value']);
    }

    /** @param array<string, mixed> $where */
    protected function whereColumn(Builder $query, array $where): string
    {
        return $this->wrap($where['first']).' '.$where['operator'].' '.$this->wrap($where['second']);
    }

    /** @param array<string, mixed> $where */
    protected function whereIn(Builder $query, array $where): string
    {
        if ($where['values'] === []) {
            return $where['not'] ? '1 = 1' : '0 = 1';
        }

        return $this->wrap($where['column']).($where['not'] ? ' not in ' : ' in ').'('.$this->parameterize($where['values']).')';
    }

    /** @param array<string, mixed> $where */
    protected function whereNull(Builder $query, array $where): string
    {
        return $this->wrap($where['column']).($where['not'] ? ' is not null' : ' is null');
    }

    /** @param array<string, mixed> $where */
    protected function whereBetween(Builder $query, array $where): string
    {
        return $this->wrap($where['column']).($where['not'] ? ' not between ' : ' between ').'? and ?';
    }

    /** @param array<string, mixed> $where */
    protected function whereNested(Builder $query, array $where): string
    {
        $inner = $this->removeLeadingBoolean(implode(' ', $this->compileWheresToArray($where['query'])));

        return ($where['not'] ? 'not ' : '').'('.$inner.')';
    }

    /** @param array<string, mixed> $where */
    protected function whereRaw(Builder $query, array $where): string
    {
        return $where['sql'];
    }

    /** @param array<string, mixed> $where */
    protected function whereExists(Builder $query, array $where): string
    {
        return ($where['not'] ? 'not ' : '').'exists ('.$this->compileSelect($where['query']).')';
    }

    /** @param array<string, mixed> $where */
    protected function whereLike(Builder $query, array $where): string
    {
        return $this->wrap($where['column']).($where['not'] ? ' not like ?' : ' like ?');
    }

    /** @param array<string, mixed> $where */
    protected function whereDate(Builder $query, array $where): string
    {
        return $this->dateBasedWhere($where['function'], $where);
    }

    /**
     * `date`, `year`, `month` ou `day`.
     *
     * @param  array<string, mixed>  $where
     */
    abstract protected function dateBasedWhere(string $type, array $where): string;

    /**
     * Ajusta o valor ligado a um where de data ao formato devolvido pela função do driver.
     */
    public function prepareDateBinding(string $type, mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return match ($type) {
                'date' => $value->format('Y-m-d'),
                'year' => (int) $value->format('Y'),
                'month' => (int) $value->format('n'),
                default => (int) $value->format('j'),
            };
        }

        return $value;
    }

    /**
     * Valor ligado de um `LIKE`; um driver pode convertê-lo (ex.: GLOB no SQLite).
     */
    public function prepareLikeBinding(string $value, bool $caseSensitive): string
    {
        return $value;
    }

    protected function compileGroups(Builder $query): string
    {
        return $query->groups === [] ? '' : 'group by '.$this->columnize($query->groups);
    }

    protected function compileHavings(Builder $query): string
    {
        if ($query->havings === []) {
            return '';
        }

        $sql = [];

        foreach ($query->havings as $having) {
            $expression = $having['type'] === 'Raw'
                ? $having['sql']
                : $this->wrap($having['column']).' '.$having['operator'].' '.$this->parameter($having['value']);

            $sql[] = $having['boolean'].' '.$expression;
        }

        return 'having '.$this->removeLeadingBoolean(implode(' ', $sql));
    }

    protected function compileOrders(Builder $query): string
    {
        if ($query->orders === []) {
            return '';
        }

        $sql = array_map(
            fn (array $order): string => isset($order['sql']) ? $order['sql'] : $this->wrap($order['column']).' '.$order['direction'],
            $query->orders,
        );

        return 'order by '.implode(', ', $sql);
    }

    protected function compileLimit(Builder $query): string
    {
        return $query->limit === null ? '' : 'limit '.$query->limit;
    }

    protected function compileOffset(Builder $query): string
    {
        return $query->offset === null ? '' : 'offset '.$query->offset;
    }

    /** SQL de ordenação aleatória do driver. */
    public function compileRandom(): string
    {
        return 'RANDOM()';
    }

    public function compileExists(Builder $query): string
    {
        return 'select exists('.$this->compileSelect($query).') as '.$this->quote('exists');
    }

    // -------------------------------------------------------------------------
    // Escrita
    // -------------------------------------------------------------------------

    /**
     * @param  list<array<string, mixed>>  $values  linhas com as mesmas colunas, na mesma ordem
     */
    public function compileInsert(Builder $query, array $values): string
    {
        $table = $this->wrapTable((string) $query->from);

        if ($values === [] || $values[0] === []) {
            return "insert into {$table} default values";
        }

        $columns = $this->columnize(array_keys($values[0]));
        $rows = implode(', ', array_map(fn (array $row): string => '('.$this->parameterize($row).')', $values));

        return "insert into {$table} ({$columns}) values {$rows}";
    }

    /**
     * @param  list<array<string, mixed>>  $values
     */
    public function compileInsertGetId(Builder $query, array $values, ?string $sequence): string
    {
        return $this->compileInsert($query, $values);
    }

    /** O INSERT devolve o id (`RETURNING`) em vez de usar lastInsertId()? */
    public function insertGetIdUsesReturning(): bool
    {
        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $values
     */
    public function compileInsertOrIgnore(Builder $query, array $values): string
    {
        throw new InvalidArgumentException('Este driver não suporta insertOrIgnore().');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function compileUpdate(Builder $query, array $values): string
    {
        $sets = [];

        foreach ($values as $column => $value) {
            $sets[] = $this->wrap($column).' = '.$this->parameter($value);
        }

        $sql = 'update '.$this->wrapTable((string) $query->from).' set '.implode(', ', $sets);
        $where = $this->compileWheres($query);

        return $where === '' ? $sql : $sql.' '.$where;
    }

    public function compileDelete(Builder $query): string
    {
        $sql = 'delete from '.$this->wrapTable((string) $query->from);
        $where = $this->compileWheres($query);

        return $where === '' ? $sql : $sql.' '.$where;
    }

    /**
     * Uma ou mais instruções com os respectivos bindings.
     *
     * @return array<string, list<mixed>> sql => bindings
     */
    public function compileTruncate(Builder $query): array
    {
        return ['truncate table '.$this->wrapTable((string) $query->from) => []];
    }

    // -------------------------------------------------------------------------
    // Utilitários
    // -------------------------------------------------------------------------

    /**
     * Substitui os `?` pelos valores (escapados). **Só para mensagens e debug**, nunca para executar.
     *
     * @param  array<array-key, mixed>  $bindings
     */
    public static function interpolate(string $sql, array $bindings): string
    {
        $bindings = array_values($bindings);
        $i = 0;

        return (string) preg_replace_callback('/\?/', function (array $m) use (&$i, $bindings): string {
            if (! array_key_exists($i, $bindings)) {
                return $m[0];
            }

            return self::interpolateValue($bindings[$i++]);
        }, $sql);
    }

    protected static function interpolateValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            $value instanceof DateTimeInterface => "'".$value->format('Y-m-d H:i:s')."'",
            $value instanceof BackedEnum => self::interpolateValue($value->value),
            is_string($value) => "'".str_replace("'", "''", $value)."'",
            default => "'[".get_debug_type($value)."]'",
        };
    }
}
