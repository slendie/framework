<?php

declare(strict_types=1);

namespace Slendie\Models;

use PDO;
use Slendie\Framework\Database;
use Slendie\Framework\SQL;
use Exception;
use PDOException;

abstract class Model
{
    protected static string $table;

    public static function create(array $data): int|string
    {
        $keys = array_keys($data);
        $cols = implode(',', $keys);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $sql = 'INSERT INTO ' . static::$table . ' (' . $cols . ') VALUES (' . $placeholders . ')';
        try {
            $stmt = self::pdo()->prepare($sql);
            $stmt->execute(array_values($data));
        } catch (PDOException $e) {
            throw new Exception('Database Insertion Error: ' . $e->getMessage());
        }
        return self::pdo()->lastInsertId();
    }

    public static function find(int|string $id): array|null
    {
        $stmt = self::pdo()->prepare('SELECT * FROM ' . static::$table . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function all(): array
    {
        $stmt = self::pdo()->query('SELECT * FROM ' . static::$table);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Adiciona uma condição WHERE
     *
     * @param string $column Nome da coluna
     * @param string|null $condition Operador de comparação (opcional, padrão: '=')
     * @param mixed $value Valor para comparação
     * @return SQL
     */
    public static function where(string $column, mixed $condition = null, mixed $value = null): SQL
    {
        return self::query()->where($column, $condition, $value);
    }

    /**
     * Adiciona uma condição WHERE com OR
     *
     * @param string $column Nome da coluna
     * @param string|null $condition Operador de comparação (opcional, padrão: '=')
     * @param mixed $value Valor para comparação
     * @return SQL
     */
    public static function orWhere(string $column, string|null $condition = null, mixed $value = null): SQL
    {
        return self::query()->orWhere($column, $condition, $value);
    }

    /**
     * Agrupa condições WHERE com parênteses
     *
     * @param callable $callback Função que recebe uma instância SQL para construir condições agrupadas
     * @return SQL
     */
    public static function group($callback): SQL
    {
        return self::query()->group($callback);
    }

    /**
     * Adiciona uma ordenação ORDER BY
     *
     * @param string $column Nome da coluna
     * @param string $direction Direção da ordenação (ASC ou DESC, padrão: ASC)
     * @return SQL
     */
    public static function orderBy(string $column, string $direction = 'ASC'): SQL
    {
        return self::query()->orderBy($column, $direction);
    }

    /**
     * Adiciona uma coluna para GROUP BY
     *
     * @param string $column Nome da coluna
     * @return SQL
     */
    public static function groupBy(string $column): SQL
    {
        return self::query()->groupBy($column);
    }

    /**
     * Define o limite de linhas
     *
     * @param int $rows Número de linhas
     * @return SQL
     */
    public static function limit(int $rows): SQL
    {
        return self::query()->limit($rows);
    }

    public static function update(int|string $id, array $data): bool
    {
        $sets = [];
        $vals = [];
        foreach ($data as $k => $v) {
            $sets[] = "$k = ?";
            $vals[] = $v;
        }
        $vals[] = $id;
        $sql = 'UPDATE ' . static::$table . ' SET ' . implode(',', $sets) . ' WHERE id = ?';
        try {
            $stmt = self::pdo()->prepare($sql);
            return $stmt->execute($vals);
        } catch (PDOException $e) {
            throw new Exception('Database Update Error: ' . $e->getMessage());
        }
    }

    public static function delete(int|string $id): bool
    {
        try {
            $stmt = self::pdo()->prepare('DELETE FROM ' . static::$table . ' WHERE id = ?');
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            throw new Exception('Database Deletion Error: ' . $e->getMessage());
        }
    }

    private static function pdo(): PDO
    {
        return Database::getConnection();
    }

    /**
     * Retorna uma nova instância SQL configurada com a tabela do modelo
     *
     * @return SQL
     */
    private static function query(): SQL
    {
        return (new SQL())->table(static::$table);
    }
}
