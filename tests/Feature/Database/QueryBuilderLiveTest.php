<?php

declare(strict_types=1);

use Slendie\Database\Connection;
use Slendie\Database\Pagination\Paginator;
use Slendie\Database\Pagination\SimplePaginator;
use Slendie\Database\QueryException;
use Slendie\Database\RecordNotFoundException;
use Slendie\Tests\Fixtures\Database\DbHelpers as Db;
use Slendie\Tests\Fixtures\Database\Status;

/*
| Os mesmos testes correm em SQLite (:memory:, sempre) e, se DB_TEST_MYSQL_DSN / DB_TEST_PGSQL_DSN
| (+ _USER / _PASSWORD) estiverem definidos, em MySQL e PostgreSQL. Caso contrário, ficam skipped.
*/

dataset('drivers', Db::drivers());

/**
 * Connection real com as tabelas `leads` e `notes` recriadas e 3 leads inseridos.
 */
function liveDb(string $driver, bool $seed = true): Connection
{
    $connection = Db::live($driver);

    if ($connection === null) {
        test()->markTestSkipped(Db::skipReason($driver));
    }

    Db::createTables($connection);

    if ($seed) {
        $connection->table('leads')->insert([
            ['name' => 'Ana Silva', 'email' => 'ana@x.pt', 'votes' => 1, 'status' => 'new', 'created_at' => '2026-05-09 10:00:00'],
            ['name' => 'Rui Silva', 'email' => 'rui@x.pt', 'votes' => 5, 'status' => 'won', 'created_at' => '2026-06-10 11:00:00'],
            ['name' => 'Zé Costa', 'email' => null, 'votes' => 3, 'status' => 'new', 'created_at' => '2025-05-11 12:00:00'],
        ]);
    }

    return $connection;
}

// -----------------------------------------------------------------------------
// Critérios de aceitação do brief
// -----------------------------------------------------------------------------

it('query()->fetch() e ->fetchAll() funcionam como no brief', function (string $driver): void {
    $db = liveDb($driver);

    $lead = $db->query('SELECT * FROM leads WHERE email = ?', ['ana@x.pt'])->fetch();
    $all = $db->query('SELECT name FROM leads WHERE status = ? ORDER BY id', ['new'])->fetchAll();

    expect($lead)->toBeArray()->toMatchArray(['name' => 'Ana Silva', 'votes' => 1])
        ->and($all)->toBe([['name' => 'Ana Silva'], ['name' => 'Zé Costa']])
        ->and($db->query('SELECT * FROM leads WHERE email = ?', ['nao@x.pt'])->fetch())->toBeNull()
        ->and($db->query('SELECT COUNT(*) FROM leads')->fetchColumn())->toBe(3);
})->with('drivers');

it('whereLike + orderBy + paginate gera SQL válido', function (string $driver): void {
    $db = liveDb($driver);

    $page = $db->table('leads')->whereLike('name', 'silva')->orderBy('id', 'desc')->paginate(20, 1);

    expect($page)->toBeInstanceOf(Paginator::class)
        ->and($page->total())->toBe(2)
        ->and($page->lastPage())->toBe(1)
        ->and(array_column($page->items(), 'name'))->toBe(['Rui Silva', 'Ana Silva']);
})->with('drivers');

// -----------------------------------------------------------------------------
// Statement e Connection
// -----------------------------------------------------------------------------

it('Statement: fetch devolve null (não false), fetchObject, rowCount e bindings nomeados', function (string $driver): void {
    $db = liveDb($driver);

    $stmt = $db->query('SELECT name FROM leads WHERE email = :email', ['email' => 'rui@x.pt']);
    $none = $db->query('SELECT name FROM leads WHERE email = :email', [':email' => 'nope']);
    $object = $db->query('SELECT name FROM leads WHERE id = ?', [1])->fetchObject();

    expect($stmt->fetch())->toBe(['name' => 'Rui Silva'])
        ->and($stmt->fetch())->toBeNull()
        ->and($none->fetchAll())->toBe([])
        ->and($object)->toBeInstanceOf(stdClass::class)
        ->and($object->name)->toBe('Ana Silva')
        ->and($db->query('SELECT 1 WHERE 1 = 0')->fetchObject())->toBeNull()
        ->and($db->query('SELECT 1 WHERE 1 = 0')->fetchColumn())->toBeNull()
        ->and($db->update('UPDATE leads SET votes = ? WHERE status = ?', [9, 'new']))->toBe(2)
        ->and($db->query('SELECT 1')->getPdoStatement())->toBeInstanceOf(PDOStatement::class);
})->with('drivers');

