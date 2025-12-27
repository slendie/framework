<?php

declare(strict_types=1);

namespace Slendie\Framework;

use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Classe SQL
 *
 * Constrói queries SQL dinâmicas de forma fluente, similar ao Eloquent do Laravel.
 * Suporta WHERE, OR WHERE, agrupamento de condições, ORDER BY, GROUP BY e LIMIT.
 *
 * @packages Slendie\Framework
 */
final class SQL
{
    /**
     * Nome da tabela
     *
     * @var string
     */
    private string $table;

    /**
     * Condições WHERE
     *
     * @var array
     */
    private array $wheres = [];

    /**
     * Colunas para ORDER BY
     *
     * @var array
     */
    private array $orderBys = [];

    /**
     * Colunas para GROUP BY
     *
     * @var array
     */
    private array $groupBys = [];

    /**
     * Limite de linhas
     *
     * @var int|null
     */
    private int|null $limitValue = null;

    /**
     * Parâmetros para prepared statements
     *
     * @var array
     */
    private array $params = [];

    /**
     * Contador de parâmetros para evitar conflitos
     *
     * @var int
     */
    private int $paramCounter = 0;

    /**
     * Construtor
     *
     * @param string $table Nome da tabela
     */
    public function __construct($table = '')
    {
        $this->table = $table;
    }

    /**
     * Define a tabela
     *
     * @param string $table Nome da tabela
     * @return self
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Adiciona uma condição WHERE
     *
     * Se já existirem outras cláusulas WHERE, usa AND para conectar.
     *
     * @param string $column Nome da coluna
     * @param string|null $condition Operador de comparação (opcional, padrão: '=')
     * @param mixed $value Valor para comparação
     * @return self
     */
    public function where(string $column, mixed $condition = null, mixed $value = null): self
    {
        // Suporta where('coluna', 'valor') ou where('coluna', '=', 'valor')
        if ($value === null) {
            $value = $condition;
            $condition = '=';
        }

        $paramName = $this->getNextParamName();
        $this->params[$paramName] = $value;

        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'condition' => $condition,
            'value' => ':' . $paramName,
            'group' => false
        ];

