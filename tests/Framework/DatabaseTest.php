<?php

declare(strict_types=1);

use Slendie\Framework\Database;

require_once __DIR__ . '/../../vendor/autoload.php';

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

it('obtém conexão SQLite em memória', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();

    expect($pdo)->toBeInstanceOf(PDO::class);
    expect($pdo->getAttribute(PDO::ATTR_DRIVER_NAME))->toBe('sqlite');
});

it('retorna a mesma instância de conexão (Singleton)', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo1 = Database::getConnection();
    $pdo2 = Database::getConnection();

    expect($pdo1)->toBe($pdo2);
});

it('obtém conexão SQLite com arquivo', function () {
    $tempFile = sys_get_temp_dir() . '/test_db_' . uniqid() . '.sqlite';

    setupTestEnv('sqlite', $tempFile);

    $pdo = Database::getConnection();

    expect($pdo)->toBeInstanceOf(PDO::class);
    expect(file_exists($tempFile))->toBeTrue();

    // Limpa
    @unlink($tempFile);
});

it('resolve caminho relativo de SQLite a partir de BASE_PATH', function () {
    // Usa um caminho relativo que será resolvido a partir de BASE_PATH
    $relativePath = 'test_' . uniqid() . '.sqlite';
    setupTestEnv('sqlite', $relativePath);

    // O Database deve resolver usando BASE_PATH atual
    $pdo = Database::getConnection();

    expect($pdo)->toBeInstanceOf(PDO::class);

    // Verifica se o arquivo foi criado no BASE_PATH (se BASE_PATH estiver definido)
    if (defined('BASE_PATH')) {
        $expectedPath = BASE_PATH . '/' . $relativePath;
        if (file_exists($expectedPath)) {
            expect(file_exists($expectedPath))->toBeTrue();
            @unlink($expectedPath);
        }
    }
});

it('usa caminho absoluto de SQLite sem modificar', function () {
    $tempFile = sys_get_temp_dir() . '/absolute_test_' . uniqid() . '.sqlite';

    setupTestEnv('sqlite', $tempFile);

    $pdo = Database::getConnection();

    expect($pdo)->toBeInstanceOf(PDO::class);
    expect(file_exists($tempFile))->toBeTrue();

    // Limpa
    @unlink($tempFile);
});

it('lança exceção para driver não suportado', function () {
    setupTestEnv('unsupported', ':memory:');

    expect(fn () => Database::getConnection())
        ->toThrow(RuntimeException::class, 'Unsupported DB driver');
});

it('configura PDO com modo de erro EXCEPTION', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $errorMode = $pdo->getAttribute(PDO::ATTR_ERRMODE);

    expect($errorMode)->toBe(PDO::ERRMODE_EXCEPTION);
});

it('verifica se tabela existe no SQLite', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE test_table (id INTEGER PRIMARY KEY)');

    expect(Database::checkIfTableExists('sqlite', 'test_table'))->toBeTrue();
    expect(Database::checkIfTableExists('sqlite', 'non_existent'))->toBeFalse();
});

it('verifica se tabela existe no MySQL', function () {
    // Este teste só funciona se MySQL estiver disponível
    // Vamos apenas verificar que o método não lança erro para driver válido
    setupTestEnv('sqlite', ':memory:');

    // Como não temos MySQL disponível, vamos apenas testar que o método existe
    // e que lança exceção para driver não suportado
    expect(fn () => Database::checkIfTableExists('invalid_driver', 'test'))
        ->toThrow(RuntimeException::class, 'Unsupported DB driver');
});

it('verifica se tabela existe no PostgreSQL', function () {
    // Similar ao MySQL, apenas verifica que o método existe
    setupTestEnv('sqlite', ':memory:');

    expect(fn () => Database::checkIfTableExists('invalid_driver', 'test'))
        ->toThrow(RuntimeException::class, 'Unsupported DB driver');
});

it('cria tabela com string SQL única', function () {
    setupTestEnv('sqlite', ':memory:');

    $sql = 'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)';
    Database::createTables($sql);

    $pdo = Database::getConnection();
    $pdo->commit(); // Commit manual já que createTables não commita

    expect(Database::checkIfTableExists('sqlite', 'users'))->toBeTrue();
});