it('Connection: select, selectOne, scalar, insert, update, delete, statement, unprepared, lastInsertId', function (string $driver): void {
    $db = liveDb($driver, false);

    expect($db->insert('INSERT INTO leads (name, email) VALUES (?, ?)', ['A', 'a@x.pt']))->toBeTrue();

    $id = $driver === 'pgsql' ? (int) $db->scalar("SELECT currval(pg_get_serial_sequence('leads', 'id'))") : $db->lastInsertId();

    expect((int) $id)->toBe(1)
        ->and($db->selectOne('SELECT name FROM leads WHERE id = ?', [1]))->toBe(['name' => 'A'])
        ->and($db->select('SELECT name FROM leads'))->toBe([['name' => 'A']])
        ->and($db->scalar('SELECT name FROM leads WHERE id = ?', [1]))->toBe('A')
        ->and($db->update('UPDATE leads SET name = ? WHERE id = ?', ['B', 1]))->toBe(1)
        ->and($db->delete('DELETE FROM leads WHERE id = ?', [1]))->toBe(1)
        ->and($db->statement('DELETE FROM leads'))->toBeTrue()
        ->and($db->affectingStatement('DELETE FROM leads'))->toBe(0)
        ->and($db->unprepared("INSERT INTO leads (name) VALUES ('U1'); INSERT INTO leads (name) VALUES ('U2');"))->toBeTrue()
        ->and($db->table('leads')->count())->toBe(2);
})->with('drivers');

it('converte bindings: bool, data, enum e null', function (string $driver): void {
    $db = liveDb($driver, false);

    $db->table('leads')->insert([
        'name' => 'Conv',
        'votes' => true,
        'status' => Status::Won,
        'created_at' => new DateTimeImmutable('2026-01-02 03:04:05'),
    ]);

    $row = $db->table('leads')->first();

    expect($row['votes'])->toBe(1)
        ->and($row['status'])->toBe('won')
        ->and(substr((string) $row['created_at'], 0, 19))->toBe('2026-01-02 03:04:05')
        ->and($db->table('leads')->where('status', Status::Won)->count())->toBe(1)
        ->and($db->table('leads')->where('created_at', new DateTime('2026-01-02 03:04:05'))->count())->toBe(1)
        ->and($db->table('leads')->where('votes', true)->count())->toBe(1);

    $db->table('leads')->update(['status' => null]);

    expect($db->table('leads')->whereNull('status')->count())->toBe(1)
        ->and($db->table('leads')->where('status', null)->count())->toBe(1);
})->with('drivers');

// -----------------------------------------------------------------------------
// Builder: leitura
// -----------------------------------------------------------------------------

it('get/fetchAll e first/fetch são sinónimos; first devolve null sem linhas', function (string $driver): void {
    $db = liveDb($driver);
    $q = fn () => $db->table('leads')->where('votes', '>', 1)->orderBy('id');

    expect($q()->get())->toBe($q()->fetchAll())
        ->and(count($q()->get()))->toBe(2)
        ->and($q()->first())->toBe($q()->fetch())
        ->and($q()->first()['name'])->toBe('Rui Silva')
        ->and($db->table('leads')->where('votes', '>', 100)->first())->toBeNull()
        ->and($db->table('leads')->where('votes', '>', 100)->fetch())->toBeNull()
        ->and($db->table('leads')->where('votes', '>', 100)->get())->toBe([]);
})->with('drivers');

it('firstOrFail lança RecordNotFoundException', function (string $driver): void {
    $db = liveDb($driver);

    expect($db->table('leads')->where('id', 1)->firstOrFail()['name'])->toBe('Ana Silva');

    try {
        $db->table('leads')->where('id', 999)->firstOrFail();
        $this->fail('Devia ter lançado.');
    } catch (RecordNotFoundException $e) {
        expect($e->getTable())->toBe('leads')->and($e->getMessage())->toContain('leads');
    }
})->with('drivers');

