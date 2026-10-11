<?php

declare(strict_types=1);

namespace Slendie\Database;

use PDO;
use PDOStatement;
use stdClass;

/**
 * Wrapper de PDOStatement com fetch estável: `fetch()` devolve `null` (não `false`) sem linha.
 * Em modo `pretend` não há PDOStatement e todos os métodos devolvem resultados vazios.
 */
class Statement
{
    public function __construct(protected ?PDOStatement $statement = null)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(): ?array
    {
        if ($this->statement === null) {
            return null;
        }

        $row = $this->statement->fetch(PDO::FETCH_ASSOC);

        /** @var array<string, mixed>|false $row */
        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(): array
    {
        if ($this->statement === null) {
            return [];
        }

        /** @var list<array<string, mixed>> */
        return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        if ($this->statement === null) {
            return null;
        }

        $value = $this->statement->fetchColumn($column);

        return $value === false ? null : $value;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function fetchObject(string $class = stdClass::class): ?object
    {
        if ($this->statement === null) {
            return null;
        }

        $object = $this->statement->fetchObject($class);

        return $object === false ? null : $object;
    }

    public function rowCount(): int
    {
        return $this->statement?->rowCount() ?? 0;
    }

    public function getPdoStatement(): ?PDOStatement
    {
        return $this->statement;
    }
}
