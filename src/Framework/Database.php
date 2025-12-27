<?php

declare(strict_types=1);

namespace Slendie\Framework;

use RuntimeException;
use InvalidArgumentException;
use Exception;
use PDO;

/**
 * Classe Database
 *
 * Gerencia conexões e operações com banco de dados usando PDO.
 * Suporta múltiplos drivers: SQLite, MySQL e PostgreSQL.
 * Implementa padrão Singleton para reutilização de conexões.
 *
 * @packages Slendie\Framework
 */
final class Database
{
    /**
     * Instância estática da conexão PDO
     *
     * @var PDO|null
     */
    private static $pdo;

    /**
     * Obtém ou cria uma conexão com o banco de dados
     *
     * Retorna uma instância PDO reutilizável. Se já existe uma conexão,
     * retorna a mesma instância (Singleton). Caso contrário, cria uma nova
     * conexão baseada nas configurações de ambiente.
     *
     * Suporta os seguintes drivers:
     * - sqlite: SQLite (padrão)
     * - mysql: MySQL/MariaDB
     * - pgsql: PostgreSQL
     *
     * @return PDO Instância PDO configurada
     * @throws RuntimeException Se o driver especificado não for suportado
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }

        $driver = env('DB_CONNECTION', 'sqlite');

        if ($driver === 'sqlite') {
            $database = env('DB_DATABASE', ':memory:');

            // Se não for :memory: e não for um caminho absoluto, resolve a partir do BASE_PATH
            if ($database !== ':memory:' && !empty($database)) {
                // Verifica se é um caminho absoluto
                // Windows: C:\ ou C:/, Unix: /
                $isAbsolute = preg_match('/^([A-Z]:[\\\\\/]|\\/)/i', $database);

                if (!$isAbsolute) {
                    // É um caminho relativo, resolve a partir do BASE_PATH
                    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
                    // Constrói o caminho completo e normaliza separadores
                    $database = $basePath . '/' . str_replace('\\', '/', $database);
                }
            }

            $dsn = 'sqlite:' . $database;
            self::$pdo = new PDO($dsn);
        } elseif ($driver === 'mysql') {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $dbname = env('DB_DATABASE', 'app');
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            self::$pdo = new PDO($dsn, env('DB_USERNAME', ''), env('DB_PASSWORD', ''));
        } elseif ($driver === 'pgsql') {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '5432');
            $dbname = env('DB_DATABASE', 'app');
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            self::$pdo = new PDO($dsn, env('DB_USERNAME', ''), env('DB_PASSWORD', ''));
        } else {
            throw new RuntimeException('Unsupported DB driver');
        }

        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return self::$pdo;
    }

    /**
     * Verifica se uma tabela existe no banco de dados
     *
     * Utiliza consultas específicas para cada driver:
     * - SQLite: consulta sqlite_master
     * - MySQL: usa SHOW TABLES
     * - PostgreSQL: usa to_regclass()
     *
     * @param string $driver Nome do driver ('sqlite', 'mysql' ou 'pgsql')
     * @param string $tableName Nome da tabela a verificar
     * @return bool true se a tabela existe, false caso contrário
     * @throws RuntimeException Se o driver especificado não for suportado
     */
    public static function checkIfTableExists(string $driver, string $tableName): bool
    {
        $pdo = self::getConnection();

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:table");
            $stmt->execute([':table' => $tableName]);
            return $stmt->fetchColumn() !== false;
        }
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
            $stmt->execute([':table' => $tableName]);
            return $stmt->fetchColumn() !== false;
        }
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT to_regclass(:table) IS NOT NULL AS exists");
            $stmt->execute([':table' => $tableName]);
            return (bool)$stmt->fetchColumn();
        }
        throw new RuntimeException('Unsupported DB driver');

    }

    /**
     * Cria tabelas no banco de dados
     *
     * Executa apenas comandos CREATE TABLE. Aceita uma string SQL única
     * ou um array de strings SQL. Cada statement é validado para garantir
     * que seja um comando CREATE.
     *
     * As operações são executadas dentro de uma transação. Em caso de erro,
     * a transação é revertida automaticamente.
     *
     * @param string|array $sql String SQL ou array de strings SQL com comandos CREATE
     * @return void
     * @throws InvalidArgumentException Se algum statement não for um comando CREATE
     * @throws RuntimeException Se ocorrer erro durante a execução
     */
    public static function createTables(string|array $sql): void
    {
        if (empty($sql)) {
            return;
        }

        $pdo = self::getConnection();
        $statements = is_array($sql) ? $sql : [$sql];

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $useTransaction = true;
            } else {
                $useTransaction = false;
            }

            foreach ($statements as $stmt) {
                $stmt = mb_trim((string)$stmt);

                if ($stmt === '') {
                    continue;
                }

                if (!preg_match('/^\s*CREATE\s+/i', $stmt)) {
                    throw new InvalidArgumentException('createTables() accepts only CREATE statements.');
                }

                $pdo->exec($stmt);
            }

            // Nota: commit comentado - transação deve ser commitada externamente
            // if ($useTransaction) {
            //     $pdo->commit();
            // }
        } catch (Exception $e) {
            if (isset($useTransaction) && $useTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Executa comandos SQL genéricos
     *
     * Executa uma ou múltiplas instruções SQL. Aceita uma string SQL única
     * ou um array de strings SQL. As operações são executadas dentro de uma
     * transação que é commitada automaticamente em caso de sucesso.
     *
     * Em caso de erro, a transação é revertida automaticamente.
     *
     * @param string|array $sql String SQL ou array de strings SQL a executar
     * @return void
     * @throws RuntimeException Se ocorrer erro durante a execução
     */
    public static function execute(string|array $sql): void
    {
        if (empty($sql)) {
            return;
        }

        $pdo = self::getConnection();
        $statements = is_array($sql) ? $sql : [$sql];

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $useTransaction = true;
            } else {
                $useTransaction = false;
            }

            foreach ($statements as $stmt) {
                $stmt = mb_trim((string)$stmt);

                if ($stmt === '') {
                    continue;
                }

                $pdo->exec($stmt);
            }

            if ($useTransaction) {
                $pdo->commit();
            }
        } catch (Exception $e) {
            if (isset($useTransaction) && $useTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Executa uma consulta SQL preparada
     *
     * Prepara e executa uma consulta SQL com parâmetros nomeados ou posicionais.
     * Retorna apenas a primeira linha de resultados como array associativo.
     *
     * @param string $sql Consulta SQL com placeholders
     * @param array $params Parâmetros para substituir os placeholders
     * @return array|false Array associativo com os resultados ou false se não houver resultados
     */
    public static function executePrepare(string $sql, array $params = []): array|false
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