it('find, value, pluck (com chave) e select de colunas', function (string $driver): void {
    $db = liveDb($driver);

    expect($db->table('leads')->find(2)['name'])->toBe('Rui Silva')
        ->and($db->table('leads')->find(2, ['name']))->toBe(['name' => 'Rui Silva'])
        ->and($db->table('leads')->find(99))->toBeNull()
        ->and($db->table('leads')->where('id', 3)->value('name'))->toBe('Zé Costa')
        ->and($db->table('leads')->where('id', 99)->value('name'))->toBeNull()
        ->and($db->table('leads')->orderBy('id')->pluck('name'))->toBe(['Ana Silva', 'Rui Silva', 'Zé Costa'])
        ->and($db->table('leads')->orderBy('id')->pluck('name', 'id'))->toBe([1 => 'Ana Silva', 2 => 'Rui Silva', 3 => 'Zé Costa'])
        ->and($db->table('leads')->orderBy('id')->pluck('leads.name as n', 'leads.id'))->toBe([1 => 'Ana Silva', 2 => 'Rui Silva', 3 => 'Zé Costa'])
        ->and($db->table('leads')->select('name', 'votes')->where('id', 1)->first())->toBe(['name' => 'Ana Silva', 'votes' => 1]);
})->with('drivers');

it('agregados', function (string $driver): void {
    $db = liveDb($driver);

    expect($db->table('leads')->count())->toBe(3)
        ->and($db->table('leads')->count('email'))->toBe(2)
        ->and($db->table('leads')->distinct()->count('status'))->toBe(2)
        ->and($db->table('leads')->where('votes', '>', 1)->count())->toBe(2)
        ->and((int) $db->table('leads')->sum('votes'))->toBe(9)
        ->and((float) $db->table('leads')->avg('votes'))->toBe(3.0)
        ->and((int) $db->table('leads')->min('votes'))->toBe(1)
        ->and((int) $db->table('leads')->max('votes'))->toBe(5)
        ->and($db->table('leads')->where('id', 99)->sum('votes'))->toBe(0)
        ->and($db->table('leads')->orderBy('id')->count())->toBe(3)
        ->and($db->table('leads')->groupBy('status')->count())->toBe(2)
        ->and($db->table('leads')->limit(2)->count())->toBe(2);
})->with('drivers');

it('exists / doesntExist', function (string $driver): void {
    $db = liveDb($driver);

    expect($db->table('leads')->where('status', 'won')->exists())->toBeTrue()
        ->and($db->table('leads')->where('status', 'lost')->exists())->toBeFalse()
        ->and($db->table('leads')->where('status', 'lost')->doesntExist())->toBeTrue();
})->with('drivers');

it('where: operadores, in, between, null, column, raw, aninhados, not, or', function (string $driver): void {
    $db = liveDb($driver);
    $names = fn ($q) => $q->orderBy('id')->pluck('name');

    expect($names($db->table('leads')->where('votes', '>=', 3)))->toBe(['Rui Silva', 'Zé Costa'])
        ->and($names($db->table('leads')->where('votes', '<>', 3)))->toBe(['Ana Silva', 'Rui Silva'])
        ->and($names($db->table('leads')->where(['status' => 'new', 'votes' => 3])))->toBe(['Zé Costa'])
        ->and($names($db->table('leads')->whereIn('id', [1, 3])))->toBe(['Ana Silva', 'Zé Costa'])
        ->and($names($db->table('leads')->whereNotIn('id', [1, 3])))->toBe(['Rui Silva'])
        ->and($names($db->table('leads')->whereIn('id', [])))->toBe([])
        ->and($names($db->table('leads')->whereNotIn('id', [])))->toHaveCount(3)
        ->and($names($db->table('leads')->whereBetween('votes', [2, 4])))->toBe(['Zé Costa'])
        ->and($names($db->table('leads')->whereNotBetween('votes', [2, 4])))->toBe(['Ana Silva', 'Rui Silva'])
        ->and($names($db->table('leads')->whereNull('email')))->toBe(['Zé Costa'])
        ->and($names($db->table('leads')->whereNotNull('email')))->toBe(['Ana Silva', 'Rui Silva'])
        ->and($names($db->table('leads')->whereColumn('votes', '>', 'id')))->toBe(['Rui Silva'])
        ->and($names($db->table('leads')->whereRaw('votes > ?', [2])))->toBe(['Rui Silva', 'Zé Costa'])
        ->and($names($db->table('leads')->where('status', 'new')->orWhere('votes', 5)))->toBe(['Ana Silva', 'Rui Silva', 'Zé Costa'])
        ->and($names($db->table('leads')->where(fn ($q) => $q->where('status', 'new')->where('votes', '>', 1))->orWhere('id', 2)))->toBe(['Rui Silva', 'Zé Costa'])
        ->and($names($db->table('leads')->whereNot('status', 'new')))->toBe(['Rui Silva'])
        ->and($names($db->table('leads')->where('votes', '>', 0)->whereNot(fn ($q) => $q->where('id', 1)->orWhere('id', 2))))->toBe(['Zé Costa'])
        ->and($names($db->table('leads')->when(true, fn ($q) => $q->where('id', 1))))->toBe(['Ana Silva'])
        ->and($names($db->table('leads')->unless(true, fn ($q) => $q->where('id', 1))))->toHaveCount(3);
})->with('drivers');

