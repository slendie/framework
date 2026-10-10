<?php

declare(strict_types=1);

use Slendie\Container\BindingResolutionException;
use Slendie\Container\CircularDependencyException;
use Slendie\Container\Container;
use Slendie\Tests\Fixtures\CircularA;
use Slendie\Tests\Fixtures\Clock;
use Slendie\Tests\Fixtures\Greeter;
use Slendie\Tests\Fixtures\GreeterContract;
use Slendie\Tests\Fixtures\Invokable;
use Slendie\Tests\Fixtures\Mailer;
use Slendie\Tests\Fixtures\NeedsNullable;
use Slendie\Tests\Fixtures\NeedsScalar;
use Slendie\Tests\Fixtures\Newsletter;
use Slendie\Tests\Fixtures\ShoutingGreeter;
use Slendie\Tests\Fixtures\StaticHolder;

beforeEach(function (): void {
    $this->container = new Container();
});

it('resolve classes concretas sem binding (autowiring)', function (): void {
    $clock = $this->container->make(Clock::class);

    expect($clock)->toBeInstanceOf(Clock::class);
});

it('resolve dependências aninhadas', function (): void {
    $this->container->bind(GreeterContract::class, Greeter::class);

    $newsletter = $this->container->make(Newsletter::class);

    expect($newsletter)->toBeInstanceOf(Newsletter::class)
        ->and($newsletter->mailer)->toBeInstanceOf(Mailer::class)
        ->and($newsletter->mailer->clock)->toBeInstanceOf(Clock::class)
        ->and($newsletter->mailer->host)->toBe('localhost')
        ->and($newsletter->greeter)->toBeInstanceOf(Greeter::class);
});

it('bind com closure recebe o container e os parâmetros', function (): void {
    $this->container->bind('mailer', fn (Container $c, array $params) => new Mailer($c->make(Clock::class), $params['host'] ?? 'default'));

    expect($this->container->make('mailer')->host)->toBe('default')
        ->and($this->container->make('mailer', ['host' => 'smtp.x'])->host)->toBe('smtp.x');
});

it('bind devolve instâncias diferentes; singleton devolve a mesma', function (): void {
    $this->container->bind('a', Clock::class);
    $this->container->singleton('b', Clock::class);

    expect($this->container->make('a'))->not->toBe($this->container->make('a'))
        ->and($this->container->make('b'))->toBe($this->container->make('b'))
        ->and($this->container->isShared('b'))->toBeTrue()
        ->and($this->container->isShared('a'))->toBeFalse();
});

it('instance regista um objecto já construído', function (): void {
    $clock = new Clock();
    $this->container->instance(Clock::class, $clock);

    expect($this->container->make(Clock::class))->toBe($clock)
        ->and($this->container->resolved(Clock::class))->toBeTrue();
});

it('interface sem bind lança BindingResolutionException com dica', function (): void {
    $this->container->make(GreeterContract::class);
})->throws(BindingResolutionException::class, 'não é instanciável');

it('classe inexistente lança BindingResolutionException', function (): void {
    $this->container->make('Nao\\Existe');
})->throws(BindingResolutionException::class, 'não existe');

it('parâmetro escalar sem default nem valor lança excepção explicativa', function (): void {
    $this->container->make(NeedsScalar::class);
})->throws(BindingResolutionException::class, '$name');

it('parâmetro escalar pode ser passado por nome', function (): void {
    $obj = $this->container->make(NeedsScalar::class, ['name' => 'slendie']);

    expect($obj->name)->toBe('slendie');
});

it('dependência nullable sem bind resolve para null', function (): void {
    expect($this->container->make(NeedsNullable::class)->greeter)->toBeNull();
});

it('detecta dependências circulares', function (): void {
    $this->container->make(CircularA::class);
})->throws(CircularDependencyException::class, 'CircularA -> ');

it('alias resolve para o abstract', function (): void {
    $this->container->singleton(Clock::class);
    $this->container->alias(Clock::class, 'clock');

    expect($this->container->make('clock'))->toBe($this->container->make(Clock::class))
        ->and($this->container->bound('clock'))->toBeTrue()
        ->and($this->container->getAlias('clock'))->toBe(Clock::class);
});

it('alias de si próprio lança', function (): void {
    $this->container->alias('x', 'x');
})->throws(BindingResolutionException::class);

