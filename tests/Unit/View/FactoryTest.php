<?php

declare(strict_types=1);

use Slendie\Container\Container;
use Slendie\Contracts\Arrayable;
use Slendie\Contracts\Renderable;
use Slendie\Exceptions\NotFoundHttpException;
use Slendie\Tests\Fixtures\View\ViewHelpers as V;
use Slendie\View\Compiler\BladeCompiler;
use Slendie\View\Finder;
use Slendie\View\View;
use Slendie\View\ViewException;
use Slendie\View\ViewNotFoundException;

beforeEach(function (): void {
    $this->dir = V::tempDir();
    $this->factory = V::factory($this->dir);
});

// -----------------------------------------------------------------------------
// Finder
// -----------------------------------------------------------------------------

it('o Finder resolve nomes com pontos para pastas', function (): void {
    V::write($this->dir, 'layouts.app', 'x');

    $finder = new Finder([$this->dir.'/views']);

    expect($finder->find('layouts.app'))->toEndWith('/layouts/app.blade.php')
        ->and($finder->exists('layouts.app'))->toBeTrue()
        ->and($finder->exists('layouts.nada'))->toBeFalse();
});

it('o Finder procura nas pastas por ordem e a primeira vence', function (): void {
    $other = V::tempDir();
    V::write($this->dir, 'home', 'A');
    V::write($other, 'home', 'B');
    V::write($other, 'so-aqui', 'C');

    $finder = new Finder([$this->dir.'/views', $other.'/views']);

    expect(str_replace(chr(92), '/', $finder->find('home')))->toStartWith(str_replace(chr(92), '/', $this->dir))
        ->and($finder->exists('so-aqui'))->toBeTrue();

    $finder->prependLocation($other.'/views');
    expect(str_replace(chr(92), '/', $finder->find('home')))->toStartWith(str_replace(chr(92), '/', $other));
});

it('ViewNotFoundException lista as pastas procuradas', function (): void {
    try {
        (new Finder(['/a/views', '/b/views']))->find('x.y');
        $this->fail('devia lançar');
    } catch (ViewNotFoundException $e) {
        expect($e->getMessage())->toContain('View [x.y] não encontrada')->toContain('/a/views/x/y.blade.php')->toContain('/b/views/x/y.blade.php')
            ->and($e->getSearched())->toBe(['/a/views/x/y.blade.php', '/b/views/x/y.blade.php']);
    }
});

it('rejeita nomes de view que tentam sair da pasta', function (string $name): void {
    (new Finder([$this->dir.'/views']))->find($name);
})->with(['../segredo', 'a/b', 'a..b', '', 'a b', "a\0b", 'ns::', '::x', 'a::b::c'])->throws(ViewNotFoundException::class);

it('namespaces: pkg::view, prepend e override do projecto', function (): void {
    $pkg = V::tempDir();
    $override = V::tempDir();
    V::write($pkg, 'login', 'pacote');
    V::write($pkg, 'forgot', 'pacote-forgot');
    V::write($override, 'login', 'projecto');

    $this->factory->addNamespace('auth', $pkg.'/views');

    expect($this->factory->render('auth::login'))->toBe('pacote');

    $this->factory->prependNamespace('auth', $override.'/views');

    expect($this->factory->render('auth::login'))->toBe('projecto')
        ->and($this->factory->render('auth::forgot'))->toBe('pacote-forgot')
        ->and($this->factory->exists('auth::nada'))->toBeFalse();
});

it('namespace não registado lança ViewNotFoundException', function (): void {
    $this->factory->make('nope::x');
})->throws(ViewNotFoundException::class, 'namespace de views [nope]');

it('addLocation acrescenta pastas ao Finder', function (): void {
    $extra = V::tempDir();
    V::write($extra, 'extra', 'extra!');

    expect($this->factory->exists('extra'))->toBeFalse();

    $this->factory->addLocation($extra.'/views');

    expect($this->factory->render('extra'))->toBe('extra!');
});

// -----------------------------------------------------------------------------
// Factory / View
// -----------------------------------------------------------------------------

it('make() devolve uma View Renderable/Arrayable com with(), name() e path()', function (): void {
    V::write($this->dir, 'hello', '{{ $a }}-{{ $b }}-{{ $c }}');

    $view = $this->factory->make('hello', ['a' => 1])->with('b', 2)->with(['c' => 3]);

    expect($view)->toBeInstanceOf(View::class)->toBeInstanceOf(Renderable::class)->toBeInstanceOf(Arrayable::class)
        ->and($view->name())->toBe('hello')
        ->and($view->path())->toEndWith('/views/hello.blade.php')
        ->and($view->getData())->toBe(['a' => 1, 'b' => 2, 'c' => 3])
        ->and($view->toArray())->toBe(['a' => 1, 'b' => 2, 'c' => 3])
        ->and($view->render())->toBe('1-2-3')
        ->and((string) $view)->toBe('1-2-3');
});