it('whereLike: insensível por defeito, sensível opcional, % automático', function (string $driver): void {
    $db = liveDb($driver);
    $names = fn ($q) => $q->orderBy('id')->pluck('name');

    expect($names($db->table('leads')->whereLike('name', 'silva')))->toBe(['Ana Silva', 'Rui Silva'])
        ->and($names($db->table('leads')->whereLike('name', 'SILVA')))->toBe(['Ana Silva', 'Rui Silva'])
        ->and($names($db->table('leads')->whereLike('name', 'ana%')))->toBe(['Ana Silva'])
        ->and($names($db->table('leads')->whereLike('name', 'Ana%', true)))->toBe(['Ana Silva'])
        ->and($names($db->table('leads')->whereLike('name', 'ana%', true)))->toBe([])
        ->and($names($db->table('leads')->whereNotLike('name', 'silva')))->toBe(['Zé Costa'])
        ->and($names($db->table('leads')->whereLike('name', 'x')->orWhereLike('name', 'costa')))->toBe(['Zé Costa']);
})->with('drivers');

it('whereDate / whereYear / whereMonth / whereDay', function (string $driver): void {
    $db = liveDb($driver);
    $names = fn ($q) => $q->orderBy('id')->pluck('name');

    expect($names($db->table('leads')->whereDate('created_at', '2026-05-09')))->toBe(['Ana Silva'])
        ->and($names($db->table('leads')->whereDate('created_at', new DateTimeImmutable('2026-06-10'))))->toBe(['Rui Silva'])
        ->and($names($db->table('leads')->whereYear('created_at', 2026)))->toBe(['Ana Silva', 'Rui Silva'])
        ->and($names($db->table('leads')->whereYear('created_at', '<', 2026)))->toBe(['Zé Costa'])
        ->and($names($db->table('leads')->whereMonth('created_at', 5)))->toBe(['Ana Silva', 'Zé Costa'])
        ->and($names($db->table('leads')->whereMonth('created_at', '>', 5)))->toBe(['Rui Silva'])
        ->and($names($db->table('leads')->whereDay('created_at', 10)))->toBe(['Rui Silva'])
        ->and($names($db->table('leads')->whereDay('created_at', 9)))->toBe(['Ana Silva']);
})->with('drivers');

