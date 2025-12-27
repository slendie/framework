<?php

declare(strict_types=1);

use Slendie\Framework\SQL;
use Slendie\Framework\Database;

require_once __DIR__ . '/../../vendor/autoload.php';

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

it('inicializa com tabela vazia', function () {
    $sql = new SQL();
    expect($sql)->toBeInstanceOf(SQL::class);
});

it('inicializa com nome de tabela', function () {
    $sql = new SQL('users');

    $reflection = new ReflectionClass($sql);
    $property = $reflection->getProperty('table');
    $property->setAccessible(true);

    expect($property->getValue($sql))->toBe('users');
});

it('define tabela com método table()', function () {
    $sql = new SQL();
    $result = $sql->table('users');

    expect($result)->toBe($sql); // Retorna self para method chaining

    $reflection = new ReflectionClass($sql);
    $property = $reflection->getProperty('table');
    $property->setAccessible(true);

    expect($property->getValue($sql))->toBe('users');
});

it('adiciona condição WHERE com dois parâmetros', function () {
    $sql = new SQL('users');
    $sql->where('name', 'John');

    $reflection = new ReflectionClass($sql);
    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    $paramsProperty = $reflection->getProperty('params');
    $paramsProperty->setAccessible(true);

    $wheres = $wheresProperty->getValue($sql);
    $params = $paramsProperty->getValue($sql);

    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['column'])->toBe('name');
    expect($wheres[0]['condition'])->toBe('=');
    expect($wheres[0]['type'])->toBe('AND');
    expect($params)->toHaveKey('param_0');
    expect($params['param_0'])->toBe('John');
});

it('adiciona condição WHERE com três parâmetros', function () {
    $sql = new SQL('users');
    $sql->where('age', '>', 18);

    $reflection = new ReflectionClass($sql);
    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    $paramsProperty = $reflection->getProperty('params');
    $paramsProperty->setAccessible(true);

    $wheres = $wheresProperty->getValue($sql);
    $params = $paramsProperty->getValue($sql);

    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['column'])->toBe('age');
    expect($wheres[0]['condition'])->toBe('>');
    expect($wheres[0]['type'])->toBe('AND');
    expect($params['param_0'])->toBe(18);
});

it('adiciona múltiplas condições WHERE com AND', function () {
    $sql = new SQL('users');
    $sql->where('name', 'John')
        ->where('age', '>', 18);

    $reflection = new ReflectionClass($sql);
    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);

    $wheres = $wheresProperty->getValue($sql);

    expect($wheres)->toHaveCount(2);
    expect($wheres[0]['type'])->toBe('AND');
    expect($wheres[1]['type'])->toBe('AND');
});

it('adiciona condição OR WHERE', function () {
    $sql = new SQL('users');
    $sql->orWhere('status', 'active');

    $reflection = new ReflectionClass($sql);
    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);

    $wheres = $wheresProperty->getValue($sql);

    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['type'])->toBe('OR');
    expect($wheres[0]['column'])->toBe('status');
    expect($wheres[0]['condition'])->toBe('=');
});

it('adiciona condição OR WHERE com três parâmetros', function () {
    $sql = new SQL('users');
    $sql->orWhere('age', '<', 65);

    $reflection = new ReflectionClass($sql);
    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);

    $wheres = $wheresProperty->getValue($sql);

    expect($wheres)->toHaveCount(1);
    expect($wheres[0]['type'])->toBe('OR');
    expect($wheres[0]['condition'])->toBe('<');
});

it('combina WHERE e OR WHERE', function () {
    $sql = new SQL('users');
    $sql->where('name', 'John')
        ->orWhere('name', 'Jane');

    $reflection = new ReflectionClass($sql);
    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);

    $wheres = $wheresProperty->getValue($sql);

    expect($wheres)->toHaveCount(2);
    expect($wheres[0]['type'])->toBe('AND');
    expect($wheres[1]['type'])->toBe('OR');
});

