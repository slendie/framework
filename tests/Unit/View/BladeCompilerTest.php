<?php

declare(strict_types=1);

use Slendie\Support\Str;
use Slendie\Tests\Fixtures\View\ViewHelpers as V;
use Slendie\View\CompileException;
use Slendie\View\Compiler\BladeCompiler;
use Slendie\View\ViewErrorBag;

/** Renderiza e junta o espaço em branco, para os testes de estrutura não dependerem de quebras de linha. */
function squished(string $blade, array $data = [], array $files = []): string
{
    return Str::squish(V::render($blade, $data, $files));
}

// -----------------------------------------------------------------------------
// Echos e comentários
// -----------------------------------------------------------------------------

it('{{ }} escapa e {!! !!} não', function (): void {
    expect(V::render('<p>{{ $t }}</p><p>{!! $t !!}</p>', ['t' => '<b>"x"</b>']))
        ->toBe('<p>&lt;b&gt;&quot;x&quot;&lt;/b&gt;</p><p><b>"x"</b></p>');
});

it('aceita qualquer expressão PHP e valores por omissão', function (): void {
    expect(V::render("{{ \$a ?? 'omissão' }}|{{ count(\$l) }}|{{ strtoupper('x') . 1 + 2 }}", ['l' => [1, 2, 3]]))
        ->toBe('omissão|3|X3');
});

it('@{{ }} e @{!! !!} ficam literais', function (): void {
    expect(V::render('@{{ nome }} @{!! cru !!} {{ $x }}', ['x' => 1]))->toBe('{{ nome }} {!! cru !!} 1');
});

it('comentários não vão para o HTML', function (): void {
    expect(V::render("a{{-- um\ncomentário {{ \$x }} --}}b"))->toBe('ab');
});

it('preserva as quebras de linha depois de echos e comentários', function (): void {
    expect(V::render("{{ \$a }}\n{{ \$b }}\n{{-- c --}}\nfim\n", ['a' => 1, 'b' => 2]))->toBe("1\n2\n\nfim\n");
    expect(V::render("{{ \$a }}\r\n{{ \$b }}\r\n", ['a' => 1, 'b' => 2]))->toBe("1\r\n2\r\n");
});

it('echo de array lança com mensagem clara', function (): void {
    V::render('{{ $a }}', ['a' => [1]]);
})->throws(Slendie\View\ViewException::class, 'e() só aceita');

// -----------------------------------------------------------------------------
// Condicionais
// -----------------------------------------------------------------------------

it('@if @elseif @else @endif', function (): void {
    $tpl = '@if($n > 5) grande @elseif($n > 2) médio @else pequeno @endif';

    expect(squished($tpl, ['n' => 9]))->toBe('grande')
        ->and(squished($tpl, ['n' => 3]))->toBe('médio')
        ->and(squished($tpl, ['n' => 1]))->toBe('pequeno');
});

it('aceita espaço entre a directiva e os parênteses', function (): void {
    expect(V::render('@if ($a) sim @endif', ['a' => true]))->toBe(' sim ');
});

it('@unless, @isset e @empty($x)', function (): void {
    $tpl = '@unless($a) A @endunless @isset($b) B @endisset @empty($c) C @endempty';

    expect(squished($tpl, ['a' => false, 'b' => 1, 'c' => []]))->toBe('A B C')
        ->and(squished($tpl, ['a' => true, 'c' => [1]]))->toBe('');
});

it('@switch com @case, @break e @default', function (): void {
    $tpl = "@switch(\$s)\n    @case('new') Novo @break\n    @case('won') Ganho @break\n    @default Outro\n@endswitch";

    expect(squished($tpl, ['s' => 'new']))->toBe('Novo')
        ->and(squished($tpl, ['s' => 'won']))->toBe('Ganho')
        ->and(squished($tpl, ['s' => 'x']))->toBe('Outro');
});

it('@switch aninhado', function (): void {
    $tpl = '@switch($a) @case(1) @switch($b) @case(1) 1-1 @break @default 1-x @endswitch @break @default x @endswitch';

    expect(squished($tpl, ['a' => 1, 'b' => 1]))->toBe('1-1')
        ->and(squished($tpl, ['a' => 1, 'b' => 2]))->toBe('1-x')
        ->and(squished($tpl, ['a' => 2, 'b' => 2]))->toBe('x');
});