it('joins (inner, left, closure) e whereExists', function (string $driver): void {
    $db = liveDb($driver);
    $db->table('notes')->insert([['lead_id' => 1, 'body' => 'olá'], ['lead_id' => 1, 'body' => 'adeus'], ['lead_id' => 2, 'body' => 'x']]);

    $inner = $db->table('leads')->join('notes', 'leads.id', '=', 'notes.lead_id')->orderBy('notes.id')->pluck('notes.body');
    $left = $db->table('leads')->leftJoin('notes', 'leads.id', 'notes.lead_id')->whereNull('notes.id')->pluck('leads.name');
    $closure = $db->table('leads')
        ->join('notes', fn ($j) => $j->on('leads.id', 'notes.lead_id')->where('notes.body', 'x'))
        ->select('leads.name', 'notes.body as note')
        ->get();
    $exists = $db->table('leads')->whereExists(fn ($q) => $q->from('notes')->whereColumn('notes.lead_id', 'leads.id'))->orderBy('id')->pluck('name');
    $notExists = $db->table('leads')->whereNotExists(fn ($q) => $q->from('notes')->whereColumn('notes.lead_id', 'leads.id'))->pluck('name');

    expect($inner)->toBe(['olá', 'adeus', 'x'])
        ->and($left)->toBe(['Zé Costa'])
        ->and($closure)->toBe([['name' => 'Rui Silva', 'note' => 'x']])
        ->and($exists)->toBe(['Ana Silva', 'Rui Silva'])
        ->and($notExists)->toBe(['Zé Costa']);
})->with('drivers');

it('group by, having, order, limit, offset', function (string $driver): void {
    $db = liveDb($driver);

    $grouped = $db->table('leads')->select('status')->selectRaw('count(*) as n')->groupBy('status')->having('n', '>', 1)->get();

    expect($grouped)->toHaveCount(1)
        ->and($grouped[0]['status'])->toBe('new')
        ->and((int) $grouped[0]['n'])->toBe(2)
        ->and($db->table('leads')->orderBy('votes', 'desc')->pluck('name'))->toBe(['Rui Silva', 'Zé Costa', 'Ana Silva'])
        ->and($db->table('leads')->orderByDesc('id')->limit(2)->pluck('id'))->toBe([3, 2])
        ->and($db->table('leads')->orderBy('id')->offset(1)->limit(1)->pluck('id'))->toBe([2])
        ->and($db->table('leads')->orderBy('id')->offset(1)->pluck('id'))->toBe([2, 3])
        ->and($db->table('leads')->orderBy('id')->forPage(2, 2)->pluck('id'))->toBe([3])
        ->and($db->table('leads')->orderBy('id')->skip(2)->take(5)->pluck('id'))->toBe([3])
        ->and($db->table('leads')->latest('id')->first()['id'])->toBe(3)
        ->and($db->table('leads')->oldest('id')->first()['id'])->toBe(1)
        ->and($db->table('leads')->orderBy('id')->reorder('id', 'desc')->first()['id'])->toBe(3)
        ->and($db->table('leads')->inRandomOrder()->limit(1)->get())->toHaveCount(1)
        ->and($db->table('leads')->orderByRaw('votes desc')->first()['name'])->toBe('Rui Silva');
})->with('drivers');

it('paginate: total, lastPage, itens, URLs e toArray', function (string $driver): void {
    $db = liveDb($driver);

    $page2 = $db->table('leads')->orderBy('id')->paginate(2, 2);
    $first = $db->table('leads')->orderBy('id')->paginate(2, 1);
    $beyond = $db->table('leads')->orderBy('id')->paginate(2, 9);
    $none = $db->table('leads')->where('id', 99)->paginate(2);

    expect($page2->total())->toBe(3)
        ->and($page2->lastPage())->toBe(2)
        ->and($page2->currentPage())->toBe(2)
        ->and(array_column($page2->items(), 'id'))->toBe([3])
        ->and($page2->hasMorePages())->toBeFalse()
        ->and($first->hasMorePages())->toBeTrue()
        ->and($first->nextPageUrl())->toContain('page=2')
        ->and($first->toArray())->toMatchArray(['total' => 3, 'per_page' => 2, 'current_page' => 1, 'last_page' => 2, 'from' => 1, 'to' => 2])
        ->and($beyond->items())->toBe([])
        ->and($none->total())->toBe(0)
        ->and($none->lastPage())->toBe(1);
})->with('drivers');

it('simplePaginate sabe se há mais páginas sem contar', function (string $driver): void {
    $db = liveDb($driver);

    $first = $db->table('leads')->orderBy('id')->simplePaginate(2, 1);
    $last = $db->table('leads')->orderBy('id')->simplePaginate(2, 2);

    expect($first)->toBeInstanceOf(SimplePaginator::class)
        ->and(array_column($first->items(), 'id'))->toBe([1, 2])
        ->and($first->hasMorePages())->toBeTrue()
        ->and(array_column($last->items(), 'id'))->toBe([3])
        ->and($last->hasMorePages())->toBeFalse();
})->with('drivers');