it('agrupa condições WHERE com group()', function () {
    $sql = new SQL('users');
    $sql->where('status', 'active')
        ->group(function ($q) {
            $q->where('age', '>', 18)
              ->orWhere('age', '<', 65);
        });

    $reflection = new ReflectionClass($sql);
    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);

    $wheres = $wheresProperty->getValue($sql);

    expect($wheres)->toHaveCount(2);
    expect($wheres[0]['group'])->toBeFalse();
    expect($wheres[1]['group'])->toBeTrue();
    expect($wheres[1])->toHaveKey('conditions');
});

it('group() lança exceção quando callback não é callable', function () {
    $sql = new SQL('users');

    expect(fn () => $sql->group('not a callback'))
        ->toThrow(TypeError::class, 'Argument #1 ($callback) must be of type callable, string given');

});

it('adiciona ORDER BY ASC', function () {
    $sql = new SQL('users');
    $sql->orderBy('name', 'ASC');

    $reflection = new ReflectionClass($sql);
    $orderBysProperty = $reflection->getProperty('orderBys');
    $orderBysProperty->setAccessible(true);

    $orderBys = $orderBysProperty->getValue($sql);

    expect($orderBys)->toHaveCount(1);
    expect($orderBys[0]['column'])->toBe('name');
    expect($orderBys[0]['direction'])->toBe('ASC');
});

it('adiciona ORDER BY DESC', function () {
    $sql = new SQL('users');
    $sql->orderBy('created_at', 'DESC');

    $reflection = new ReflectionClass($sql);
    $orderBysProperty = $reflection->getProperty('orderBys');
    $orderBysProperty->setAccessible(true);

    $orderBys = $orderBysProperty->getValue($sql);

    expect($orderBys[0]['direction'])->toBe('DESC');
});

it('ORDER BY usa ASC como padrão', function () {
    $sql = new SQL('users');
    $sql->orderBy('name');

    $reflection = new ReflectionClass($sql);
    $orderBysProperty = $reflection->getProperty('orderBys');
    $orderBysProperty->setAccessible(true);

    $orderBys = $orderBysProperty->getValue($sql);

    expect($orderBys[0]['direction'])->toBe('ASC');
});

it('ORDER BY normaliza direção para maiúsculas', function () {
    $sql = new SQL('users');
    $sql->orderBy('name', 'desc');

    $reflection = new ReflectionClass($sql);
    $orderBysProperty = $reflection->getProperty('orderBys');
    $orderBysProperty->setAccessible(true);

    $orderBys = $orderBysProperty->getValue($sql);

    expect($orderBys[0]['direction'])->toBe('DESC');
});

it('ORDER BY usa ASC para direção inválida', function () {
    $sql = new SQL('users');
    $sql->orderBy('name', 'INVALID');

    $reflection = new ReflectionClass($sql);
    $orderBysProperty = $reflection->getProperty('orderBys');
    $orderBysProperty->setAccessible(true);

    $orderBys = $orderBysProperty->getValue($sql);

    expect($orderBys[0]['direction'])->toBe('ASC');
});

it('adiciona múltiplos ORDER BY', function () {
    $sql = new SQL('users');
    $sql->orderBy('name', 'ASC')
        ->orderBy('age', 'DESC');

    $reflection = new ReflectionClass($sql);
    $orderBysProperty = $reflection->getProperty('orderBys');
    $orderBysProperty->setAccessible(true);

    $orderBys = $orderBysProperty->getValue($sql);

    expect($orderBys)->toHaveCount(2);
});

it('adiciona GROUP BY', function () {
    $sql = new SQL('users');
    $sql->groupBy('status');

    $reflection = new ReflectionClass($sql);
    $groupBysProperty = $reflection->getProperty('groupBys');
    $groupBysProperty->setAccessible(true);

    $groupBys = $groupBysProperty->getValue($sql);

    expect($groupBys)->toHaveCount(1);
    expect($groupBys[0])->toBe('status');
});

it('adiciona múltiplos GROUP BY', function () {
    $sql = new SQL('users');
    $sql->groupBy('status')
        ->groupBy('role');

    $reflection = new ReflectionClass($sql);
    $groupBysProperty = $reflection->getProperty('groupBys');
    $groupBysProperty->setAccessible(true);

    $groupBys = $groupBysProperty->getValue($sql);

    expect($groupBys)->toHaveCount(2);
});