it('@env e @production (o ambiente é testing)', function (): void {
    $app = Slendie\Tests\Fixtures\Http\HttpHelpers::app();

    expect($app->environment())->toBe('testing')
        ->and(squished("@env('testing') T @endenv @env(['local', 'staging']) L @endenv @production P @endproduction"))->toBe('T')
        ->and(squished("@env('local', 'testing') ok @endenv"))->toBe('ok');
});

it('as directivas sem expressão obrigatória falham em compilação', function (): void {
    V::render('@if');
})->throws(CompileException::class, 'A directiva @if precisa de uma expressão');

// -----------------------------------------------------------------------------
// Ciclos e $loop
// -----------------------------------------------------------------------------

it('@foreach com $loop', function (): void {
    $tpl = '@foreach($items as $k => $item)[{{ $k }}:{{ $item }}:{{ $loop->iteration }}/{{ $loop->count }}'
        .'{{ $loop->first ? "F" : "" }}{{ $loop->last ? "L" : "" }}{{ $loop->even ? "e" : "o" }}:{{ $loop->remaining }}:{{ $loop->index }}:{{ $loop->depth }}]@endforeach';

    expect(V::render($tpl, ['items' => ['a' => 'x', 'b' => 'y', 'c' => 'z']]))
        ->toBe('[a:x:1/3Fo:2:0:1][b:y:2/3e:1:1:1][c:z:3/3Lo:0:2:1]');
});

it('$loop->parent em ciclos aninhados e restauro depois do ciclo interior', function (): void {
    $tpl = '@foreach($rows as $row)R{{ $loop->iteration }}('
        .'@foreach($row as $cell){{ $loop->parent->iteration }}.{{ $loop->iteration }}/{{ $loop->depth }} @endforeach'
        .'|{{ $loop->iteration }})@endforeach';

    expect(V::render($tpl, ['rows' => [[1, 2], [3]]]))->toBe('R1(1.1/2 1.2/2 |1)R2(2.1/2 |2)');
});

it('$loop funciona com geradores (sem count)', function (): void {
    $generator = (static function () {
        yield 'a';
        yield 'b';
    })();

    expect(V::render('@foreach($g as $v){{ $v }}{{ $loop->last === null ? "?" : "" }}{{ $loop->remaining ?? "-" }}@endforeach', ['g' => $generator]))
        ->toBe('a?-b?-');
});

it('@forelse com @empty', function (): void {
    $tpl = '@forelse($items as $item)<{{ $item }}>@empty vazio @endforelse';

    expect(V::render($tpl, ['items' => [1, 2]]))->toBe('<1><2>')
        ->and(V::render($tpl, ['items' => []]))->toBe(' vazio ');
});

it('@forelse aninhado: cada @empty fecha o seu ciclo', function (): void {
    $tpl = '@forelse($a as $x)[@forelse($x as $y){{ $y }}@empty -@endforelse]@empty NADA @endforelse';

    expect(V::render($tpl, ['a' => [[1, 2], []]]))->toBe('[12][ -]')
        ->and(V::render($tpl, ['a' => []]))->toBe(' NADA ');
});

it('@empty sem @forelse falha em compilação', function (): void {
    V::render('@empty');
})->throws(CompileException::class, '@empty sem argumentos');

it('@for, @while, @break(cond) e @continue(cond)', function (): void {
    expect(V::render('@for($i = 0; $i < 5; $i++)@continue($i == 1)@break($i == 4){{ $i }}@endfor'))->toBe('023')
        ->and(V::render('@php($n = 3)@while($n > 0){{ $n-- }}@endwhile'))->toBe('321');
});

it('@break dentro de @foreach repõe o $loop do pai', function (): void {
    $tpl = '@foreach([1, 2] as $a)@foreach([1, 2, 3] as $b)@break($b == 2){{ $b }}@endforeach{{ $loop->iteration }}@endforeach';

    expect(V::render($tpl))->toBe('1112');
});

it('@foreach inválido falha em compilação', function (): void {
    V::render('@foreach($x)@endforeach');
})->throws(CompileException::class, "espera '\$items as \$item'");

// -----------------------------------------------------------------------------
// Layouts, secções, includes e stacks
// -----------------------------------------------------------------------------