it('share() disponibiliza dados a todas as views e os dados da view vencem', function (): void {
    V::write($this->dir, 'a', '{{ $site }}/{{ $x }}');
    $this->factory->share('site', 'Slendie');
    $this->factory->share(['x' => 'partilhado']);

    expect($this->factory->render('a'))->toBe('Slendie/partilhado')
        ->and($this->factory->render('a', ['x' => 'local']))->toBe('Slendie/local')
        ->and($this->factory->getShared())->toHaveKeys(['site', 'x', 'errors']);
});

it('composers recebem a View antes de renderizar, com wildcard', function (): void {
    V::write($this->dir, 'admin.users', '{{ $menu }}|{{ $tag ?? "-" }}');
    V::write($this->dir, 'site.home', '{{ $menu ?? "sem" }}');

    $calls = [];
    $this->factory->composer('admin.*', function (View $view) use (&$calls): void {
        $calls[] = $view->name();
        $view->with('menu', 'M');
    });
    $this->factory->composer(['admin.users', 'nao.existe'], fn (View $view) => $view->with('tag', 'T'));

    expect($this->factory->render('admin.users'))->toBe('M|T')
        ->and($this->factory->render('site.home'))->toBe('sem')
        ->and($calls)->toBe(['admin.users']);
});

it('composers em classe: Classe e Classe@metodo via container', function (): void {
    V::write($this->dir, 'c', '{{ $a ?? "" }}{{ $b ?? "" }}');
    $factory = V::factory($this->dir, container: new Container());

    $factory->composer('c', ComposerFixture::class);
    $factory->composer('c', ComposerFixture::class.'@other');

    expect($factory->render('c'))->toBe('AB');
});

it('composers em classe sem container falham com mensagem clara', function (): void {
    V::write($this->dir, 'c', 'x');
    $this->factory->composer('c', ComposerFixture::class);

    $this->factory->render('c');
})->throws(LogicException::class, 'precisam de um container');

it('composers com método inexistente falham', function (): void {
    V::write($this->dir, 'c', 'x');
    $factory = V::factory($this->dir, container: new Container());
    $factory->composer('c', ComposerFixture::class.'@nada');

    $factory->render('c');
})->throws(LogicException::class, 'não tem o método [nada]');

// -----------------------------------------------------------------------------
// Compilação, cache e erros
// -----------------------------------------------------------------------------

it('compila para storage/framework/views/{sha1}.php e reutiliza a cache', function (): void {
    $file = V::write($this->dir, 'cached', 'Olá {{ $n }}');
    $compiler = V::compiler($this->factory);
    $compiled = $compiler->getCompiledPath($file);

    expect($compiled)->toBe($this->dir.'/cache/'.sha1($file).'.php')
        ->and(is_file($compiled))->toBeFalse();

    expect($this->factory->render('cached', ['n' => 1]))->toBe('Olá 1')
        ->and(is_file($compiled))->toBeTrue();

    $mtime = filemtime($compiled);
    clearstatcache();
    touch($compiled, $mtime + 100);
    $before = file_get_contents($compiled);

    expect($this->factory->render('cached', ['n' => 2]))->toBe('Olá 2')
        ->and(file_get_contents($compiled))->toBe($before)
        ->and($compiler->isExpired($file))->toBeFalse();
});

it('recompila quando o ficheiro fonte muda (mtime)', function (): void {
    $file = V::write($this->dir, 'changing', 'versão 1');
    $compiler = V::compiler($this->factory);

    expect($this->factory->render('changing'))->toBe('versão 1');
    touch($compiler->getCompiledPath($file), time() + 100);
    expect($compiler->isExpired($file))->toBeFalse();

    file_put_contents($file, 'versão 2');
    touch($file, time() + 200);

    expect($compiler->isExpired($file))->toBeTrue()
        ->and($this->factory->render('changing'))->toBe('versão 2');
});

it('com debug recompila quando o conteúdo muda mesmo com mtime antigo', function (): void {
    $factory = V::factory($this->dir, debug: true);
    $compiler = V::compiler($factory);
    $file = V::write($this->dir, 'dbg', 'um');

    expect($factory->render('dbg'))->toBe('um');

    // compilada "no futuro" e fonte com mtime antigo: só o hash do conteúdo denuncia a mudança
    touch($compiler->getCompiledPath($file), time() + 500);
    file_put_contents($file, 'dois');
    touch($file, time() - 500);

    expect($compiler->isExpired($file))->toBeTrue()
        ->and($factory->render('dbg'))->toBe('dois');

    // sem debug o mesmo cenário continua a servir a cache
    $production = new BladeCompiler($this->dir.'/cache', debug: false);
    touch($production->getCompiledPath($file), time() + 500);
    expect($production->isExpired($file))->toBeFalse();
});