it('define LIMIT', function () {
    $sql = new SQL('users');
    $sql->limit(10);

    $reflection = new ReflectionClass($sql);
    $limitProperty = $reflection->getProperty('limitValue');
    $limitProperty->setAccessible(true);

    $limit = $limitProperty->getValue($sql);

    expect($limit)->toBe(10);
});

it('get() constrói SQL básico', function () {
    $sql = new SQL('users');
    $query = $sql->get();

    expect($query)->toBe('SELECT * FROM users');
});

it('get() lança exceção quando tabela não está definida', function () {
    $sql = new SQL();

    expect(fn () => $sql->get())
        ->toThrow(RuntimeException::class, 'Table name is required');
});

it('get() aceita colunas customizadas', function () {
    $sql = new SQL('users');
    $query = $sql->get('name, email');

    expect($query)->toBe('SELECT name, email FROM users');
});

it('get() constrói SQL com WHERE', function () {
    $sql = new SQL('users');
    $sql->where('name', 'John');
    $query = $sql->get();

    expect($query)->toContain('WHERE');
    expect($query)->toContain('name');
    expect($query)->toContain(':param_0');
});

it('get() constrói SQL com múltiplas condições WHERE', function () {
    $sql = new SQL('users');
    $sql->where('name', 'John')
        ->where('age', '>', 18);
    $query = $sql->get();

    expect($query)->toContain('WHERE');
    expect($query)->toContain('AND');
});

it('get() constrói SQL com OR WHERE', function () {
    $sql = new SQL('users');
    $sql->where('name', 'John')
        ->orWhere('name', 'Jane');
    $query = $sql->get();

    expect($query)->toContain('OR');
});

it('get() constrói SQL com ORDER BY', function () {
    $sql = new SQL('users');
    $sql->orderBy('name', 'ASC');
    $query = $sql->get();

    expect($query)->toContain('ORDER BY');
    expect($query)->toContain('name ASC');
});

it('get() constrói SQL com múltiplos ORDER BY', function () {
    $sql = new SQL('users');
    $sql->orderBy('name', 'ASC')
        ->orderBy('age', 'DESC');
    $query = $sql->get();

    expect($query)->toContain('ORDER BY');
    expect($query)->toContain('name ASC');
    expect($query)->toContain('age DESC');
});

it('get() constrói SQL com GROUP BY', function () {
    $sql = new SQL('users');
    $sql->groupBy('status');
    $query = $sql->get();

    expect($query)->toContain('GROUP BY');
    expect($query)->toContain('status');
});

it('get() constrói SQL com LIMIT', function () {
    $sql = new SQL('users');
    $sql->limit(10);
    $query = $sql->get();

    expect($query)->toContain('LIMIT');
    expect($query)->toContain('10');
});

it('get() constrói SQL completo com todas as cláusulas', function () {
    $sql = new SQL('users');
    $sql->where('status', 'active')
        ->orderBy('name', 'ASC')
        ->groupBy('role')
        ->limit(10);
    $query = $sql->get();

    expect($query)->toContain('SELECT');
    expect($query)->toContain('FROM users');
    expect($query)->toContain('WHERE');
    expect($query)->toContain('GROUP BY');
    expect($query)->toContain('ORDER BY');
    expect($query)->toContain('LIMIT');
});

it('getParams() retorna parâmetros para prepared statements', function () {
    $sql = new SQL('users');
    $sql->where('name', 'John')
        ->where('age', '>', 18);

    $params = $sql->getParams();

    expect($params)->toBeArray();
    expect($params)->toHaveCount(2);
    expect($params['param_0'])->toBe('John');
    expect($params['param_1'])->toBe(18);
});

it('getParams() retorna array vazio quando não há parâmetros', function () {
    $sql = new SQL('users');

    $params = $sql->getParams();

    expect($params)->toBeArray();
    expect($params)->toHaveCount(0);
});

