<?php

declare(strict_types=1);

use Slendie\Validation\Translator;

beforeEach(function (): void {
    $this->framework = sys_get_temp_dir().'/slendie-lang-'.bin2hex(random_bytes(4));
    $this->project = $this->framework.'-project';

    foreach (['pt', 'en'] as $locale) {
        mkdir($this->framework.'/'.$locale, 0777, true);
        mkdir($this->project.'/'.$locale, 0777, true);
    }

    file_put_contents($this->framework.'/pt/messages.php', "<?php return ['hello' => 'Olá :name', 'only_pt' => 'só pt', 'nested' => ['deep' => 'fundo'], 'apples' => '{0} sem maçãs|{1} uma maçã|[2,*] :count maçãs', 'plain' => 'item|itens', 'caps' => ':name / :Name / :NAME'];");
    file_put_contents($this->framework.'/en/messages.php', "<?php return ['hello' => 'Hello :name', 'only_en' => 'only en'];");
    file_put_contents($this->project.'/pt/messages.php', "<?php return ['hello' => 'Boas, :name!'];");
});

afterEach(function (): void {
    foreach ([$this->framework, $this->project] as $dir) {
        foreach (glob($dir.'/*/*.php') ?: [] as $file) {
            unlink($file);
        }
    }
});

it('get() traduz com replacements e variantes de maiúsculas', function (): void {
    $t = new Translator($this->framework, null, 'pt');

    expect($t->get('messages.hello', ['name' => 'ana']))->toBe('Olá ana')
        ->and($t->get('messages.hello', ['name' => 'ana'], 'en'))->toBe('Hello ana')
        ->and($t->get('messages.nested.deep'))->toBe('fundo')
        ->and($t->get('messages.caps', ['name' => 'ana']))->toBe('ana / Ana / ANA');
});

it('a chave inexistente devolve a própria chave e has() diz se existe', function (): void {
    $t = new Translator($this->framework, null, 'pt');

    expect($t->get('messages.nope'))->toBe('messages.nope')
        ->and($t->get('semgrupo'))->toBe('semgrupo')
        ->and($t->has('messages.hello'))->toBeTrue()
        ->and($t->has('messages.nope'))->toBeFalse();
});

it('fallback de locale: usa en quando falta em pt', function (): void {
    $t = new Translator($this->framework, null, 'pt', 'en');

    expect($t->get('messages.only_en'))->toBe('only en')
        ->and($t->has('messages.only_en'))->toBeTrue()
        ->and($t->has('messages.only_en', null, false))->toBeFalse()
        ->and($t->get('messages.only_pt', [], 'en'))->toBe('messages.only_pt');

    $t->setLocale('en');
    expect($t->getLocale())->toBe('en')->and($t->get('messages.hello', ['name' => 'x']))->toBe('Hello x');
});

it('o projecto sobrepõe os textos da framework', function (): void {
    $t = new Translator($this->framework, $this->project, 'pt');

    expect($t->get('messages.hello', ['name' => 'Rui']))->toBe('Boas, Rui!')
        ->and($t->get('messages.only_pt'))->toBe('só pt');
});

it('addLines e addPath / addNamespace', function (): void {
    $t = new Translator($this->framework, null, 'pt');
    $t->addLines(['messages.hello' => 'Olá a todos', 'extra.novo' => 'novo'], 'pt');

    expect($t->get('messages.hello'))->toBe('Olá a todos')
        ->and($t->get('extra.novo'))->toBe('novo');

    $package = sys_get_temp_dir().'/slendie-pkg-'.bin2hex(random_bytes(4));
    mkdir($package.'/pt', 0777, true);
    file_put_contents($package.'/pt/pkg.php', "<?php return ['titulo' => 'Pacote'];");

    $t->addNamespace('meu', $package);
    expect($t->get('meu::pkg.titulo'))->toBe('Pacote');

    $t->addPath($package);
    expect($t->get('pkg.titulo'))->toBe('Pacote');

    unlink($package.'/pt/pkg.php');
});

it('choice(): intervalos, singular|plural e :count', function (): void {
    $t = new Translator($this->framework, null, 'pt');

    expect($t->choice('messages.apples', 0))->toBe('sem maçãs')
        ->and($t->choice('messages.apples', 1))->toBe('uma maçã')
        ->and($t->choice('messages.apples', 5))->toBe('5 maçãs')
        ->and($t->choice('messages.plain', 1))->toBe('item')
        ->and($t->choice('messages.plain', 3))->toBe('itens');
});

it('os helpers __, trans e trans_choice usam o tradutor da framework', function (): void {
    expect(__('validation.required', ['attribute' => 'nome']))->toBe('O campo nome é obrigatório.')
        ->and(trans('validation.required', ['attribute' => 'x'], 'en'))->toBe('The x field is required.')
        ->and(__(null))->toBeNull()
        ->and(__('pagination.next'))->toBe('Seguinte &raquo;')
        ->and(trans_choice('validation.nope', 1))->toBe('validation.nope');
});
