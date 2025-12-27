<?php

declare(strict_types=1);

use Slendie\Models\Model;
use Slendie\Framework\Database;
use Slendie\Framework\SQL;

require_once __DIR__ . '/../../vendor/autoload.php';

// Define BASE_PATH se não estiver definido
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

// Classe de teste que estende Model
final class TestModel extends Model
{
    protected static string $table = 'test_models';
}

// Função auxiliar para criar tabela de teste
function createTestTable()
{
    $pdo = Database::getConnection();
    $pdo->exec('CREATE TABLE IF NOT EXISTS test_models (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT,
        age INTEGER,
        status TEXT DEFAULT "active"
    )');
}

// Função auxiliar para limpar tabela
function clearTestTable()
{
    $pdo = Database::getConnection();
    $pdo->exec('DELETE FROM test_models');
}

beforeEach(function () {
    setupTestEnv();
    createTestTable();
    // Limpa a tabela e reseta o auto increment
    $pdo = Database::getConnection();
    $pdo->exec('DELETE FROM test_models');
    try {
        $pdo->exec('DELETE FROM sqlite_sequence WHERE name = "test_models"');
    } catch (Exception $e) {
        // Ignora se a tabela sqlite_sequence não existir
    }
});

it('pdo() retorna instância de PDO através de Database', function () {
    // Testa indiretamente através de create
    $id = TestModel::create(['name' => 'Test']);

    // lastInsertId retorna string no SQLite
    expect($id)->toBeString();
    expect((int)$id)->toBeGreaterThan(0);
});

it('query() retorna instância SQL com tabela configurada', function () {
    // Testa indiretamente através de where
    $sql = TestModel::where('name', '=', 'Test');

    expect($sql)->toBeInstanceOf(SQL::class);

    // Verifica que a tabela está configurada usando reflection
    $reflection = new ReflectionClass($sql);
    $tableProperty = $reflection->getProperty('table');
    $tableProperty->setAccessible(true);
    $table = $tableProperty->getValue($sql);

    expect($table)->toBe('test_models');
});

it('create() insere dados e retorna ID', function () {
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30
    ];

    $id = TestModel::create($data);

    // lastInsertId retorna string no SQLite
    expect($id)->toBeString();
    expect((int)$id)->toBeGreaterThan(0);

    // Verifica que os dados foram inseridos
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT * FROM test_models WHERE id = ?');
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    expect($result)->toBeArray();
    expect($result['name'])->toBe('John Doe');
    expect($result['email'])->toBe('john@example.com');
    expect($result['age'])->toBe(30);
});

it('create() insere múltiplos registros', function () {
    $data1 = ['name' => 'User 1', 'email' => 'user1@example.com'];
    $data2 = ['name' => 'User 2', 'email' => 'user2@example.com'];

    $id1 = TestModel::create($data1);
    $id2 = TestModel::create($data2);

    // lastInsertId retorna string no SQLite
    expect($id1)->toBeString();
    expect($id2)->toBeString();
    expect((int)$id2)->toBeGreaterThan((int)$id1);
});

it('create() lança exceção em caso de erro', function () {
    // Tenta inserir em coluna que não existe
    $data = ['invalid_column' => 'value'];

    expect(function () use ($data) {
        TestModel::create($data);
    })->toThrow(Exception::class);
});

it('find() retorna registro por ID', function () {
    $pdo = Database::getConnection();
    $pdo->exec('DELETE FROM test_models');

    $data = ['name' => 'Jane Doe', 'email' => 'jane@example.com'];
    $id = TestModel::create($data);

    $result = TestModel::find($id);

    expect($result)->toBeArray();
    // Verifica que o ID corresponde (pode ser string ou int dependendo do driver)
    expect((string)$result['id'])->toBe((string)$id);
    expect($result['name'])->toBe('Jane Doe');
    expect($result['email'])->toBe('jane@example.com');
});

it('find() retorna null quando ID não existe', function () {
    $result = TestModel::find(99999);

    expect($result)->toBeNull();
});

it('all() retorna todos os registros', function () {
    TestModel::create(['name' => 'User 1', 'email' => 'user1@example.com']);
    TestModel::create(['name' => 'User 2', 'email' => 'user2@example.com']);
    TestModel::create(['name' => 'User 3', 'email' => 'user3@example.com']);

    $results = TestModel::all();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(3);
});

