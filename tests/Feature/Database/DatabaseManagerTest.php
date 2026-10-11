<?php

declare(strict_types=1);

use Slendie\Database\Connection;
use Slendie\Database\ConnectionFactory;
use Slendie\Database\DatabaseManager;
use Slendie\Database\Pagination\AbstractPaginator;
use Slendie\Database\Query\Builder;
use Slendie\Database\Query\Grammars\MySqlGrammar;
use Slendie\Database\Query\Grammars\PgsqlGrammar;
use Slendie\Database\Query\Grammars\SqliteGrammar;
use Slendie\Exceptions\SlendieException;
use Slendie\Foundation\Application;
use Slendie\Http\Request;
use Slendie\Support\Facades\DB;
use Slendie\Tests\Fixtures\Http\HttpHelpers as H;
use Slendie\Tests\Fixtures\View\ViewHelpers as V;

afterEach(fn () => AbstractPaginator::flushResolvers());

function manager(array $connections = [], string $default = 'main'): DatabaseManager
{
    $app = new Application(fixturePath('http'));

    return new DatabaseManager($app, [
        'default' => $default,
        'connections' => $connections + [
            'main' => ['driver' => 'sqlite', 'database' => ':memory:'],
            'other' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ],
    ]);
}

// -----------------------------------------------------------------------------
// DatabaseManager
// -----------------------------------------------------------------------------

it('connection() é preguiçosa e guarda uma Connection por nome', function (): void {
    $m = manager();

    expect($m->getConnections())->toBe([])
        ->and($m->connection())->toBeInstanceOf(Connection::class)
        ->and($m->connection('main'))->toBe($m->connection())
        ->and($m->connection('other'))->not->toBe($m->connection('main'))
        ->and(array_keys($m->getConnections()))->toBe(['main', 'other'])
        ->and($m->connection()->getName())->toBe('main');
});

it('getDefaultConnection / setDefaultConnection', function (): void {
    $m = manager();

    expect($m->getDefaultConnection())->toBe('main');

    $m->setDefaultConnection('other');

    expect($m->getDefaultConnection())->toBe('other')
        ->and($m->connection()->getName())->toBe('other');
});

it('purge e reconnect descartam a ligação (SQLite :memory: perde os dados)', function (): void {
    $m = manager();
    $m->statement('create table t (id integer)');
    $m->table('t')->insert(['id' => 1]);

    $first = $m->connection();
    $m->purge();

    expect($m->getConnections())->toBe([]);

    $second = $m->reconnect();

    expect($second)->not->toBe($first)
        ->and(fn () => $second->table('t')->count())->toThrow(Slendie\Database\QueryException::class);
});

it('__call delega para a ligação por omissão', function (): void {
    $m = manager();

    $m->statement('create table t (id integer, name text)');
    $m->insert('insert into t (id, name) values (?, ?)', [1, 'a']);

    expect($m->select('select * from t'))->toBe([['id' => 1, 'name' => 'a']])
        ->and($m->query('select name from t')->fetch())->toBe(['name' => 'a'])
        ->and($m->table('t'))->toBeInstanceOf(Builder::class)
        ->and($m->transaction(fn () => 5))->toBe(5)
        ->and($m->raw('1'))->toBeInstanceOf(Slendie\Database\Query\Expression::class);
});

it('as ligações são independentes', function (): void {
    $m = manager();

    $m->connection('main')->statement('create table t (id integer)');
    $m->connection('main')->table('t')->insert(['id' => 1]);

    expect($m->connection('main')->table('t')->count())->toBe(1)
        ->and(fn () => $m->connection('other')->table('t')->count())->toThrow(Slendie\Database\QueryException::class);
});

it('lança para ligações desconhecidas ou sem driver', function (): void {
    expect(fn () => manager()->connection('nope'))->toThrow(InvalidArgumentException::class, 'nope')
        ->and(fn () => manager(['bad' => ['host' => 'x']])->connection('bad'))->toThrow(InvalidArgumentException::class, 'driver')
        ->and(fn () => manager(['bad' => ['driver' => 'oracle']])->connection('bad'))->toThrow(InvalidArgumentException::class, 'oracle');
});

it('extend() regista drivers de packages', function (): void {
    $m = manager(['custom' => ['driver' => 'acme', 'tag' => 'x']]);
    $received = null;

    $m->extend('acme', function (array $config, string $name) use (&$received): Connection {
        $received = [$config, $name];

        return new Connection(new PDO('sqlite::memory:'), 'acme', 'acme-db', $config, new SqliteGrammar());
    });

    $connection = $m->connection('custom');

    expect($connection->getDriverName())->toBe('acme')
        ->and($connection->getDatabaseName())->toBe('acme-db')
        ->and($received)->toBe([['driver' => 'acme', 'tag' => 'x'], 'custom'])
        ->and($m->connection('custom'))->toBe($connection);
});

