<?php

declare(strict_types=1);

use Slendie\Support\Str;

it('converte casing', function (): void {
    expect(Str::snake('LeadController'))->toBe('lead_controller')
        ->and(Str::snake('contactForm'))->toBe('contact_form')
        ->and(Str::snake('Lead Controller'))->toBe('lead_controller')
        ->and(Str::snake('already_snake'))->toBe('already_snake')
        ->and(Str::kebab('LeadController'))->toBe('lead-controller')
        ->and(Str::studly('contact_form'))->toBe('ContactForm')
        ->and(Str::studly('contact-form here'))->toBe('ContactFormHere')
        ->and(Str::camel('contact_form'))->toBe('contactForm')
        ->and(Str::title('olá mundo'))->toBe('Olá Mundo')
        ->and(Str::ucfirst('ãgua'))->toBe('Ãgua')
        ->and(Str::lcfirst('Água'))->toBe('água')
        ->and(Str::upper('ação'))->toBe('AÇÃO');
});

it('pluraliza e singulariza (inglês)', function (): void {
    expect(Str::plural('lead'))->toBe('leads')
        ->and(Str::plural('category'))->toBe('categories')
        ->and(Str::plural('person'))->toBe('people')
        ->and(Str::plural('Person'))->toBe('People')
        ->and(Str::plural('status'))->toBe('statuses')
        ->and(Str::plural('address'))->toBe('addresses')
        ->and(Str::plural('box'))->toBe('boxes')
        ->and(Str::plural('knife'))->toBe('knives')
        ->and(Str::plural('tomato'))->toBe('tomatoes')
        ->and(Str::plural('quiz'))->toBe('quizzes')
        ->and(Str::plural('news'))->toBe('news')
        ->and(Str::plural('lead', 1))->toBe('lead')
        ->and(Str::plural('day'))->toBe('days')
        ->and(Str::singular('leads'))->toBe('lead')
        ->and(Str::singular('categories'))->toBe('category')
        ->and(Str::singular('people'))->toBe('person')
        ->and(Str::singular('statuses'))->toBe('status')
        ->and(Str::singular('addresses'))->toBe('address')
        ->and(Str::singular('boxes'))->toBe('box')
        ->and(Str::singular('knives'))->toBe('knife')
        ->and(Str::singular('news'))->toBe('news');
});

it('pesquisa e corta', function (): void {
    expect(Str::startsWith('slendie', 'sle'))->toBeTrue()
        ->and(Str::startsWith('slendie', ['x', 'sl']))->toBeTrue()
        ->and(Str::endsWith('slendie', 'die'))->toBeTrue()
        ->and(Str::contains('Slendie', 'END'))->toBeFalse()
        ->and(Str::contains('Slendie', 'END', ignoreCase: true))->toBeTrue()
        ->and(Str::after('a@b.c', '@'))->toBe('b.c')
        ->and(Str::afterLast('a.b.c', '.'))->toBe('c')
        ->and(Str::before('a@b.c', '@'))->toBe('a')
        ->and(Str::beforeLast('a.b.c', '.'))->toBe('a.b')
        ->and(Str::between('[x]', '[', ']'))->toBe('x')
        ->and(Str::limit('ação completa', 4))->toBe('ação...')
        ->and(Str::limit('curto', 10))->toBe('curto')
        ->and(Str::words('um dois três quatro', 2))->toBe('um dois...')
        ->and(Str::length('ação'))->toBe(4)
        ->and(Str::substr('ação', 1, 2))->toBe('çã');
});

it('transforma', function (): void {
    expect(Str::slug('Olá Mundo, ação & reação!'))->toBe('ola-mundo-acao-reacao')
        ->and(Str::slug('Já_vi_isto', '_'))->toBe('ja_vi_isto')
        ->and(Str::ascii('Ünïcödé çã'))->toBe('Unicode ca')
        ->and(Str::replaceFirst('a', 'X', 'banana'))->toBe('bXnana')
        ->and(Str::replaceLast('a', 'X', 'banana'))->toBe('bananX')
        ->and(Str::start('path', '/'))->toBe('/path')
        ->and(Str::start('//path', '/'))->toBe('/path')
        ->and(Str::finish('path', '/'))->toBe('path/')
        ->and(Str::finish('path//', '/'))->toBe('path/')
        ->and(Str::padLeft('5', 3, '0'))->toBe('005')
        ->and(Str::padRight('ã', 3, '.'))->toBe('ã..')
        ->and(Str::squish('  a   b  '))->toBe('a b')
        ->and(Str::wrap('x', '"'))->toBe('"x"')
        ->and(Str::wrap('x', '[', ']'))->toBe('[x]')
        ->and(Str::classBasename('App\\Models\\Lead'))->toBe('Lead')
        ->and(Str::classBasename(new stdClass()))->toBe('stdClass');
});

it('gera aleatórios e uuids válidos', function (): void {
    $r = Str::random(32);
    $u = Str::uuid();

    expect($r)->toHaveLength(32)->toMatch('/^[A-Za-z0-9]+$/')
        ->and(Str::random(7))->toHaveLength(7)
        ->and($u)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and(Str::isUuid($u))->toBeTrue()
        ->and(Str::isUuid('nope'))->toBeFalse()
        ->and(Str::uuid())->not->toBe($u);
});

it('is() com wildcards', function (): void {
    expect(Str::is('admin.*', 'admin.leads.index'))->toBeTrue()
        ->and(Str::is('admin.*', 'public.home'))->toBeFalse()
        ->and(Str::is(['a', 'b*'], 'bcd'))->toBeTrue()
        ->and(Str::is('exact', 'exact'))->toBeTrue()
        ->and(Str::is('/users/*/edit', '/users/5/edit'))->toBeTrue();
});