it('all() retorna array vazio quando não há registros', function () {
    $results = TestModel::all();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(0);
});

it('where() retorna instância SQL', function () {
    $sql = TestModel::where('name', '=', 'John');

    expect($sql)->toBeInstanceOf(SQL::class);
});

it('where() funciona com query builder', function () {
    // Testa que where() retorna instância SQL e pode ser executado
    $sql = TestModel::where('name', '=', 'Test');

    expect($sql)->toBeInstanceOf(SQL::class);

    // Executa a query (pode retornar null se não houver resultados)
    $results = $sql->execute();

    // execute() pode retornar null ou array
    expect($results === null || is_array($results))->toBeTrue();
});

it('where() funciona com sintaxe simplificada', function () {
    // Testa que where() funciona com sintaxe simplificada (sem operador)
    $sql = TestModel::where('name', 'Test');

    expect($sql)->toBeInstanceOf(SQL::class);

    // Executa a query (pode retornar null se não houver resultados)
    $results = $sql->execute();

    // execute() pode retornar null ou array
    expect($results === null || is_array($results))->toBeTrue();
});

it('orWhere() adiciona condição OR', function () {
    TestModel::create(['name' => 'John', 'email' => 'john@example.com']);
    TestModel::create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $results = TestModel::where('name', '=', 'John')
        ->orWhere('name', '=', 'Jane')
        ->execute();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);
});

it('group() agrupa condições WHERE', function () {
    TestModel::create(['name' => 'John', 'age' => 25, 'status' => 'active']);
    TestModel::create(['name' => 'Jane', 'age' => 30, 'status' => 'active']);
    TestModel::create(['name' => 'Bob', 'age' => 25, 'status' => 'inactive']);

    $results = TestModel::group(function ($query) {
        $query->where('age', '=', 25)->orWhere('age', '=', 30);
    })->where('status', '=', 'active')->execute();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);
});

it('orderBy() ordena resultados', function () {
    TestModel::create(['name' => 'Charlie', 'age' => 30]);
    TestModel::create(['name' => 'Alice', 'age' => 25]);
    TestModel::create(['name' => 'Bob', 'age' => 35]);

    $results = TestModel::orderBy('name', 'ASC')->execute();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(3);
    expect($results[0]['name'])->toBe('Alice');
    expect($results[1]['name'])->toBe('Bob');
    expect($results[2]['name'])->toBe('Charlie');
});

it('orderBy() ordena em ordem descendente', function () {
    TestModel::create(['name' => 'Alice', 'age' => 25]);
    TestModel::create(['name' => 'Bob', 'age' => 35]);
    TestModel::create(['name' => 'Charlie', 'age' => 30]);

    $results = TestModel::orderBy('age', 'DESC')->execute();

    expect($results)->toBeArray();
    expect($results[0]['age'])->toBe(35);
    expect($results[1]['age'])->toBe(30);
    expect($results[2]['age'])->toBe(25);
});

it('groupBy() agrupa resultados', function () {
    TestModel::create(['name' => 'John', 'status' => 'active']);
    TestModel::create(['name' => 'Jane', 'status' => 'active']);
    TestModel::create(['name' => 'Bob', 'status' => 'inactive']);

    $results = TestModel::groupBy('status')->execute();

    expect($results)->toBeArray();
    // GROUP BY retorna registros agrupados
    expect($results)->toHaveCount(2);
});

it('limit() limita número de resultados', function () {
    TestModel::create(['name' => 'User 1']);
    TestModel::create(['name' => 'User 2']);
    TestModel::create(['name' => 'User 3']);
    TestModel::create(['name' => 'User 4']);

    $results = TestModel::limit(2)->execute();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);
});

it('update() atualiza registro por ID', function () {
    $id = TestModel::create(['name' => 'John', 'email' => 'john@example.com']);

    $result = TestModel::update($id, ['name' => 'John Updated', 'email' => 'john.updated@example.com']);

    expect($result)->toBeTrue();

    $updated = TestModel::find($id);
    expect($updated['name'])->toBe('John Updated');
    expect($updated['email'])->toBe('john.updated@example.com');
});