it('extend decora o serviço resolvido', function (): void {
    $this->container->singleton(GreeterContract::class, Greeter::class);
    $this->container->extend(GreeterContract::class, fn (GreeterContract $g) => new ShoutingGreeter($g));

    expect($this->container->make(GreeterContract::class)->greet('ana'))->toBe('OLÁ, ANA!');
});

it('extend aplica-se também a instâncias já registadas', function (): void {
    $this->container->instance(GreeterContract::class, new Greeter());
    $this->container->extend(GreeterContract::class, fn (GreeterContract $g) => new ShoutingGreeter($g));

    expect($this->container->make(GreeterContract::class))->toBeInstanceOf(ShoutingGreeter::class);
});

it('resolving callbacks correm ao resolver, por abstract e por tipo', function (): void {
    $seen = [];
    $this->container->resolving(Clock::class, function (Clock $c) use (&$seen): void {
        $seen[] = 'exact';
    });
    $this->container->resolving(GreeterContract::class, function () use (&$seen): void {
        $seen[] = 'by-interface';
    });
    $this->container->bind('greeter', Greeter::class);

    $this->container->make(Clock::class);
    $this->container->make('greeter');

    expect($seen)->toBe(['exact', 'by-interface']);
});

describe('call()', function (): void {
    it('injecta dependências numa closure e aceita parâmetros nomeados', function (): void {
        $result = $this->container->call(fn (Clock $clock, string $name) => $clock->now().':'.$name, ['name' => 'x']);

        expect($result)->toBe('2026-10-10:x');
    });

    it('aceita [instância, método] com DI e defaults', function (): void {
        $this->container->bind(GreeterContract::class, Greeter::class);
        $newsletter = $this->container->make(Newsletter::class);

        expect($this->container->call([$newsletter, 'send'], ['to' => 'Rui']))->toBe('Olá, Rui! 2026-10-10 ')
            ->and($this->container->call([$newsletter, 'send'], ['to' => 'Rui', 'times' => 2]))->toBe('Olá, Rui! 2026-10-10 Olá, Rui! 2026-10-10 ');
    });

    it('aceita [classe, método], Classe@método e Classe::método', function (): void {
        expect($this->container->call([StaticHolder::class, 'build']))->toBe('static:2026-10-10')
            ->and($this->container->call(StaticHolder::class.'@build'))->toBe('static:2026-10-10')
            ->and($this->container->call(StaticHolder::class.'::build'))->toBe('static:2026-10-10');
    });

    it('aceita classe invocável', function (): void {
        expect($this->container->call(Invokable::class))->toBe('2026-10-10!')
            ->and($this->container->call(Invokable::class, ['suffix' => '?']))->toBe('2026-10-10?');
    });

    it('aceita passar dependências por tipo em vez de por nome', function (): void {
        $clock = new Clock();
        $result = $this->container->call(fn (Clock $c) => spl_object_id($c), [$clock]);

        expect($result)->toBe(spl_object_id($clock));
    });
});

it('ArrayAccess funciona como açúcar', function (): void {
    $this->container['clock'] = fn () => new Clock();
    $this->container['valor'] = 42;

    expect($this->container['clock'])->toBeInstanceOf(Clock::class)
        ->and($this->container['valor'])->toBe(42)
        ->and(isset($this->container['clock']))->toBeTrue();

    unset($this->container['clock']);

    expect(isset($this->container['clock']))->toBeFalse();
});

it('bindIf e singletonIf não sobrepõem', function (): void {
    $this->container->bind('x', fn () => 'a');
    $this->container->bindIf('x', fn () => 'b');
    $this->container->singletonIf('y', fn () => 'c');

    expect($this->container->make('x'))->toBe('a')
        ->and($this->container->make('y'))->toBe('c');
});

it('getInstance cria e setInstance substitui a instância global', function (): void {
    $a = Container::getInstance();
    $b = new Container();
    Container::setInstance($b);

    expect(Container::getInstance())->toBe($b)->not->toBe($a);
});

it('flush limpa tudo', function (): void {
    $this->container->singleton('x', Clock::class);
    $this->container->make('x');
    $this->container->flush();

    expect($this->container->bound('x'))->toBeFalse()
        ->and($this->container->getBindings())->toBe([]);
});