it('chunk e cursor', function (string $driver): void {
    $db = liveDb($driver);
    $seen = [];

    $done = $db->table('leads')->orderBy('id')->chunk(2, function (array $rows, int $page) use (&$seen): void {
        $seen[$page] = array_column($rows, 'id');
    });

    $stopped = 0;
    $early = $db->table('leads')->orderBy('id')->chunk(1, function () use (&$stopped): bool {
        $stopped++;

        return false;
    });

    $ids = [];
    foreach ($db->table('leads')->orderBy('id')->cursor() as $row) {
        $ids[] = $row['id'];
    }

    expect($done)->toBeTrue()
        ->and($seen)->toBe([1 => [1, 2], 2 => [3]])
        ->and($early)->toBeFalse()
        ->and($stopped)->toBe(1)
        ->and($ids)->toBe([1, 2, 3]);
})->with('drivers');

// -----------------------------------------------------------------------------
// Builder: escrita
// -----------------------------------------------------------------------------

it('CRUD completo pelo builder', function (string $driver): void {
    $db = liveDb($driver, false);

    expect($db->table('leads')->insert(['name' => 'Um', 'email' => 'um@x.pt']))->toBeTrue()
        ->and($db->table('leads')->insert([['name' => 'Dois', 'email' => 'dois@x.pt'], ['name' => 'Tres', 'email' => 'tres@x.pt']]))->toBeTrue()
        ->and($db->table('leads')->count())->toBe(3)
        ->and($db->table('leads')->where('name', 'Dois')->update(['votes' => 7, 'status' => 'won']))->toBe(1)
        ->and($db->table('leads')->where('name', 'Dois')->first())->toMatchArray(['votes' => 7, 'status' => 'won'])
        ->and($db->table('leads')->where('votes', 0)->update(['status' => 'new']))->toBe(2)
        ->and($db->table('leads')->update([]))->toBe(0)
        ->and($db->table('leads')->where('name', 'Tres')->delete())->toBe(1)
        ->and($db->table('leads')->delete(1))->toBe(1)
        ->and($db->table('leads')->pluck('name'))->toBe(['Dois'])
        ->and($db->table('leads')->delete())->toBe(1)
        ->and($db->table('leads')->count())->toBe(0);
})->with('drivers');

it('insertGetId devolve o id inserido', function (string $driver): void {
    $db = liveDb($driver, false);

    $a = $db->table('leads')->insertGetId(['name' => 'A']);
    $b = $db->table('leads')->insertGetId(['name' => 'B']);

    expect($a)->toBe(1)->and($b)->toBe(2);
})->with('drivers');

it('insertOrIgnore ignora violações de unicidade', function (string $driver): void {
    $db = liveDb($driver, false);

    expect($db->table('leads')->insertOrIgnore(['name' => 'A', 'email' => 'a@x.pt']))->toBe(1)
        ->and($db->table('leads')->insertOrIgnore(['name' => 'B', 'email' => 'a@x.pt']))->toBe(0)
        ->and($db->table('leads')->pluck('name'))->toBe(['A'])
        ->and($db->table('leads')->insertOrIgnore([]))->toBe(0);

    expect(fn () => $db->table('leads')->insert(['name' => 'C', 'email' => 'a@x.pt']))->toThrow(QueryException::class);
})->with('drivers');

it('updateOrInsert', function (string $driver): void {
    $db = liveDb($driver, false);

    $db->table('leads')->updateOrInsert(['email' => 'a@x.pt'], ['name' => 'A', 'votes' => 1]);
    $db->table('leads')->updateOrInsert(['email' => 'a@x.pt'], ['votes' => 2]);
    $db->table('leads')->updateOrInsert(['email' => 'a@x.pt']);

    expect($db->table('leads')->count())->toBe(1)
        ->and($db->table('leads')->first())->toMatchArray(['name' => 'A', 'votes' => 2, 'email' => 'a@x.pt']);
})->with('drivers');