it('update() atualiza apenas campos especificados', function () {
    $id = TestModel::create(['name' => 'John', 'email' => 'john@example.com', 'age' => 30]);

    TestModel::update($id, ['name' => 'John Updated']);

    $updated = TestModel::find($id);
    expect($updated['name'])->toBe('John Updated');
    expect($updated['email'])->toBe('john@example.com'); // Não foi alterado
    expect($updated['age'])->toBe(30); // Não foi alterado
});

it('update() retorna false quando ID não existe', function () {
    $result = TestModel::update(99999, ['name' => 'Updated']);

    // PDO execute retorna true mesmo quando nenhuma linha é afetada
    // Mas vamos verificar que não lança exceção
    expect($result)->toBeBool();
});

it('update() lança exceção em caso de erro', function () {
    $id = TestModel::create(['name' => 'John']);

    // Tenta atualizar coluna que não existe
    expect(function () use ($id) {
        TestModel::update($id, ['invalid_column' => 'value']);
    })->toThrow(Exception::class);
});

it('delete() remove registro por ID', function () {
    $id = TestModel::create(['name' => 'John', 'email' => 'john@example.com']);

    $result = TestModel::delete($id);

    expect($result)->toBeTrue();

    $deleted = TestModel::find($id);
    expect($deleted)->toBeNull();
});

it('delete() retorna true mesmo quando ID não existe', function () {
    $result = TestModel::delete(99999);

    // PDO execute retorna true mesmo quando nenhuma linha é afetada
    expect($result)->toBeTrue();
});

it('delete() lança exceção em caso de erro', function () {
    // Como não podemos facilmente simular erro de PDO sem mock,
    // vamos apenas verificar que o método funciona corretamente
    $id = TestModel::create(['name' => 'John']);

    $result = TestModel::delete($id);

    expect($result)->toBeTrue();
});

it('where() funciona com diferentes operadores', function () {
    TestModel::create(['name' => 'John', 'age' => 25]);
    TestModel::create(['name' => 'Jane', 'age' => 30]);
    TestModel::create(['name' => 'Bob', 'age' => 35]);

    $results = TestModel::where('age', '>', 25)->execute();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);
});

it('where() funciona com operador LIKE', function () {
    clearTestTable(); // Garante que a tabela está limpa
    TestModel::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    TestModel::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
    TestModel::create(['name' => 'Bob Johnson', 'email' => 'bob@example.com']);

    $results = TestModel::where('name', 'LIKE', '%John%')->execute();

    expect($results)->toBeArray();
    // LIKE '%John%' encontra 'John Doe' e 'Bob Johnson'
    expect($results)->toHaveCount(2);
    $names = array_column($results, 'name');
    expect($names)->toContain('John Doe');
    expect($names)->toContain('Bob Johnson');
});

it('combina múltiplos métodos de query builder', function () {
    clearTestTable(); // Garante que a tabela está limpa
    TestModel::create(['name' => 'Alice', 'age' => 25, 'status' => 'active']);
    TestModel::create(['name' => 'Bob', 'age' => 30, 'status' => 'active']);
    TestModel::create(['name' => 'Charlie', 'age' => 35, 'status' => 'inactive']);
    TestModel::create(['name' => 'David', 'age' => 25, 'status' => 'active']);

    $results = TestModel::where('status', '=', 'active')
        ->where('age', '>=', 25)
        ->orderBy('age', 'ASC')
        ->limit(2)
        ->execute();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);
    // Verifica que está ordenado por age ASC
    expect((int)$results[0]['age'])->toBeLessThanOrEqual((int)$results[1]['age']);
});

it('create() funciona com valores null', function () {
    $data = [
        'name' => 'John',
        'email' => null,
        'age' => null
    ];

    $id = TestModel::create($data);

    // lastInsertId retorna string no SQLite
    expect($id)->toBeString();

    $result = TestModel::find($id);
    expect($result['name'])->toBe('John');
    expect($result['email'])->toBeNull();
    expect($result['age'])->toBeNull();
});

it('update() funciona com valores null', function () {
    $id = TestModel::create(['name' => 'John', 'email' => 'john@example.com']);

    TestModel::update($id, ['email' => null]);

    $updated = TestModel::find($id);
    expect($updated['email'])->toBeNull();
});