it('@extends, @section, @yield e @parent', function (): void {
    $files = ['layout' => "<h1>@yield('t', 'Padrão')</h1>@section('c')<i>base</i>@show"];
    $child = "@extends('layout')@section('t', 'Olá')@section('c')[@parent|filho]@endsection";

    expect(V::render($child, [], $files))->toBe('<h1>Olá</h1>[<i>base</i>|filho]');
});

it('@yield usa o valor por omissão (escapado) quando a secção não existe', function (): void {
    $files = ['layout' => "<h1>@yield('t', '<Padrão>')</h1>"];

    expect(V::render("@extends('layout')", [], $files))->toBe('<h1>&lt;Padrão&gt;</h1>');
});

it('herança em vários níveis com @parent', function (): void {
    $files = [
        'base' => "<body>@yield('c')</body>",
        'mid' => "@extends('base')@section('c')M(@yield('i'))@endsection",
    ];

    expect(V::render("@extends('mid')@section('i')<b>leaf</b>@endsection", [], $files))->toBe('<body>M(<b>leaf</b>)</body>');
});

it('@section ... @show define e imprime; a filha sobrepõe', function (): void {
    $files = ['layout' => "[@section('s')<i>pai</i>@show]"];

    expect(V::render("@extends('layout')@section('s')<u>filho</u>@endsection", [], $files))->toBe('[<u>filho</u>]')
        ->and(V::render("@extends('layout')", [], $files))->toBe('[<i>pai</i>]');
});

it('@overwrite e @append', function (): void {
    $files = ['layout' => "[@section('s')<i>pai</i>@show]"];

    expect(V::render("@extends('layout')@section('s')<a/>@endsection @section('s')<b/>@overwrite", [], $files))->toBe(' [<b/>]')
        ->and(V::render("@extends('layout')@section('s')<a/>@endsection @section('s')<b/>@append", [], $files))->toBe(' [<a/><b/>]');
});

it('@hasSection e @sectionMissing', function (): void {
    $files = ['layout' => "@hasSection('a') A @endif @sectionMissing('b') !B @endif"];

    expect(squished("@extends('layout')@section('a', 1)", [], $files))->toBe('A !B');
});

it('o estado das secções não vaza entre renderizações', function (): void {
    $dir = V::tempDir();
    V::write($dir, 'layout', "[@yield('s', 'vazio')]");
    V::write($dir, 'one', "@extends('layout')@section('s', 'um')");
    V::write($dir, 'two', "@extends('layout')");
    $factory = V::factory($dir);

    expect($factory->render('one'))->toBe('[um]')
        ->and($factory->render('two'))->toBe('[vazio]');
});

it('@include passa as variáveis do pai e as extra', function (): void {
    $files = ['partials.item' => '<{{ $label }}:{{ $a }}{{ $extra ?? "" }}>'];

    expect(V::render("@include('partials.item', ['extra' => '!'])", ['label' => 'L', 'a' => 1], $files))->toBe('<L:1!>');
});

it('@include aninhado e dentro de @foreach vê o $loop', function (): void {
    $files = [
        'row' => '{{ $loop->iteration }}=@include("cell", ["v" => $item])',
        'cell' => '[{{ $v }}{{ $loop->first ? "*" : "" }}]',
    ];

    expect(V::render('@foreach($items as $item)@include("row")@endforeach', ['items' => ['a', 'b']], $files))
        ->toBe('1=[a*]2=[b]');
});

it('@includeIf, @includeWhen, @includeUnless e @includeFirst', function (): void {
    $files = ['yes' => 'Y{{ $v ?? "" }}', 'alt' => 'ALT'];

    expect(V::render("@includeIf('nao.existe')|@includeIf('yes')", [], $files))->toBe('|Y')
        ->and(V::render("@includeWhen(true, 'yes', ['v' => 1])@includeWhen(false, 'yes')", [], $files))->toBe('Y1')
        ->and(V::render("@includeUnless(false, 'yes')@includeUnless(true, 'alt')", [], $files))->toBe('Y')
        ->and(V::render("@includeFirst(['nao.existe', 'alt', 'yes'])", [], $files))->toBe('ALT');
});

it('@includeFirst sem nenhuma view lança ViewNotFoundException', function (): void {
    V::render("@includeFirst(['a', 'b'])");
})->throws(Slendie\View\ViewException::class, 'Nenhuma das views existe');

