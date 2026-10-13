<?php

declare(strict_types=1);

use Slendie\Container\Container;
use Slendie\Http\Request;
use Slendie\Support\Env;
use Slendie\Support\Facade;
use Slendie\Support\Facades\Session as SessionFacade;
use Slendie\Testing\DatabaseState;
use Slendie\Testing\Fakes\FakeUploadedFile;
use Slendie\Testing\Fakes\RecordingHandler;
use Slendie\Tests\Fixtures\GreeterContract;
use Slendie\Tests\Fixtures\Testing\FixtureTestCase;
use Slendie\Tests\Fixtures\Testing\Probe;

uses(FixtureTestCase::class);

it('cada teste tem a sua aplicação e o estado global aponta para ela', function (): void {
    Probe::$seen['app'] = spl_object_id($this->app);

    expect(Container::getInstance())->toBe($this->app)
        ->and(Facade::getFacadeApplication())->toBe($this->app)
        ->and(Env::get('APP_ENV'))->toBe('testing');

    $this->afterApplicationCreated(function (): void {
        Probe::$seen['after'] = true;
    });

    $this->beforeApplicationDestroyed(function (): void {
        Probe::$seen['before_destroy'] = Container::getInstance() === $this->app;
        throw new RuntimeException('um callback partido não pode parar a limpeza');
    });

    expect(Probe::$seen['after'])->toBeTrue();
});

it('...e no fim do teste tudo é deitado fora', function (): void {
    expect(Probe::$seen['before_destroy'])->toBeTrue()
        ->and(spl_object_id($this->app))->not->toBe(Probe::$seen['app'])
        ->and($_SESSION)->toBe([]);
});

it('afterApplicationCreated registado antes de a app existir corre depois dos traits', function (): void {
    $test = new class ('x') extends FixtureTestCase {
        public function register(Closure $callback): void
        {
            $this->afterApplicationCreated($callback);
        }

        public function callbacks(): array
        {
            return $this->afterApplicationCreatedCallbacks;
        }
    };

    $test->register(fn () => null);

    expect($test->callbacks())->toHaveCount(1);
});

it('swap substitui serviços e facades', function (): void {
    $fake = new class () implements GreeterContract {
        public function greet(string $name): string
        {
            return "fake {$name}";
        }
    };

    $this->swap(GreeterContract::class, $fake);

    expect($this->app->make(GreeterContract::class))->toBe($fake);

    $custom = $this->instance('session', new Slendie\Session\Session('s', new Slendie\Session\ArraySessionHandler()));

    expect(SessionFacade::getFacadeRoot())->toBe($custom);
});

it('o handler de excepções embrulhado grava a última excepção', function (): void {
    $handler = $this->app->make(Slendie\Foundation\Exceptions\Handler::class);

    expect($handler)->toBeInstanceOf(RecordingHandler::class);

    /** @var RecordingHandler $handler */
    $this->get('/boom')->assertServerError();

    expect($handler->lastException)->toBeInstanceOf(RuntimeException::class)
        ->and($handler->inner())->not->toBeInstanceOf(RecordingHandler::class)
        ->and($handler->shouldReport(new RuntimeException()))->toBeTrue();

    $this->get('/nada')->assertNotFound();

    expect($handler->lastException)->toBeInstanceOf(Slendie\Exceptions\NotFoundHttpException::class);

    $this->get('/text');

    expect($handler->lastException)->toBeNull();

    $handler->report(new RuntimeException('log'));
});

it('currentSession é nula antes de haver sessão; withSession cria-a', function (): void {
    expect($this->session())->toBeNull()
        ->and($this->session('x', 'padrão'))->toBe('padrão');

    $this->withSession(['x' => 1]);

    expect($this->session('x'))->toBe(1)
        ->and($this->session('y', 'd'))->toBe('d')
        ->and($this->session())->toBeInstanceOf(Slendie\Session\Session::class);
});