it('clearCache() apaga as views compiladas', function (): void {
    V::write($this->dir, 'a', 'a');
    V::write($this->dir, 'b', 'b');
    $this->factory->render('a');
    $this->factory->render('b');

    expect(glob($this->dir.'/cache/*.php'))->toHaveCount(2)
        ->and(V::compiler($this->factory)->clearCache())->toBe(2)
        ->and(glob($this->dir.'/cache/*.php'))->toBe([]);
});

it('uma excepção dentro da view reporta o nome e a linha original', function (): void {
    V::write($this->dir, 'boom', "<h1>título</h1>\n@foreach([1] as \$i)\n    {{ \$i }}\n    {{ throw new RuntimeException('falhou') }}\n@endforeach\n");

    try {
        $this->factory->render('boom');
        $this->fail('devia lançar');
    } catch (ViewException $e) {
        expect($e->getViewName())->toBe('boom')
            ->and($e->getViewLine())->toBe(4)
            ->and($e->getViewPath())->toEndWith('boom.blade.php')
            ->and($e->getMessage())->toContain('falhou')->toContain('boom')->toContain('boom.blade.php:4')
            ->and($e->getPrevious())->toBeInstanceOf(RuntimeException::class);
    }
});

it('a linha do erro está certa mesmo com comentários, echos e @php multi-linha antes', function (): void {
    V::write($this->dir, 'lines', "{{-- a\nb --}}\n@php\n    \$x = 1;\n@endphp\n{{\n  \$x\n}}\n@verbatim\n{{ y }}\n@endverbatim\n{{ throw new LogicException('aqui') }}");

    try {
        $this->factory->render('lines');
        $this->fail('devia lançar');
    } catch (ViewException $e) {
        expect($e->getViewLine())->toBe(12);
    }
});

it('a excepção na view incluída aponta para a view incluída', function (): void {
    V::write($this->dir, 'outer', "linha 1\n@include('inner')");
    V::write($this->dir, 'inner', "a\nb\n{{ throw new LogicException('dentro') }}");

    try {
        $this->factory->render('outer');
        $this->fail('devia lançar');
    } catch (ViewException $e) {
        expect($e->getViewName())->toBe('inner')->and($e->getViewLine())->toBe(3);
    }
});

it('erros de PHP (ParseError) na expressão também reportam a linha', function (): void {
    V::write($this->dir, 'parse', "ok\n\n{{ \$a + }}");

    try {
        $this->factory->render('parse');
        $this->fail('devia lançar');
    } catch (ViewException $e) {
        expect($e->getViewLine())->toBe(3)->and($e->getPrevious())->toBeInstanceOf(ParseError::class);
    }
});

it('HttpException dentro da view (abort) passa sem ser embrulhada', function (): void {
    V::write($this->dir, 'gone', "@php throw new Slendie\\Exceptions\\NotFoundHttpException('sumiu'); @endphp");

    $this->factory->render('gone');
})->throws(NotFoundHttpException::class, 'sumiu');

it('depois de uma excepção os buffers e o estado ficam limpos', function (): void {
    V::write($this->dir, 'broken', "@section('s')<p>aberta @php throw new Exception('x'); @endphp");
    V::write($this->dir, 'fine', "@yield('s', 'limpo')");

    $level = ob_get_level();

    try {
        $this->factory->render('broken');
    } catch (ViewException) {
    }

    expect(ob_get_level())->toBe($level)
        ->and($this->factory->render('fine'))->toBe('limpo');
});

it('o Engine não deixa ficar os buffers abertos num ParseError', function (): void {
    V::write($this->dir, 'p', 'x {{ ) }}');
    $level = ob_get_level();

    try {
        $this->factory->render('p');
    } catch (ViewException) {
    }

    expect(ob_get_level())->toBe($level);
});

it('exists() e render()', function (): void {
    V::write($this->dir, 'there', 'ok');

    expect($this->factory->exists('there'))->toBeTrue()
        ->and($this->factory->exists('not.there'))->toBeFalse()
        ->and($this->factory->render('there'))->toBe('ok');
});

final class ComposerFixture
{
    public function compose(View $view): void
    {
        $view->with('a', 'A');
    }

    public function other(View $view): void
    {
        $view->with('b', 'B');
    }
}