// -----------------------------------------------------------------------------
// ConnectionFactory
// -----------------------------------------------------------------------------

it('a Connection é preguiçosa: só liga na primeira query', function (): void {
    $dir = sys_get_temp_dir().'/slendie-db-'.bin2hex(random_bytes(4));
    $factory = new ConnectionFactory($dir);

    $connection = $factory->make(['driver' => 'sqlite', 'database' => 'x.sqlite']);

    // A pasta ainda não existe e nada falhou.
    expect($connection->getDatabaseName())->toBe($dir.DIRECTORY_SEPARATOR.'x.sqlite')
        ->and(fn () => $connection->select('select 1'))->toThrow(SlendieException::class, 'não existe');
});

it('SQLite: caminho relativo a database/, absoluto e :memory:', function (): void {
    $dir = sys_get_temp_dir().'/slendie-db-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $factory = new ConnectionFactory($dir);

    $relative = $factory->make(['driver' => 'sqlite', 'database' => 'a.sqlite']);
    $absolute = $factory->make(['driver' => 'sqlite', 'database' => $dir.'/b.sqlite']);
    $memory = $factory->make(['driver' => 'sqlite', 'database' => ':memory:']);
    $empty = $factory->make(['driver' => 'sqlite']);

    $relative->statement('create table t (id integer)');
    $absolute->statement('create table t (id integer)');

    expect(is_file($dir.'/a.sqlite'))->toBeTrue()
        ->and(is_file($dir.'/b.sqlite'))->toBeTrue()
        ->and($memory->getDatabaseName())->toBe(':memory:')
        ->and($empty->getDatabaseName())->toBe(':memory:')
        ->and($memory->select('select 1 as x'))->toBe([['x' => 1]]);

    $relative->disconnect();
    $absolute->disconnect();
    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);
});

it('SQLite: foreign keys, busy_timeout e journal_mode', function (): void {
    $dir = sys_get_temp_dir().'/slendie-db-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $factory = new ConnectionFactory($dir);

    $on = $factory->make(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
    $off = $factory->make(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => false]);
    $tuned = $factory->make(['driver' => 'sqlite', 'database' => 'w.sqlite', 'busy_timeout' => 1234, 'journal_mode' => 'wal']);

    expect($on->scalar('pragma foreign_keys'))->toBe(1)
        ->and($off->scalar('pragma foreign_keys'))->toBe(0)
        ->and($tuned->scalar('pragma busy_timeout'))->toBe(1234)
        ->and(strtolower((string) $tuned->scalar('pragma journal_mode')))->toBe('wal');

    $tuned->disconnect();
    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);
});

it('SQLite: as foreign keys são aplicadas', function (): void {
    $c = (new ConnectionFactory())->make(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
    $c->statement('create table p (id integer primary key)');
    $c->statement('create table c (id integer primary key, p_id integer references p(id))');

    expect(fn () => $c->table('c')->insert(['p_id' => 99]))->toThrow(Slendie\Database\QueryException::class, 'FOREIGN KEY');
});

it('opções PDO: ERRMODE_EXCEPTION, FETCH_ASSOC, prepares nativos e STRINGIFY_FETCHES=false', function (): void {
    $pdo = (new ConnectionFactory())->make(['driver' => 'sqlite', 'database' => ':memory:'])->getPdo();

    expect($pdo->getAttribute(PDO::ATTR_ERRMODE))->toBe(PDO::ERRMODE_EXCEPTION)
        ->and($pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE))->toBe(PDO::FETCH_ASSOC);
});

it('a config pode sobrepor opções PDO', function (): void {
    $pdo = (new ConnectionFactory())->make(['driver' => 'sqlite', 'database' => ':memory:', 'options' => [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM]])->getPdo();

    expect($pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE))->toBe(PDO::FETCH_NUM);
});

it('escolhe a grammar pelo driver', function (): void {
    $f = new ConnectionFactory();

    expect($f->createGrammar('sqlite'))->toBeInstanceOf(SqliteGrammar::class)
        ->and($f->createGrammar('mysql'))->toBeInstanceOf(MySqlGrammar::class)
        ->and($f->createGrammar('mariadb'))->toBeInstanceOf(MySqlGrammar::class)
        ->and($f->createGrammar('pgsql'))->toBeInstanceOf(PgsqlGrammar::class)
        ->and($f->make(['driver' => 'pgsql', 'database' => 'x'])->getGrammar())->toBeInstanceOf(PgsqlGrammar::class)
        ->and($f->make(['driver' => 'mysql', 'database' => 'x'])->getDriverName())->toBe('mysql');
});