it('increment / decrement', function (string $driver): void {
    $db = liveDb($driver);

    expect($db->table('leads')->where('id', 1)->increment('votes'))->toBe(1)
        ->and($db->table('leads')->where('id', 1)->increment('votes', 4, ['status' => 'won']))->toBe(1)
        ->and($db->table('leads')->where('id', 1)->first())->toMatchArray(['votes' => 6, 'status' => 'won'])
        ->and($db->table('leads')->where('id', 1)->decrement('votes', 2))->toBe(1)
        ->and($db->table('leads')->where('id', 1)->value('votes'))->toBe(4);
})->with('drivers');

it('truncate esvazia e reinicia os ids', function (string $driver): void {
    $db = liveDb($driver);

    $db->table('notes')->truncate();
    $db->table('leads')->truncate();

    expect($db->table('leads')->count())->toBe(0)
        ->and($db->table('leads')->insertGetId(['name' => 'Novo']))->toBe(1);
})->with('drivers');

// -----------------------------------------------------------------------------
// Transacções
// -----------------------------------------------------------------------------

it('transaction: commit devolve o resultado do callback', function (string $driver): void {
    $db = liveDb($driver, false);

    $result = $db->transaction(function (Connection $c): string {
        $c->table('leads')->insert(['name' => 'T']);

        return 'ok';
    });

    expect($result)->toBe('ok')
        ->and($db->table('leads')->count())->toBe(1)
        ->and($db->transactionLevel())->toBe(0);
})->with('drivers');

