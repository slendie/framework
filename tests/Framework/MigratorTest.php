<?php

declare(strict_types=1);

use Slendie\Framework\Migrator;
use Slendie\Framework\Database;

require_once __DIR__ . '/../../vendor/autoload.php';

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

// Função auxiliar para criar diretório de migrações temporário
function createTempMigrationsDir()
{
    $tempDir = sys_get_temp_dir() . '/migrations_test_' . uniqid();
    mkdir($tempDir, 0777, true);
    return $tempDir;
}

// Função auxiliar para criar arquivo de migração temporário
function createMigrationFile($dir, $filename, $content)
{
    $file = $dir . '/' . $filename;
    file_put_contents($file, $content);
    return $file;
}

it('inicializa com diretório padrão quando não especificado', function () {
    setupTestEnv('sqlite', ':memory:');

    $migrator = new Migrator();

    // Verifica que a tabela migrations foi criada
    expect(Database::checkIfTableExists('sqlite', 'migrations'))->toBeTrue();
});

it('inicializa com diretório customizado', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();
    $migrator = new Migrator($tempDir);

    expect(Database::checkIfTableExists('sqlite', 'migrations'))->toBeTrue();

    // Limpa
    removeDirectory($tempDir);
});

it('cria tabela migrations automaticamente', function () {
    setupTestEnv('sqlite', ':memory:');

    $migrator = new Migrator();

    $pdo = Database::getConnection();
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
    $result = $stmt->fetch();

    expect($result)->not->toBeFalse();
    expect($result['name'])->toBe('migrations');
});

it('não cria tabela migrations se já existe', function () {
    setupTestEnv('sqlite', ':memory:');

    // Cria primeira instância
    $migrator1 = new Migrator();

    // Cria segunda instância (não deve dar erro)
    $migrator2 = new Migrator();

    expect(Database::checkIfTableExists('sqlite', 'migrations'))->toBeTrue();
});

it('obtém migrações disponíveis do diretório', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    // Cria arquivos de migração
    createMigrationFile($tempDir, '20240101_000001_create_users.php', '<?php return [];');
    createMigrationFile($tempDir, '20240102_000002_create_posts.php', '<?php return [];');
    createMigrationFile($tempDir, '20240103_000003_create_comments.php', '<?php return [];');

    $migrator = new Migrator($tempDir);
    $available = $migrator->getAvailableMigrations();

    expect($available)->toHaveCount(3);
    expect($available)->toContain('20240101_000001_create_users.php');
    expect($available)->toContain('20240102_000002_create_posts.php');
    expect($available)->toContain('20240103_000003_create_comments.php');

    // Limpa
    removeDirectory($tempDir);
});

it('retorna array vazio quando diretório não existe', function () {
    setupTestEnv('sqlite', ':memory:');

    $nonExistentDir = sys_get_temp_dir() . '/non_existent_' . uniqid();
    $migrator = new Migrator($nonExistentDir);

    $available = $migrator->getAvailableMigrations();

    expect($available)->toBeArray();
    expect($available)->toHaveCount(0);
});

it('retorna apenas arquivos .php do diretório', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    // Cria arquivos .php e outros tipos
    createMigrationFile($tempDir, '20240101_000001_create_users.php', '<?php return [];');
    createMigrationFile($tempDir, 'readme.txt', 'text file');
    createMigrationFile($tempDir, '20240102_000002_create_posts.php', '<?php return [];');

    $migrator = new Migrator($tempDir);
    $available = $migrator->getAvailableMigrations();

    expect($available)->toHaveCount(2);
    expect($available)->not->toContain('readme.txt');

    // Limpa
    removeDirectory($tempDir);
});

it('ordena migrações alfabeticamente', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    // Cria arquivos em ordem diferente
    createMigrationFile($tempDir, '20240103_000003_create_comments.php', '<?php return [];');
    createMigrationFile($tempDir, '20240101_000001_create_users.php', '<?php return [];');
    createMigrationFile($tempDir, '20240102_000002_create_posts.php', '<?php return [];');

    $migrator = new Migrator($tempDir);
    $available = $migrator->getAvailableMigrations();

    expect($available[0])->toBe('20240101_000001_create_users.php');
    expect($available[1])->toBe('20240102_000002_create_posts.php');
    expect($available[2])->toBe('20240103_000003_create_comments.php');

    // Limpa
    removeDirectory($tempDir);
});