it('@push, @prepend e @stack', function (): void {
    $files = ['layout' => "<head>@stack('head')</head>@yield('c')"];
    $child = "@extends('layout')@section('c')<i>x</i>@endsection @push('head')<b/>@endpush @push('head')<c/>@endpush @prepend('head')<a/>@endprepend";

    expect(squished($child, [], $files))->toBe('<head><a/><b/><c/></head><i>x</i>');
});

it('@push dentro de um include aparece no @stack do layout', function (): void {
    $files = ['layout' => "@yield('c')[@stack('js')]", 'partial' => "@push('js')<p/>@endpush"];

    expect(V::render("@extends('layout')@section('c')@include('partial')@endsection", [], $files))->toBe('[<p/>]');
});

it('@once renderiza uma só vez por ciclo de render', function (): void {
    expect(V::render('@foreach([1, 2, 3] as $i)@once ONCE @endonce{{ $i }}@endforeach'))->toBe(' ONCE 123');
});

it('secção por fechar é reportada', function (): void {
    V::render("@section('s')sem fim");
})->throws(Slendie\View\ViewException::class, 'por fechar');

// -----------------------------------------------------------------------------
// Formulários
// -----------------------------------------------------------------------------

it('@error expõe $message e restaura o anterior', function (): void {
    $errors = new ViewErrorBag(['email' => ['Inválido', 'Outro'], 'nome' => 'Obrigatório']);
    $tpl = "@error('email')<e>{{ \$message }}</e>@enderror @error('x')<s>NAO</s>@enderror @error('nome')<n>{{ \$message }}</n>@enderror [{{ \$message ?? 'sem' }}]";

    expect(squished($tpl, ['errors' => $errors]))->toBe('<e>Inválido</e> <n>Obrigatório</n> [sem]')
        ->and(squished($tpl))->toBe('[sem]');
});

it('@error aninhados', function (): void {
    $errors = new ViewErrorBag(['a' => 'A!', 'b' => 'B!']);

    expect(V::render("@error('a'){{ \$message }}@error('b'){{ \$message }}@enderror {{ \$message }} @enderror", ['errors' => $errors]))
        ->toBe('A!B! A! ');
});

it('$errors tem any(), all() e first()', function (): void {
    expect(squished('@if($errors->any())@foreach($errors->all() as $e)<{{ $e }}>@endforeach @endif', ['errors' => new ViewErrorBag(['a' => ['1', '2']])]))
        ->toBe('<1><2>')
        ->and(V::render('{{ $errors->any() ? "sim" : "não" }}'))->toBe('não');
});

it('@checked, @selected, @disabled, @readonly e @required', function (): void {
    expect(V::render('<i @checked($a) @selected($a) @disabled($b) @readonly($a) @required($a)>', ['a' => true, 'b' => false]))
        ->toBe('<i checked selected  readonly required>');
});

it('@class junta classes condicionais', function (): void {
    expect(V::render("<a @class(['btn', 'btn-primary' => \$p, 'off' => ! \$p, 'x'])>", ['p' => true]))
        ->toBe('<a class="btn btn-primary x">');
});

it('@csrf e @method compilam para os helpers', function (): void {
    $php = V::compile("@csrf\n@method('PUT')");

    expect($php)->toContain('csrf_field()')->toContain("method_field('PUT')");
});

// -----------------------------------------------------------------------------
// Diversos
// -----------------------------------------------------------------------------

it('@json é seguro para <script> e aceita flags', function (): void {
    $expected = str_replace('~', chr(92), '<script>var d = {"a":"~u003C~/script~u003E","q":"it~u0027s"};</script>');

    expect(V::render('<script>var d = @json($d);</script>', ['d' => ['a' => '</script>', 'q' => "it's"]]))->toBe($expected);

    expect(V::render('@json($d, JSON_PRETTY_PRINT)', ['d' => [1]]))->toBe("[\n    1\n]");
});

it('@php em bloco e inline', function (): void {
    expect(V::render("@php\n    \$total = 2 + 3;\n@endphp\n{{ \$total }}@php(\$total++){{ \$total }}"))->toBe('56')
        ->and(V::render('@php($x = "a"){{ $x }}'))->toBe('a');
});

it('@verbatim não processa nada', function (): void {
    expect(V::render("@verbatim\n{{ \$x }} @if(\$y) @foo\n@endverbatim{{ \$z }}", ['z' => 'z']))->toBe("\n{{ \$x }} @if(\$y) @foo\nz");
});