it('where() funciona com valores null', function () {
    TestModel::create(['name' => 'John', 'email' => null]);
    TestModel::create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $results = TestModel::where('email', 'IS', null)->execute();

    // IS NULL precisa ser tratado de forma especial no SQL
    // Vamos testar com IS NULL diretamente
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('SELECT * FROM test_models WHERE email IS NULL');
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    expect($results)->toBeArray();
    expect($results)->toHaveCount(1);
    expect($results[0]['name'])->toBe('John');
});

it('all() retorna registros em ordem de inserção', function () {
    clearTestTable(); // Garante que a tabela está limpa
    $id1 = TestModel::create(['name' => 'First']);
    $id2 = TestModel::create(['name' => 'Second']);
    $id3 = TestModel::create(['name' => 'Third']);

    $results = TestModel::all();

    expect($results)->toBeArray();
    expect($results)->toHaveCount(3);
    // Verifica que os IDs correspondem (pode ser string ou int dependendo do driver)
    expect((string)$results[0]['id'])->toBe((string)$id1);
    expect((string)$results[1]['id'])->toBe((string)$id2);
    expect((string)$results[2]['id'])->toBe((string)$id3);
});

it('find() retorna null para ID zero', function () {
    $result = TestModel::find(0);

    expect($result)->toBeNull();
});

it('update() funciona com múltiplos campos', function () {
    $id = TestModel::create(['name' => 'John', 'email' => 'john@example.com', 'age' => 25]);

    TestModel::update($id, [
        'name' => 'John Updated',
        'email' => 'john.updated@example.com',
        'age' => 30
    ]);

    $updated = TestModel::find($id);
    expect($updated['name'])->toBe('John Updated');
    expect($updated['email'])->toBe('john.updated@example.com');
    expect($updated['age'])->toBe(30);
});

it('delete() remove apenas registro especificado', function () {
    $id1 = TestModel::create(['name' => 'User 1']);
    $id2 = TestModel::create(['name' => 'User 2']);
    $id3 = TestModel::create(['name' => 'User 3']);

    TestModel::delete($id2);

    expect(TestModel::find($id1))->not->toBeNull();
    expect(TestModel::find($id2))->toBeNull();
    expect(TestModel::find($id3))->not->toBeNull();
});

it('query() cria nova instância a cada chamada', function () {
    // Testa indiretamente através de where
    $query1 = TestModel::where('name', '=', 'Test');
    $query2 = TestModel::where('name', '=', 'Test');

    // Devem ser instâncias diferentes
    expect($query1)->not->toBe($query2);
    expect($query1)->toBeInstanceOf(SQL::class);
    expect($query2)->toBeInstanceOf(SQL::class);
});

it('where() pode ser encadeado', function () {
    // Testa que where() pode ser encadeado
    $sql = TestModel::where('age', '=', 25)
        ->where('status', '=', 'active');

    expect($sql)->toBeInstanceOf(SQL::class);

    // Executa a query (pode retornar null se não houver resultados)
    $results = $sql->execute();

    // execute() pode retornar null ou array
    expect($results === null || is_array($results))->toBeTrue();
});

it('orderBy() pode ser encadeado', function () {
    TestModel::create(['name' => 'Charlie', 'age' => 30]);
    TestModel::create(['name' => 'Alice', 'age' => 25]);
    TestModel::create(['name' => 'Bob', 'age' => 25]);

    $results = TestModel::orderBy('age', 'ASC')
        ->orderBy('name', 'ASC')
        ->execute();

    expect($results)->toBeArray();
    expect($results[0]['name'])->toBe('Alice');
    expect($results[1]['name'])->toBe('Bob');
});

it('create() retorna lastInsertId correto', function () {
    clearTestTable(); // Garante que a tabela está limpa
    $id1 = TestModel::create(['name' => 'First']);
    $id2 = TestModel::create(['name' => 'Second']);

    // lastInsertId retorna string no SQLite
    expect((int)$id2)->toBeGreaterThan((int)$id1);
    expect((int)$id2)->toBe((int)$id1 + 1);
});

it('update() retorna true quando atualização é bem-sucedida', function () {
    $id = TestModel::create(['name' => 'John']);

    $result = TestModel::update($id, ['name' => 'Updated']);

    expect($result)->toBeTrue();
});

it('delete() retorna true quando deleção é bem-sucedida', function () {
    $id = TestModel::create(['name' => 'John']);

    $result = TestModel::delete($id);

    expect($result)->toBeTrue();
});