it('FakeUploadedFile.create gera ficheiros reais com o tamanho pedido', function (): void {
    $file = FakeUploadedFile::create('doc.pdf', 2);

    expect($file->isValid())->toBeTrue()
        ->and($file->size())->toBe(2048)
        ->and($file->clientName())->toBe('doc.pdf')
        ->and($file->mimeType())->toBe('application/pdf')
        ->and(filesize($file->path()))->toBe(2048);

    expect(FakeUploadedFile::create('vazio.txt')->size())->toBe(0)
        ->and(FakeUploadedFile::create('a.png', 1)->mimeType())->toBe('image/png')
        ->and(FakeUploadedFile::create('a.bin', 0, 'x/y')->mimeType())->toBe('x/y')
        ->and(FakeUploadedFile::create('a.csv')->mimeType())->toBe('text/csv')
        ->and(FakeUploadedFile::create('a.json')->mimeType())->toBe('application/json')
        ->and(FakeUploadedFile::create('a.zip', 1)->mimeType())->toBe('application/zip')
        ->and(FakeUploadedFile::create('a.xyz')->mimeType())->toBe('application/octet-stream');
});

it('FakeUploadedFile.image gera imagens com as dimensões pedidas', function (string $name, string $mime): void {
    $image = FakeUploadedFile::image($name, 20, 10);

    expect($image->mimeType())->toBe($mime)
        ->and($image->isValid())->toBeTrue();

    if (function_exists('getimagesize')) {
        $size = getimagesize($image->path());

        expect($size[0])->toBe(20)->and($size[1])->toBe(10);
    }
})->with([['a.png', 'image/png'], ['a.jpg', 'image/jpeg'], ['a.gif', 'image/gif']]);

it('FakeUploadedFile.image recusa dimensões inválidas e createWithContent escreve o conteúdo', function (): void {
    expect(fn () => FakeUploadedFile::image('a.png', 0, 5))->toThrow(InvalidArgumentException::class);

    $file = FakeUploadedFile::createWithContent('d.csv', "a,b\n1,2\n");

    expect(file_get_contents($file->path()))->toBe("a,b\n1,2\n")
        ->and($file->size())->toBe(8)
        ->and($file->mimeType())->toBe('text/csv');
});

it('um ficheiro falso pode ser guardado (store) como um upload real', function (): void {
    $this->app->useStoragePath(sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-store-'.bin2hex(random_bytes(4)));

    $path = FakeUploadedFile::create('guardar.txt', 1)->store('docs', 'f.txt');

    expect($path)->toBe('docs/f.txt')
        ->and(is_file(storage_path('app/docs/f.txt')))->toBeTrue();

    @unlink(storage_path('app/docs/f.txt'));
});

it('ficheiros em arrays aninhados também chegam ao pedido', function (): void {
    $this->post('/echo', ['title' => 'x', 'docs' => ['a' => FakeUploadedFile::create('a.txt', 1)], 'only' => ['k' => 'v']])
        ->assertOk()
        ->assertJson(['title' => 'x', 'only' => ['k' => 'v']]);

    $request = Request::create('/x', 'POST');

    expect($request->hasFile('docs'))->toBeFalse();
});

it('DatabaseState guarda e esquece ligações', function (): void {
    expect(DatabaseState::get('nada'))->toBeNull();

    $connection = $this->db();
    DatabaseState::put('k', $connection);

    expect(DatabaseState::get('k'))->toBe($connection);

    DatabaseState::flush();

    expect(DatabaseState::get('k'))->toBeNull();
});

it('applicationBootstrapFile procura bootstrap/app.php e falha com uma mensagem útil', function (): void {
    $lost = new class ('x') extends Slendie\Testing\TestCase {
        public function find(): string
        {
            return $this->applicationBootstrapFile();
        }

        public function create(): Slendie\Foundation\Application
        {
            return $this->createApplication();
        }
    };

    $cwd = getcwd();
    chdir(sys_get_temp_dir());

    try {
        expect(fn () => $lost->find())->toThrow(LogicException::class, 'bootstrap/app.php');
    } finally {
        chdir((string) $cwd);
    }
});