it('obtém migrações executadas do banco de dados', function () {
    setupTestEnv('sqlite', ':memory:');

    $migrator = new Migrator();

    // Inicialmente não há migrações executadas
    $executed = $migrator->getExecutedMigrations();
    expect($executed)->toBeArray();
    expect($executed)->toHaveCount(0);
});

it('executa migração simples com sucesso', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY, name TEXT);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    $migrator = new Migrator($tempDir);
    $result = $migrator->run();

    expect($result['success'])->toBeTrue();
    expect($result['executed'])->toBe(1);
    expect($result['total'])->toBe(1);
    expect($result['errors'])->toHaveCount(0);

    // Verifica que a tabela foi criada
    expect(Database::checkIfTableExists('sqlite', 'test_table'))->toBeTrue();

    // Verifica que a migração foi registrada
    $executed = $migrator->getExecutedMigrations();
    expect($executed)->toContain('20240101_000001_create_test.php');

    // Limpa
    removeDirectory($tempDir);
});

it('executa múltiplas migrações em ordem', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migration1 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT);'
];
PHP;

    $migration2 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY, title TEXT);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_users.php', $migration1);
    createMigrationFile($tempDir, '20240102_000002_create_posts.php', $migration2);

    $migrator = new Migrator($tempDir);
    $result = $migrator->run();

    expect($result['success'])->toBeTrue();
    expect($result['executed'])->toBe(2);
    expect($result['total'])->toBe(2);

    // Verifica que ambas as tabelas foram criadas
    expect(Database::checkIfTableExists('sqlite', 'users'))->toBeTrue();
    expect(Database::checkIfTableExists('sqlite', 'posts'))->toBeTrue();

    // Verifica que ambas as migrações foram registradas
    $executed = $migrator->getExecutedMigrations();
    expect($executed)->toContain('20240101_000001_create_users.php');
    expect($executed)->toContain('20240102_000002_create_posts.php');

    // Limpa
    removeDirectory($tempDir);
});

it('executa migração com array de queries', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => [
        'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT);',
        'CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY, title TEXT);'
    ]
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_tables.php', $migrationContent);

    $migrator = new Migrator($tempDir);
    $result = $migrator->run();

    expect($result['success'])->toBeTrue();
    expect($result['executed'])->toBe(1);

    // Verifica que ambas as tabelas foram criadas
    expect(Database::checkIfTableExists('sqlite', 'users'))->toBeTrue();
    expect(Database::checkIfTableExists('sqlite', 'posts'))->toBeTrue();

    // Limpa
    removeDirectory($tempDir);
});

it('não executa migrações já executadas', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    $migrator = new Migrator($tempDir);

    // Executa primeira vez
    $result1 = $migrator->run();
    expect($result1['executed'])->toBe(1);

    // Executa segunda vez (não deve executar novamente)
    $result2 = $migrator->run();
    expect($result2['executed'])->toBe(0);
    expect($result2['message'])->toBe('Nenhuma migração pendente.');

    // Limpa
    removeDirectory($tempDir);
});

it('retorna mensagem quando não há migrações pendentes', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrator = new Migrator($tempDir);
    $result = $migrator->run();

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Nenhuma migração pendente.');
    expect($result['executed'])->toBe(0);

    // Limpa
    removeDirectory($tempDir);
});

it('lança exceção quando arquivo de migração não existe', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrator = new Migrator($tempDir);

    // Tenta executar migração que não existe
    // Isso não deve acontecer normalmente, mas vamos testar o comportamento interno
    // Criando uma migração e depois removendo o arquivo
    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    // Executa uma vez para registrar
    $migrator->run();

    // Remove o arquivo
    unlink($tempDir . '/20240101_000001_create_test.php');

    // Adiciona uma nova migração que referencia a anterior (não vai acontecer)
    // Na verdade, vamos testar diretamente o método runMigration via reflection
    $reflection = new ReflectionClass($migrator);
    $method = $reflection->getMethod('runMigration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($migrator, 'non_existent.php', 1))
        ->toThrow(RuntimeException::class, 'Arquivo de migração não encontrado');

    // Limpa
    removeDirectory($tempDir);
});

it('lança exceção quando migração não suporta driver', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'mysql' => 'CREATE TABLE test_table (id INT PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    $migrator = new Migrator($tempDir);

    $result = $migrator->run();

    expect($result['success'])->toBeFalse();
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0]['migration'])->toBe('20240101_000001_create_test.php');
    expect($result['errors'][0]['error'])->toContain('não possui suporte para o driver');

    // Limpa
    removeDirectory($tempDir);
});