it('driver PDO em falta dá uma mensagem clara', function (): void {
    if (in_array('pgsql', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('pdo_pgsql está instalado.');
    }

    $c = (new ConnectionFactory())->make(['driver' => 'pgsql', 'database' => 'x']);

    expect(fn () => $c->getPdo())->toThrow(SlendieException::class, 'pdo_pgsql');
});

it('Connection: disconnect volta a ligar com a closure', function (): void {
    $c = (new ConnectionFactory())->make(['driver' => 'sqlite', 'database' => ':memory:']);
    $first = $c->getPdo();

    $c->disconnect();

    expect($c->getPdo())->not->toBe($first);
});

// -----------------------------------------------------------------------------
// Provider, facade e paginação integrada
// -----------------------------------------------------------------------------

function dbApp(): Application
{
    $app = H::app(['database' => [
        'default' => 'sqlite',
        'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']],
    ]]);
    $views = V::tempDir();
    config(['view.paths' => [$views.'/views'], 'view.compiled' => $views.'/cache']);
    Slendie\Support\Facade::setFacadeApplication($app);

    return $app;
}

it('o provider regista db, DatabaseManager e a Connection por omissão', function (): void {
    $app = dbApp();

    expect($app->make('db'))->toBeInstanceOf(DatabaseManager::class)
        ->and($app->make('db'))->toBe($app->make(DatabaseManager::class))
        ->and($app->make(Connection::class))->toBe($app->make('db')->connection())
        ->and($app->make(Connection::class)->getDriverName())->toBe('sqlite');
});

it('a config da framework dá defaults (sqlite) e o projecto vence', function (): void {
    $app = H::app();

    expect(config('database.default'))->toBe('sqlite')
        ->and(array_keys(config('database.connections')))->toBe(['sqlite', 'mysql', 'pgsql'])
        ->and(config('database.migrations'))->toBe('migrations');
});

it('a facade DB', function (): void {
    dbApp();

    DB::statement('create table leads (id integer primary key autoincrement, name text, email text)');
    DB::insert('insert into leads (name, email) values (?, ?)', ['Ana', 'ana@x.pt']);

    expect(DB::query('SELECT * FROM leads WHERE email = ?', ['ana@x.pt'])->fetch())->toMatchArray(['name' => 'Ana'])
        ->and(DB::query('SELECT * FROM leads')->fetchAll())->toHaveCount(1)
        ->and(DB::table('leads')->whereLike('name', 'an')->count())->toBe(1)
        ->and(DB::select('select count(*) as n from leads'))->toBe([['n' => 1]])
        ->and(DB::transaction(fn () => DB::table('leads')->count()))->toBe(1)
        ->and(DB::raw('count(*)'))->toBeInstanceOf(Slendie\Database\Query\Expression::class)
        ->and(DB::connection('sqlite'))->toBeInstanceOf(Connection::class)
        ->and(DB::getDefaultConnection())->toBe('sqlite');
});

it('a paginação lê a página e a query do pedido actual e renderiza links', function (): void {
    $app = dbApp();
    DB::statement('create table leads (id integer primary key autoincrement, name text)');
    DB::table('leads')->insert(array_map(fn (int $i): array => ['name' => "Lead {$i}"], range(1, 45)));

    $app->instance('request', Request::create('/leads?q=ana&page=2'));

    $page = DB::table('leads')->orderBy('id')->paginate(10);

    expect($page->currentPage())->toBe(2)
        ->and($page->total())->toBe(45)
        ->and($page->lastPage())->toBe(5)
        ->and(array_column($page->items(), 'id'))->toBe(range(11, 20))
        ->and($page->url(3))->toBe('/leads?q=ana&page=3');

    $html = $page->links();

    expect($html)->toContain('<nav class="pagination"')
        ->and($html)->toContain('href="/leads?q=ana&amp;page=3"')
        ->and($html)->toContain('<li class="active" aria-current="page"><span>2</span></li>')
        ->and($html)->toContain('rel="prev"')
        ->and($html)->toContain('rel="next"');

    $simple = DB::table('leads')->orderBy('id')->simplePaginate(10)->links('pagination::simple');

    expect($simple)->toContain('Seguinte')->toContain('page=3')
        ->and(DB::table('leads')->where('id', 1)->paginate(10)->links())->toBe('');
});

it('firstOrFail não encontrado vira 404 no Handler', function (): void {
    $app = dbApp();
    DB::statement('create table leads (id integer primary key)');

    try {
        DB::table('leads')->firstOrFail();
        $this->fail('Devia ter lançado.');
    } catch (Slendie\Database\RecordNotFoundException $e) {
        $handler = $app->make(Slendie\Foundation\Exceptions\Handler::class);

        expect($handler->render(Request::create('/x'), $e)->getStatusCode())->toBe(404)
            ->and($handler->render(Request::create('/api/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']), $e)->getStatusCode())->toBe(404);
    }
});
