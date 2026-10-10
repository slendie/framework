<?php

declare(strict_types=1);

use Slendie\Container\Container;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Routing\Pipeline;
use Slendie\Tests\Fixtures\Http\TagMiddleware;

beforeEach(function (): void {
    TagMiddleware::$log = [];
});

it('executa os pipes em cebola e chega ao destino', function (): void {
    $log = [];

    $result = (new Pipeline())
        ->send('x')
        ->through([
            function (string $in, Closure $next) use (&$log): string {
                $log[] = 'a-in';
                $out = $next($in.'1');
                $log[] = 'a-out';

                return $out.'A';
            },
            function (string $in, Closure $next) use (&$log): string {
                $log[] = 'b-in';
                $out = $next($in.'2');
                $log[] = 'b-out';

                return $out.'B';
            },
        ])
        ->then(function (string $in) use (&$log): string {
            $log[] = 'dest';

            return $in.'!';
        });

    expect($result)->toBe('x12!BA')
        ->and($log)->toBe(['a-in', 'b-in', 'dest', 'b-out', 'a-out']);
});

it('um pipe pode interromper a cadeia', function (): void {
    $reached = false;

    $result = (new Pipeline())
        ->send('x')
        ->through([
            fn (string $in, Closure $next): string => 'parou',
            function (string $in, Closure $next) use (&$reached): mixed {
                $reached = true;

                return $next($in);
            },
        ])
        ->then(fn (string $in): string => 'destino');

    expect($result)->toBe('parou')->and($reached)->toBeFalse();
});

it('resolve pipes por classe e passa parâmetros Classe:a,b', function (): void {
    $response = (new Pipeline(Container::getInstance()))
        ->send(Request::create('/'))
        ->through([TagMiddleware::class.':a,b', TagMiddleware::class.':c'])
        ->then(fn () => new Response('ok'));

    expect(TagMiddleware::$log)->toBe(['before:a|b', 'before:c', 'after:c', 'after:a|b'])
        ->and($response->getHeader('X-Tag'))->toBe('c');
});

it('aceita instâncias e o método personalizado via()', function (): void {
    $pipe = new class () {
        public function run(string $in, Closure $next): string
        {
            return strtoupper($next($in));
        }
    };

    $result = (new Pipeline())->send('abc')->through($pipe)->via('run')->then(fn (string $in): string => $in.'d');

    expect($result)->toBe('ABCD');
});

it('rejeita pipes sem o método esperado', function (): void {
    (new Pipeline())->send(1)->through([new stdClass()])->then(fn () => 1);
})->throws(InvalidArgumentException::class);