        return $this;
    }

    /**
     * Adiciona uma condição WHERE com OR
     *
     * @param string $column Nome da coluna
     * @param string|null $condition Operador de comparação (opcional, padrão: '=')
     * @param mixed $value Valor para comparação
     * @return self
     */
    public function orWhere(string $column, string|null $condition = null, mixed $value = null): self
    {
        // Suporta orWhere('coluna', 'valor') ou orWhere('coluna', '=', 'valor')
        if ($value === null) {
            $value = $condition;
            $condition = '=';
        }

        $paramName = $this->getNextParamName();
        $this->params[$paramName] = $value;

        $this->wheres[] = [
            'type' => 'OR',
            'column' => $column,
            'condition' => $condition,
            'value' => ':' . $paramName,
            'group' => false
        ];

        return $this;
    }

    /**
     * Agrupa condições WHERE com parênteses
     *
     * @param callable $callback Função que recebe uma instância SQL para construir condições agrupadas
     * @return self
     */
    public function group(callable $callback): self
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('group() requires a callable parameter');
        }

        // Cria uma nova instância SQL para o grupo
        $groupSQL = new self();
        $callback($groupSQL);

        // Mescla os parâmetros do grupo com novos nomes únicos
        $paramMapping = [];
        foreach ($groupSQL->params as $oldKey => $value) {
            $newKey = $this->getNextParamName();
            $this->params[$newKey] = $value;
            $paramMapping[':' . $oldKey] = ':' . $newKey;
        }

        // Atualiza referências de parâmetros nas condições do grupo
        $groupConditions = [];
        foreach ($groupSQL->wheres as $condition) {
            $updatedCondition = $condition;
            if (isset($condition['value']) && isset($paramMapping[$condition['value']])) {
                $updatedCondition['value'] = $paramMapping[$condition['value']];
            }
            $groupConditions[] = $updatedCondition;
        }

        // Adiciona o grupo às condições WHERE
        $this->wheres[] = [
            'type' => 'AND',
            'group' => true,
            'conditions' => $groupConditions
        ];

        return $this;
    }

    /**
     * Adiciona uma ordenação ORDER BY
     *
     * @param string $column Nome da coluna
     * @param string $direction Direção da ordenação (ASC ou DESC, padrão: ASC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = mb_strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        $this->orderBys[] = [
            'column' => $column,
            'direction' => $direction
        ];

        return $this;
    }

    /**
     * Adiciona uma coluna para GROUP BY
     *
     * @param string $column Nome da coluna
     * @return self
     */
    public function groupBy(string $column): self
    {
        $this->groupBys[] = $column;
        return $this;
    }

    /**
     * Define o limite de linhas
     *
     * @param int $rows Número de linhas
     * @return self
     */
    public function limit(int $rows): self
    {
        $this->limitValue = (int)$rows;
        return $this;
    }

    /**
     * Retorna o SQL final construído
     *
     * @param string $select Colunas para SELECT (padrão: '*')
     * @return string SQL construído
     */
    public function get(string $select = '*'): string
    {
        if (empty($this->table)) {
            throw new RuntimeException('Table name is required. Use table() method to set it.');
        }

        $sql = 'SELECT ' . $select . ' FROM ' . $this->table;

        $where = $this->buildWhere();
        if ($where) {
            $sql .= ' ' . $where;
        }

        $groupBy = $this->buildGroupBy();
        if ($groupBy) {
            $sql .= ' ' . $groupBy;
        }

        $orderBy = $this->buildOrderBy();
        if ($orderBy) {
            $sql .= ' ' . $orderBy;
        }

        $limit = $this->buildLimit();
        if ($limit) {
            $sql .= ' ' . $limit;
        }

        return $sql;
    }

    /**
     * Retorna os parâmetros para prepared statements
     *
     * @return array
     */
    public function getParams(): array|null
    {
        return $this->params;
    }

    /**
     * Executa a query e retorna os resultados
     *
     * Se houver apenas um resultado, retorna esse resultado diretamente.
     * Se houver múltiplos resultados, retorna um array.
     * Se não houver resultados, retorna null.
     *
     * @param string $select Colunas para SELECT (padrão: '*')
     * @return array|array|null Array associativo com os resultados, um único resultado, ou null
     * @throws Exception Se ocorrer erro durante a execução
     */
    public function execute(string $select = '*'): array|null
    {
        try {
            $sql = $this->get($select);
            $params = $this->getParams();

            $pdo = Database::getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Se não houver resultados, retorna null
            if (empty($results)) {
                return null;
            }

            // Se houver apenas um resultado, retorna diretamente
            if (count($results) === 1) {
                return $results[0];
            }

            // Se houver múltiplos resultados, retorna o array
            return $results;
        } catch (PDOException $e) {
            throw new Exception('Database Query Error: ' . $this->formatErrorMessage($e));
        }
    }

    /**
     * Executa a query e retorna apenas o primeiro resultado
     *
     * @param string $select Colunas para SELECT (padrão: '*')
     * @return array|null Array associativo com o primeiro resultado ou null se não houver resultados
     * @throws Exception Se ocorrer erro durante a execução
     */
    public function first(string $select = '*'): array|null
    {
        try {
            $sql = $this->get($select);
            $params = $this->getParams();

            $pdo = Database::getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            throw new Exception('Database Query Error: ' . $this->formatErrorMessage($e));
        }
    }

    /**
     * Reseta a instância para reutilização
     *
     * @return self
     */
    public function reset(): self
    {
        $this->wheres = [];
        $this->orderBys = [];
        $this->groupBys = [];
        $this->limitValue = null;
        $this->params = [];
        $this->paramCounter = 0;
        return $this;
    }

    /**
     * Constrói e retorna a cláusula WHERE
     *
     * @return string
     */
    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $whereParts = [];
        $first = true;

        foreach ($this->wheres as $where) {
            if ($where['group']) {
                // Grupo de condições
                $groupParts = [];
                $groupFirst = true;

                foreach ($where['conditions'] as $condition) {
                    $connector = $groupFirst ? '' : ' ' . $condition['type'] . ' ';
                    $groupParts[] = $connector . $this->buildWhereCondition($condition);
                    $groupFirst = false;
                }

                $connector = $first ? '' : ' ' . $where['type'] . ' ';
                $whereParts[] = $connector . '(' . implode('', $groupParts) . ')';
                $first = false;
            } else {
                // Condição simples
                $connector = $first ? '' : ' ' . $where['type'] . ' ';
                $whereParts[] = $connector . $this->buildWhereCondition($where);
                $first = false;
            }
        }

        return 'WHERE ' . implode('', $whereParts);
    }

    /**
     * Constrói uma condição WHERE individual
     *
     * @param array $where Array com informações da condição
     * @return string
     */
    private function buildWhereCondition(array $where): string
    {
        return $where['column'] . ' ' . $where['condition'] . ' ' . $where['value'];
    }

    /**
     * Constrói a cláusula ORDER BY
     *
     * @return string
     */
    private function buildOrderBy(): string
    {
        if (empty($this->orderBys)) {
            return '';
        }

        $orders = [];
        foreach ($this->orderBys as $orderBy) {
            $orders[] = $orderBy['column'] . ' ' . $orderBy['direction'];
        }

        return 'ORDER BY ' . implode(', ', $orders);
    }

    /**
     * Constrói a cláusula GROUP BY
     *
     * @return string
     */
    private function buildGroupBy(): string
    {
        if (empty($this->groupBys)) {
            return '';
        }

        return 'GROUP BY ' . implode(', ', $this->groupBys);
    }

    /**
     * Constrói a cláusula LIMIT
     *
     * @return string
     */
    private function buildLimit(): string
    {
        if ($this->limitValue === null) {
            return '';
        }

        return 'LIMIT ' . $this->limitValue;
    }

    /**
     * Formata mensagem de erro do banco de dados
     *
     * @param PDOException $e Exceção do PDO
     * @return string Mensagem de erro formatada
     */
    private function formatErrorMessage(PDOException $e): string
    {
        $message = $e->getMessage();

        // Detecta erro de tabela não encontrada
        if (preg_match('/no such table:?\s+(\w+)/i', $message, $matches) ||
            preg_match("/Table\s+['\"]?(\w+)['\"]?\s+doesn't\s+exist/i", $message, $matches) ||
            preg_match("/relation\s+['\"]?(\w+)['\"]?\s+does\s+not\s+exist/i", $message, $matches)) {
            $tableName = $matches[1] ?? 'unknown';
            return sprintf(
                "Tabela '%s' não encontrada. Execute as migrações com: php scripts/migrate.php\nErro original: %s",
                $tableName,
                $message
            );
        }

        return $message;
    }

    /**
     * Gera o próximo nome de parâmetro único
     *
     * @return string
     */
    private function getNextParamName(): string
    {
        return 'param_' . $this->paramCounter++;
    }
}