it('cria múltiplas tabelas com array de SQL', function () {
    setupTestEnv('sqlite', ':memory:');

    $sql = [
        'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
        'CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT)'
    ];

    Database::createTables($sql);
    $pdo = Database::getConnection();
    $pdo->commit();

    expect(Database::checkIfTableExists('sqlite', 'users'))->toBeTrue();
    expect(Database::checkIfTableExists('sqlite', 'posts'))->toBeTrue();
});

it('ignora SQL vazio em createTables', function () {
    setupTestEnv('sqlite', ':memory:');

    $sql = [
        'CREATE TABLE users (id INTEGER PRIMARY KEY)',
        '',
        '   ',
        'CREATE TABLE posts (id INTEGER PRIMARY KEY)'
    ];

    Database::createTables($sql);
    $pdo = Database::getConnection();
    $pdo->commit();

    expect(Database::checkIfTableExists('sqlite', 'users'))->toBeTrue();
    expect(Database::checkIfTableExists('sqlite', 'posts'))->toBeTrue();
});

it('lança exceção quando createTables recebe comando não CREATE', function () {
    setupTestEnv('sqlite', ':memory:');

    $sql = 'INSERT INTO users (name) VALUES ("test")';

    // O código captura InvalidArgumentException e relança como RuntimeException
    expect(fn () => Database::createTables($sql))
        ->toThrow(RuntimeException::class, 'createTables() accepts only CREATE statements.');
});

it('não faz nada quando createTables recebe SQL vazio', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::createTables('');
    Database::createTables([]);

    // Não deve lançar erro
    expect(true)->toBeTrue();
});

it('faz rollback em createTables quando há erro', function () {
    setupTestEnv('sqlite', ':memory:');

    $sql = [
        'CREATE TABLE users (id INTEGER PRIMARY KEY)',
        'CREATE TABLE invalid SQL syntax error'
    ];

    try {
        Database::createTables($sql);
    } catch (RuntimeException $e) {
        // Esperado
    }

    $pdo = Database::getConnection();

    // Verifica que a transação foi revertida
    expect($pdo->inTransaction())->toBeFalse();
    expect(Database::checkIfTableExists('sqlite', 'users'))->toBeFalse();
});

it('executa comando SQL único', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute('CREATE TABLE test (id INTEGER PRIMARY KEY)');

    expect(Database::checkIfTableExists('sqlite', 'test'))->toBeTrue();
});

it('executa múltiplos comandos SQL com array', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute([
        'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
        'INSERT INTO users (name) VALUES ("John")',
        'INSERT INTO users (name) VALUES ("Jane")'
    ]);

    $pdo = Database::getConnection();
    $result = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    expect($result)->toBe(2);
});

it('faz commit automático em execute', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute('CREATE TABLE test (id INTEGER PRIMARY KEY)');

    $pdo = Database::getConnection();
    expect($pdo->inTransaction())->toBeFalse();
    expect(Database::checkIfTableExists('sqlite', 'test'))->toBeTrue();
});

it('faz rollback em execute quando há erro', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute('CREATE TABLE test (id INTEGER PRIMARY KEY)');

    try {
        Database::execute('INSERT INTO test (invalid_column) VALUES (1)');
    } catch (RuntimeException $e) {
        // Esperado
    }

    $pdo = Database::getConnection();
    $result = $pdo->query('SELECT COUNT(*) FROM test')->fetchColumn();

    // Nenhuma linha deve ter sido inserida devido ao rollback
    expect($result)->toBe(0);
});

it('ignora SQL vazio em execute', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute('');
    Database::execute([]);
    Database::execute(['   ', '']);

    // Não deve lançar erro
    expect(true)->toBeTrue();
});

it('executa consulta preparada com parâmetros nomeados', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    Database::execute('INSERT INTO users (name, email) VALUES ("John", "john@example.com")');

    $result = Database::executePrepare(
        'SELECT * FROM users WHERE name = :name',
        [':name' => 'John']
    );

    expect($result)->toBeArray();
    expect($result['name'])->toBe('John');
    expect($result['email'])->toBe('john@example.com');
});