it('lança exceção quando migração retorna formato inválido', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return 'invalid format';
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    $migrator = new Migrator($tempDir);

    $result = $migrator->run();

    expect($result['success'])->toBeFalse();
    expect($result['errors'])->toHaveCount(1);

    // Limpa
    removeDirectory($tempDir);
});

it('lança exceção quando migração não possui queries válidas', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => []
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    $migrator = new Migrator($tempDir);

    $result = $migrator->run();

    expect($result['success'])->toBeFalse();
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0]['error'])->toContain('não possui queries válidas');

    // Limpa
    removeDirectory($tempDir);
});

it('ignora queries vazias em migração', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => [
        'CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY);',
        '',
        '   ',
        'CREATE TABLE IF NOT EXISTS another_table (id INTEGER PRIMARY KEY);'
    ]
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    $migrator = new Migrator($tempDir);
    $result = $migrator->run();

    expect($result['success'])->toBeTrue();
    expect($result['executed'])->toBe(1);

    // Verifica que as tabelas foram criadas (ignorando queries vazias)
    expect(Database::checkIfTableExists('sqlite', 'test_table'))->toBeTrue();
    expect(Database::checkIfTableExists('sqlite', 'another_table'))->toBeTrue();

    // Limpa
    removeDirectory($tempDir);
});

it('interrompe execução quando há erro em uma migração', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migration1 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY);'
];
PHP;

    $migration2 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY);'
];
PHP;

    $migration3 = <<<'PHP'
<?php
return [
    'sqlite' => 'INVALID SQL SYNTAX ERROR'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_users.php', $migration1);
    createMigrationFile($tempDir, '20240102_000002_create_posts.php', $migration2);
    createMigrationFile($tempDir, '20240103_000003_invalid.php', $migration3);

    $migrator = new Migrator($tempDir);
    $result = $migrator->run();

    // Deve ter executado as duas primeiras antes de falhar
    expect($result['success'])->toBeFalse();
    expect($result['executed'])->toBe(2); // Executou as duas primeiras
    expect($result['errors'])->toHaveCount(1);

    // Limpa
    removeDirectory($tempDir);
});

it('executa migrações em transação', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY, name TEXT);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    $migrator = new Migrator($tempDir);
    $result = $migrator->run();

    expect($result['success'])->toBeTrue();

    // Verifica que a migração foi registrada (commit foi feito)
    $executed = $migrator->getExecutedMigrations();
    expect($executed)->toContain('20240101_000001_create_test.php');

    // Limpa
    removeDirectory($tempDir);
});

it('faz rollback quando há erro em transação', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => 'INVALID SQL SYNTAX'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_invalid.php', $migrationContent);

    $migrator = new Migrator($tempDir);
    $result = $migrator->run();

    expect($result['success'])->toBeFalse();

    // Verifica que a migração não foi registrada (rollback foi feito)
    $executed = $migrator->getExecutedMigrations();
    expect($executed)->not->toContain('20240101_000001_invalid.php');

    // Limpa
    removeDirectory($tempDir);
});

it('obtém status das migrações', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migration1 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY);'
];
PHP;

    $migration2 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_users.php', $migration1);
    createMigrationFile($tempDir, '20240102_000002_create_posts.php', $migration2);

    $migrator = new Migrator($tempDir);

    // Status antes de executar
    $statusBefore = $migrator->status();
    expect($statusBefore['total'])->toBe(2);
    expect($statusBefore['executed'])->toBe(0);
    expect($statusBefore['pending'])->toBe(2);

    // Executa uma migração
    $migrator->run();

    // Status depois de executar
    $statusAfter = $migrator->status();
    expect($statusAfter['total'])->toBe(2);
    expect($statusAfter['executed'])->toBe(2);
    expect($statusAfter['pending'])->toBe(0);

    // Limpa
    removeDirectory($tempDir);
});

it('status mostra quais migrações foram executadas', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    $migrator = new Migrator($tempDir);

    // Status antes
    $statusBefore = $migrator->status();
    expect($statusBefore['migrations'][0]['executed'])->toBeFalse();

    // Executa
    $migrator->run();

    // Status depois
    $statusAfter = $migrator->status();
    expect($statusAfter['migrations'][0]['executed'])->toBeTrue();

    // Limpa
    removeDirectory($tempDir);
});