it('PHP puro dentro do template não é tocado', function (): void {
    expect(V::render('<?php $v = @strtoupper("a"); ?>[<?= $v ?>] @@if'))->toBe('[A] @if');
});

it('@@directiva escapa e emails/@ soltos passam', function (): void {
    expect(V::render('@@if(x) a@b.pt @ 2x logo@2x.png'))->toBe('@if(x) a@b.pt @ 2x logo@2x.png');
});

it('at-rules de CSS e JS passam intactas', function (): void {
    $css = '<style>@import url("a.css"); @charset "utf-8";'
        .' @font-face { font-family: x; } @media (min-width: 600px) { a { color: red } }'
        .' @keyframes k { to { top: 0 } } @supports (display: grid) { a { b: c } } @page { margin: 0 }'
        .' @layer base { a { b: c } } @tailwind base; a { @apply p-4; } @theme { --x: 1; } @container (min-width: 1px) { a { b: c } }</style>';

    expect(V::render($css))->toBe($css);
});

it('directiva desconhecida lança CompileException com a linha', function (): void {
    try {
        V::render("<p>a</p>\n\n   <b>@foo('x')</b>");
        $this->fail('devia lançar');
    } catch (CompileException $e) {
        expect($e->getMessage())->toContain('Directiva @foo desconhecida em ')->toContain(':3')
            ->and($e->getViewLine())->toBe(3)
            ->and($e->getViewPath())->toEndWith('main.blade.php');
    }
});

it('a linha da CompileException conta @verbatim, @php e comentários anteriores', function (): void {
    $tpl = "{{-- a\nb --}}\n@verbatim\nx\n@endverbatim\n@php\n\$a = 1;\n@endphp\n{{ \$a }}\n@nope";

    try {
        V::render($tpl);
        $this->fail('devia lançar');
    } catch (CompileException $e) {
        expect($e->getViewLine())->toBe(10);
    }
});

it('directivas personalizadas', function (): void {
    $dir = V::tempDir();
    V::write($dir, 'main', "@money(\$v) | @hello | @datetime(\$v, 'x')");
    $factory = V::factory($dir);
    $blade = V::compiler($factory);

    $blade->directive('money', fn (string $expr): string => "<?php echo number_format({$expr}, 2, ',', '.'); ?>");
    $blade->directive('hello', fn (string $expr): string => '<?php echo "olá['.$expr.']"; ?>');
    $blade->directive('datetime', fn (string $expr): string => "<?php echo json_encode([{$expr}]); ?>");

    expect($factory->render('main', ['v' => 1234.5]))->toBe('1.234,50 | olá[] | [1234.5,"x"]')
        ->and(array_keys($blade->getCustomDirectives()))->toBe(['money', 'hello', 'datetime']);
});

it('uma directiva personalizada pode substituir uma nativa', function (): void {
    $compiler = new BladeCompiler(sys_get_temp_dir());
    $compiler->directive('csrf', fn (): string => 'TOKEN');

    expect($compiler->compileString('@csrf'))->toBe('TOKEN');
});

it('o nome de uma directiva personalizada é validado', function (): void {
    (new BladeCompiler(sys_get_temp_dir()))->directive('a-b', fn (): string => '');
})->throws(InvalidArgumentException::class);

it('o PHP compilado tem exactamente as linhas do template', function (): void {
    $tpl = <<<'BLADE'
{{-- cabeçalho
   em duas linhas --}}
@extends('layout')
@section('c')
    @foreach($items as $item)
        {{ $item }}
        {!! $item !!}
        @if($item)
            @include('x',
                ['a' => 1])
        @endif
    @endforeach
    @verbatim
        {{ nao }}
    @endverbatim
    @php
        $a = 1;
    @endphp
    {{
        $a
    }}
@endsection

BLADE;

    expect(substr_count(V::compile($tpl), "\n"))->toBe(substr_count($tpl, "\n"));
});

it('@extends vai para o fim do ficheiro compilado', function (): void {
    $php = V::compile("@extends('layout')\n@section('c')x@endsection");

    expect($php)->toEndWith("<?php echo \$__env->renderInclude(get_defined_vars(), 'layout'); ?>");
});

it('a classe de utilidade não expõe métodos internos como directivas', function (): void {
    V::render('@string(1)');
})->throws(CompileException::class, 'Directiva @string desconhecida');