it('execute() executa query e retorna resultados', function () {
    setupTestEnv('sqlite', ':memory:');

    // Cria tabela e insere dados
    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER)');
    $pdo->exec("INSERT INTO users (name, age) VALUES ('John', 25)");
    $pdo->exec("INSERT INTO users (name, age) VALUES ('Jane', 30)");

    $sql = new SQL('users');
    $sql->where('name', 'John');

    $result = $sql->execute();

    expect($result)->toBeArray();
    expect($result['name'])->toBe('John');
    expect($result['age'])->toBe(25);
});

it('execute() retorna null quando não há resultados', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

    $sql = new SQL('users');
    $sql->where('name', 'NonExistent');

    $result = $sql->execute();

    expect($result)->toBeNull();
});

it('execute() retorna array quando há múltiplos resultados', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER)');
    $pdo->exec("INSERT INTO users (name, age) VALUES ('John', 25)");
    $pdo->exec("INSERT INTO users (name, age) VALUES ('Jane', 30)");

    $sql = new SQL('users');

    $result = $sql->execute();

    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);
});

it('execute() retorna resultado único quando há apenas um resultado', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo->exec("INSERT INTO users (name) VALUES ('John')");

    $sql = new SQL('users');

    $result = $sql->execute();

    // Quando há apenas um resultado, retorna diretamente (não array)
    expect($result)->toBeArray();
    expect($result['name'])->toBe('John');
});

it('execute() aceita colunas customizadas', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    $pdo->exec("INSERT INTO users (name, email) VALUES ('John', 'john@example.com')");

    $sql = new SQL('users');

    $result = $sql->execute('name, email');

    expect($result)->toHaveKey('name');
    expect($result)->toHaveKey('email');
    expect($result)->not->toHaveKey('id');
});

it('first() retorna primeiro resultado', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER)');
    $pdo->exec("INSERT INTO users (name, age) VALUES ('John', 25)");
    $pdo->exec("INSERT INTO users (name, age) VALUES ('Jane', 30)");

    $sql = new SQL('users');
    $sql->orderBy('name', 'ASC');

    $result = $sql->first();

    expect($result)->toBeArray();
    expect($result['name'])->toBe('Jane'); // Ordenado por name ASC, Jane vem antes de John
});

it('first() retorna null quando não há resultados', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

    $sql = new SQL('users');
    $sql->where('name', 'NonExistent');

    $result = $sql->first();

    expect($result)->toBeNull();
});

it('reset() limpa todas as condições', function () {
    $sql = new SQL('users');
    $sql->where('name', 'John')
        ->orderBy('name', 'ASC')
        ->groupBy('status')
        ->limit(10);

    $sql->reset();

    $reflection = new ReflectionClass($sql);
    $wheresProperty = $reflection->getProperty('wheres');
    $wheresProperty->setAccessible(true);
    $orderBysProperty = $reflection->getProperty('orderBys');
    $orderBysProperty->setAccessible(true);
    $groupBysProperty = $reflection->getProperty('groupBys');
    $groupBysProperty->setAccessible(true);
    $limitProperty = $reflection->getProperty('limitValue');
    $limitProperty->setAccessible(true);
    $paramsProperty = $reflection->getProperty('params');
    $paramsProperty->setAccessible(true);
    $paramCounterProperty = $reflection->getProperty('paramCounter');
    $paramCounterProperty->setAccessible(true);

    expect($wheresProperty->getValue($sql))->toHaveCount(0);
    expect($orderBysProperty->getValue($sql))->toHaveCount(0);
    expect($groupBysProperty->getValue($sql))->toHaveCount(0);
    expect($limitProperty->getValue($sql))->toBeNull();
    expect($paramsProperty->getValue($sql))->toHaveCount(0);
    expect($paramCounterProperty->getValue($sql))->toBe(0);
});

it('reset() retorna self para method chaining', function () {
    $sql = new SQL('users');
    $result = $sql->reset();

    expect($result)->toBe($sql);
});