it('transaction: faz rollback e repropaga a excepção', function (string $driver): void {
    $db = liveDb($driver, false);

    try {
        $db->transaction(function (Connection $c): void {
            $c->table('leads')->insert(['name' => 'T']);

            throw new RuntimeException('falhou');
        });
        $this->fail('Devia ter lançado.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('falhou');
    }

    expect($db->table('leads')->count())->toBe(0)
        ->and($db->transactionLevel())->toBe(0);
})->with('drivers');

it('transaction: rollback também em erros SQL', function (string $driver): void {
    $db = liveDb($driver, false);
    $db->table('leads')->insert(['name' => 'A', 'email' => 'a@x.pt']);

    expect(fn () => $db->transaction(function (Connection $c): void {
        $c->table('leads')->insert(['name' => 'B', 'email' => 'b@x.pt']);
        $c->table('leads')->insert(['name' => 'C', 'email' => 'a@x.pt']);
    }))->toThrow(QueryException::class);

    expect($db->table('leads')->count())->toBe(1);
})->with('drivers');

it('transacções aninhadas usam savepoints: o rollback interno não desfaz o externo', function (string $driver): void {
    $db = liveDb($driver, false);
    $levels = [];

    $db->transaction(function (Connection $c) use (&$levels): void {
        $levels[] = $c->transactionLevel();
        $c->table('leads')->insert(['name' => 'externa']);

        try {
            $c->transaction(function (Connection $c) use (&$levels): void {
                $levels[] = $c->transactionLevel();
                $c->table('leads')->insert(['name' => 'interna']);

                throw new RuntimeException('interna falhou');
            });
        } catch (RuntimeException) {
            $levels[] = $c->transactionLevel();
        }

        $c->transaction(fn (Connection $c) => $c->table('leads')->insert(['name' => 'interna ok']));
    });

    expect($db->table('leads')->orderBy('id')->pluck('name'))->toBe(['externa', 'interna ok'])
        ->and($levels)->toBe([1, 2, 1])
        ->and($db->transactionLevel())->toBe(0);
})->with('drivers');

it('beginTransaction / commit / rollBack manuais', function (string $driver): void {
    $db = liveDb($driver, false);

    $db->beginTransaction();
    $db->table('leads')->insert(['name' => 'A']);
    $db->rollBack();

    $db->beginTransaction();
    $db->table('leads')->insert(['name' => 'B']);
    $db->commit();

    $db->commit();
    $db->rollBack();

    expect($db->table('leads')->pluck('name'))->toBe(['B']);
})->with('drivers');

it('transaction: tenta de novo em bloqueios (attempts)', function (string $driver): void {
    $db = liveDb($driver, false);
    $calls = 0;

    $result = $db->transaction(function () use (&$calls): int {
        if (++$calls < 3) {
            throw new RuntimeException('Deadlock found when trying to get lock');
        }

        return $calls;
    }, 3);

    expect($result)->toBe(3);

    $calls = 0;
    expect(function () use ($db, &$calls): void {
        $db->transaction(function () use (&$calls): void {
            $calls++;

            throw new RuntimeException('erro normal');
        }, 3);
    })->toThrow(RuntimeException::class);

    expect($calls)->toBe(1);
})->with('drivers');

// -----------------------------------------------------------------------------
// Excepções, log, listeners, pretend
// -----------------------------------------------------------------------------

it('QueryException traz SQL, bindings e a dica de migração para tabelas inexistentes', function (string $driver): void {
    $db = liveDb($driver, false);

    try {
        $db->table('tabela_que_nao_existe')->where('a', 'x')->get();
        $this->fail('Devia ter lançado.');
    } catch (QueryException $e) {
        expect($e->getSql())->toContain('tabela_que_nao_existe')
            ->and($e->getBindings())->toBe(['x'])
            ->and($e->getMessage())->toContain("'x'")
            ->and($e->getMessage())->toContain("Tabela 'tabela_que_nao_existe' não existe. Correu `php slendie migrate`?")
            ->and($e->getPrevious())->toBeInstanceOf(PDOException::class);
    }

    try {
        $db->query('SELECT nada FROM leads');
        $this->fail('Devia ter lançado.');
    } catch (QueryException $e) {
        expect($e->getMessage())->not->toContain('migrate');
    }
})->with('drivers');

it('query log regista sql, bindings e tempo', function (string $driver): void {
    $db = liveDb($driver);
    $heard = [];
    $db->listen(function (string $sql, array $bindings, float $ms) use (&$heard): void {
        $heard[] = [$sql, $bindings, $ms];
    });

    $db->enableQueryLog();
    $db->table('leads')->where('id', 1)->get();
    $db->table('leads')->where('votes', true)->count();
    $db->disableQueryLog();
    $db->table('leads')->count();

    $log = $db->getQueryLog();

    expect($db->logging())->toBeFalse()
        ->and($log)->toHaveCount(2)
        ->and($log[0]['sql'])->toBe(Db::builder($driver, 'leads')->where('id', 1)->toSql())
        ->and($log[0]['bindings'])->toBe([1])
        ->and($log[1]['bindings'])->toBe([1])
        ->and($log[0]['time_ms'])->toBeFloat()->toBeGreaterThanOrEqual(0.0)
        ->and($heard)->toHaveCount(3);

    $db->flushQueryLog();

    expect($db->getQueryLog())->toBe([]);
})->with('drivers');

it('pretend devolve as queries sem as executar', function (string $driver): void {
    $db = liveDb($driver);

    $log = $db->pretend(function (Connection $c): void {
        $c->table('leads')->where('id', 1)->delete();
        $c->table('leads')->insert(['name' => 'Fantasma']);
        $c->transaction(fn () => $c->table('leads')->update(['votes' => 99]));
    });

    expect(array_column($log, 'sql'))->toHaveCount(3)
        ->and($db->pretending())->toBeFalse()
        ->and($db->table('leads')->count())->toBe(3)
        ->and($db->table('leads')->where('votes', 99)->exists())->toBeFalse()
        ->and($db->transactionLevel())->toBe(0);
})->with('drivers');

it('toSql/toRawSql não executam nada', function (string $driver): void {
    $db = liveDb($driver);
    $q = $db->table('leads')->where('name', "O'Neil")->whereIn('id', [1, 2]);

    expect($q->toRawSql())->toContain("'O''Neil'")->toContain('in (1, 2)')
        ->and($q->getBindings())->toBe(["O'Neil", 1, 2])
        ->and($db->table('leads')->count())->toBe(3);
})->with('drivers');

it('uma injecção de SQL no valor é tratada como texto', function (string $driver): void {
    $db = liveDb($driver);
    $evil = "x'; DROP TABLE leads; --";

    expect($db->table('leads')->where('name', $evil)->count())->toBe(0)
        ->and($db->table('leads')->whereLike('name', $evil)->count())->toBe(0)
        ->and($db->query('SELECT * FROM leads WHERE name = ?', [$evil])->fetchAll())->toBe([])
        ->and($db->table('leads')->count())->toBe(3);
})->with('drivers');
