<?php

declare(strict_types=1);

namespace Slendie\Database;

use Slendie\Database\Query\Grammar;
use Slendie\Exceptions\SlendieException;
use Throwable;

/**
 * Envolve uma PDOException com o SQL e os bindings que a provocaram.
 *
 * A mensagem inclui o SQL com os bindings interpolados, mas **só para leitura humana**:
 * o SQL executado usa sempre prepared statements.
 */
class QueryException extends SlendieException
{
    /**
     * @param  array<array-key, mixed>  $bindings
     */
    public function __construct(
        protected string $sql,
        protected array $bindings,
        Throwable $previous,
        protected ?string $connectionName = null,
    ) {
        parent::__construct($this->formatMessage($sql, $bindings, $previous), 0, $previous);

        $this->code = $previous->getCode();
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    /**
     * O driver indicou que a tabela não existe (SQLite, MySQL 1146, PostgreSQL 42P01)?
     */
    public static function isMissingTable(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'no such table')
            || str_contains($message, '1146')
            || str_contains($message, '42P01');
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     */
    protected function formatMessage(string $sql, array $bindings, Throwable $previous): string
    {
        $message = $previous->getMessage().' (SQL: '.Grammar::interpolate($sql, $bindings).')';

        if (self::isMissingTable($previous)) {
            $table = $this->extractTable($previous->getMessage());

            $message .= ' Tabela '.($table !== null ? "'{$table}' " : '').'não existe. Correu `php slendie migrate`?';
        }

        return $message;
    }

    protected function extractTable(string $message): ?string
    {
        $patterns = [
            '/no such table: ([^\s)]+)/i',
            '/Table \'([^\']+)\' doesn\'t exist/i',
            '/relation "([^"]+)" does not exist/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $m) === 1) {
                $name = $m[1];

                return str_contains($name, '.') ? substr($name, (int) strrpos($name, '.') + 1) : $name;
            }
        }

        return null;
    }
}