it('group() mescla parâmetros corretamente', function () {
    $sql = new SQL('users');
    $sql->where('status', 'active')
        ->group(function ($q) {
            $q->where('age', '>', 18)
              ->where('age', '<', 65);
        });

    $params = $sql->getParams();

    // Deve ter 3 parâmetros: status, age > 18, age < 65
    expect($params)->toHaveCount(3);
    expect($params['param_0'])->toBe('active');
    expect($params['param_1'])->toBe(18);
    expect($params['param_2'])->toBe(65);
});

it('get() constrói SQL com grupo de condições', function () {
    $sql = new SQL('users');
    $sql->where('status', 'active')
        ->group(function ($q) {
            $q->where('age', '>', 18)
              ->orWhere('age', '<', 65);
        });
    $query = $sql->get();

    expect($query)->toContain('WHERE');
    expect($query)->toContain('(');
    expect($query)->toContain(')');
    expect($query)->toContain('OR');
});

it('execute() funciona com grupo de condições', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, age INTEGER, status TEXT)');
    $pdo->exec("INSERT INTO users (name, age, status) VALUES ('John', 25, 'active')");
    $pdo->exec("INSERT INTO users (name, age, status) VALUES ('Jane', 30, 'active')");
    $pdo->exec("INSERT INTO users (name, age, status) VALUES ('Bob', 70, 'active')");

    $sql = new SQL('users');
    $sql->where('status', 'active')
        ->group(function ($q) {
            $q->where('age', '>', 18)
              ->where('age', '<', 65);
        });

    $result = $sql->execute();

    // Deve retornar John e Jane (age > 18 AND age < 65), mas não Bob (age 70)
    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);
});

it('métodos retornam self para method chaining', function () {
    $sql = new SQL('users');

    $result = $sql->where('name', 'John')
                  ->orWhere('name', 'Jane')
                  ->orderBy('name', 'ASC')
                  ->groupBy('status')
                  ->limit(10);

    expect($result)->toBe($sql);
});

it('getNextParamName() gera nomes únicos de parâmetros', function () {
    $sql = new SQL('users');
    $sql->where('name', 'John')
        ->where('age', 25)
        ->where('email', 'john@example.com');

    $params = $sql->getParams();

    expect($params)->toHaveKey('param_0');
    expect($params)->toHaveKey('param_1');
    expect($params)->toHaveKey('param_2');
});

it('execute() lança exceção com mensagem formatada para tabela não encontrada', function () {
    setupTestEnv('sqlite', ':memory:');

    $sql = new SQL('non_existent_table');

    expect(fn () => $sql->execute())
        ->toThrow(Exception::class, 'Database Query Error');
});

it('first() lança exceção com mensagem formatada para tabela não encontrada', function () {
    setupTestEnv('sqlite', ':memory:');

    $sql = new SQL('non_existent_table');

    expect(fn () => $sql->first())
        ->toThrow(Exception::class, 'Database Query Error');
});

it('get() constrói SQL com condições complexas', function () {
    $sql = new SQL('users');
    $sql->where('status', 'active')
        ->where('role', 'admin')
        ->orWhere('role', 'moderator')
        ->orderBy('created_at', 'DESC')
        ->limit(20);

    $query = $sql->get();

    expect($query)->toContain('WHERE');
    expect($query)->toContain('AND');
    expect($query)->toContain('OR');
    expect($query)->toContain('ORDER BY');
    expect($query)->toContain('LIMIT');
});

it('execute() funciona com condições complexas', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, status TEXT, role TEXT)');
    $pdo->exec("INSERT INTO users (name, status, role) VALUES ('Admin', 'active', 'admin')");
    $pdo->exec("INSERT INTO users (name, status, role) VALUES ('Mod', 'active', 'moderator')");
    $pdo->exec("INSERT INTO users (name, status, role) VALUES ('User', 'inactive', 'user')");

    $sql = new SQL('users');
    $sql->where('status', 'active')
        ->where('role', 'admin')
        ->orWhere('role', 'moderator');

    $result = $sql->execute();

    // Deve retornar Admin e Mod (status=active AND (role=admin OR role=moderator))
    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);
});