it('faz rollback do último batch', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migration1 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY);'
];
PHP;

    $migration2 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_users.php', $migration1);
    createMigrationFile($tempDir, '20240102_000002_create_posts.php', $migration2);

    $migrator = new Migrator($tempDir);

    // Executa migrações
    $migrator->run();

    $executedBefore = $migrator->getExecutedMigrations();
    expect($executedBefore)->toHaveCount(2);

    // Faz rollback
    $result = $migrator->rollback();

    expect($result['success'])->toBeTrue();
    expect($result['rolled_back'])->toBe(2);

    $executedAfter = $migrator->getExecutedMigrations();
    expect($executedAfter)->toHaveCount(0);

    // Limpa
    removeDirectory($tempDir);
});

it('faz rollback de múltiplos steps', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migration1 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY);'
];
PHP;

    $migration2 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY);'
];
PHP;

    $migration3 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS comments (id INTEGER PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_users.php', $migration1);
    createMigrationFile($tempDir, '20240102_000002_create_posts.php', $migration2);
    createMigrationFile($tempDir, '20240103_000003_create_comments.php', $migration3);

    $migrator = new Migrator($tempDir);

    // Executa todas as migrações (todas no mesmo batch)
    $migrator->run();

    // Faz rollback de 1 step (deve remover todas do último batch)
    $result = $migrator->rollback(1);

    expect($result['success'])->toBeTrue();

    $executedAfter = $migrator->getExecutedMigrations();
    expect($executedAfter)->toHaveCount(0);

    // Limpa
    removeDirectory($tempDir);
});

it('retorna mensagem quando não há migrações para rollback', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrator = new Migrator($tempDir);

    $result = $migrator->rollback();

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Nenhuma migração para fazer rollback.');
    expect($result['rolled_back'])->toBe(0);

    // Limpa
    removeDirectory($tempDir);
});

it('faz reset de todas as migrações', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migration1 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY);'
];
PHP;

    $migration2 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_users.php', $migration1);
    createMigrationFile($tempDir, '20240102_000002_create_posts.php', $migration2);

    $migrator = new Migrator($tempDir);

    // Executa migrações
    $migrator->run();

    $executedBefore = $migrator->getExecutedMigrations();
    expect($executedBefore)->toHaveCount(2);

    // Faz reset
    $result = $migrator->reset();

    expect($result['success'])->toBeTrue();
    expect($result['deleted'])->toBe(2);

    $executedAfter = $migrator->getExecutedMigrations();
    expect($executedAfter)->toHaveCount(0);

    // Limpa
    removeDirectory($tempDir);
});

it('registra migrações com batch correto', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migration1 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY);'
];
PHP;

    $migration2 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_users.php', $migration1);
    createMigrationFile($tempDir, '20240102_000002_create_posts.php', $migration2);

    $migrator = new Migrator($tempDir);

    // Executa primeira vez
    $migrator->run();

    // Adiciona nova migração
    $migration3 = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS comments (id INTEGER PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240103_000003_create_comments.php', $migration3);

    // Executa segunda vez (deve usar batch 2)
    $migrator->run();

    // Verifica que todas foram executadas
    $executed = $migrator->getExecutedMigrations();
    expect($executed)->toHaveCount(3);

    // Limpa
    removeDirectory($tempDir);
});

it('usa driver correto do ambiente', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY);',
    'mysql' => 'CREATE TABLE IF NOT EXISTS test_table (id INT PRIMARY KEY);',
    'pgsql' => 'CREATE TABLE IF NOT EXISTS test_table (id SERIAL PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    $migrator = new Migrator($tempDir);
    $result = $migrator->run();

    expect($result['success'])->toBeTrue();

    // Verifica que a tabela foi criada (usando SQLite)
    expect(Database::checkIfTableExists('sqlite', 'test_table'))->toBeTrue();

    // Limpa
    removeDirectory($tempDir);
});

it('registra created_at ao executar migração', function () {
    setupTestEnv('sqlite', ':memory:');

    $tempDir = createTempMigrationsDir();

    $migrationContent = <<<'PHP'
<?php
return [
    'sqlite' => 'CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY);'
];
PHP;

    createMigrationFile($tempDir, '20240101_000001_create_test.php', $migrationContent);

    $migrator = new Migrator($tempDir);
    $migrator->run();

    // Verifica que created_at foi registrado
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SELECT created_at FROM migrations WHERE migration = '20240101_000001_create_test.php'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    expect($result)->not->toBeFalse();
    expect($result['created_at'])->not->toBeNull();

    // Limpa
    removeDirectory($tempDir);
});