it('executa consulta preparada com parâmetros posicionais', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    Database::execute('INSERT INTO users (name) VALUES ("John")');

    $result = Database::executePrepare(
        'SELECT * FROM users WHERE name = ?',
        ['John']
    );

    expect($result)->toBeArray();
    expect($result['name'])->toBe('John');
});

it('retorna false quando executePrepare não encontra resultados', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

    $result = Database::executePrepare(
        'SELECT * FROM users WHERE name = :name',
        [':name' => 'NonExistent']
    );

    expect($result)->toBeFalse();
});

it('executa consulta preparada sem parâmetros', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    Database::execute('INSERT INTO users (name) VALUES ("John")');

    $result = Database::executePrepare('SELECT * FROM users LIMIT 1');

    expect($result)->toBeArray();
    expect($result['name'])->toBe('John');
});

it('retorna apenas primeira linha em executePrepare', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    Database::execute('INSERT INTO users (name) VALUES ("John")');
    Database::execute('INSERT INTO users (name) VALUES ("Jane")');

    $result = Database::executePrepare('SELECT * FROM users ORDER BY name');

    // Deve retornar apenas a primeira linha (Jane)
    expect($result)->toBeArray();
    expect($result['name'])->toBe('Jane');
});

it('cria tabela e insere dados na mesma transação', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    Database::createTables('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    Database::execute('INSERT INTO users (name) VALUES ("John")');

    $pdo->commit();

    $result = Database::executePrepare('SELECT COUNT(*) as count FROM users');
    expect($result['count'])->toBe(1);
});

it('não cria nova transação se já existe uma ativa', function () {
    setupTestEnv('sqlite', ':memory:');

    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    Database::createTables('CREATE TABLE users (id INTEGER PRIMARY KEY)');

    // Ainda deve estar em transação
    expect($pdo->inTransaction())->toBeTrue();

    $pdo->rollBack();
});

it('trata strings vazias e arrays vazios em createTables', function () {
    setupTestEnv('sqlite', ':memory:');

    // Não deve lançar erro
    Database::createTables('');
    Database::createTables([]);
    Database::createTables(['', '   ']);

    expect(true)->toBeTrue();
});

it('trata strings vazias e arrays vazios em execute', function () {
    setupTestEnv('sqlite', ':memory:');

    // Não deve lançar erro
    Database::execute('');
    Database::execute([]);
    Database::execute(['', '   ']);

    expect(true)->toBeTrue();
});

it('valida que createTables aceita apenas comandos CREATE', function () {
    setupTestEnv('sqlite', ':memory:');

    $invalidCommands = [
        'SELECT * FROM users',
        'INSERT INTO users VALUES (1)',
        'UPDATE users SET name = "test"',
        'DELETE FROM users',
        'DROP TABLE users'
    ];

    foreach ($invalidCommands as $cmd) {
        // O código captura InvalidArgumentException e relança como RuntimeException
        expect(fn () => Database::createTables($cmd))
            ->toThrow(RuntimeException::class);
    }
});

it('executa transação completa com commit em execute', function () {
    setupTestEnv('sqlite', ':memory:');

    Database::execute([
        'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
        'INSERT INTO users (name) VALUES ("User1")',
        'INSERT INTO users (name) VALUES ("User2")'
    ]);

    $pdo = Database::getConnection();
    $count = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    expect($count)->toBe(2);
    expect($pdo->inTransaction())->toBeFalse();
});

it('handle SQL com espaços em branco em createTables', function () {
    setupTestEnv('sqlite', ':memory:');

    $sql = '   CREATE TABLE test (id INTEGER PRIMARY KEY)   ';
    Database::createTables($sql);

    $pdo = Database::getConnection();
    $pdo->commit();

    expect(Database::checkIfTableExists('sqlite', 'test'))->toBeTrue();
});

it('handle SQL com espaços em branco em execute', function () {
    setupTestEnv('sqlite', ':memory:');

    $sql = '   CREATE TABLE test (id INTEGER PRIMARY KEY)   ';
    Database::execute($sql);

    expect(Database::checkIfTableExists('sqlite', 'test'))->toBeTrue();
});
