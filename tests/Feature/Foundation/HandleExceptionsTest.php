<?php

declare(strict_types=1);

use Slendie\Foundation\Application;
use Slendie\Foundation\Bootstrap\BootProviders;
use Slendie\Foundation\Bootstrap\HandleExceptions;
use Slendie\Foundation\Bootstrap\LoadConfiguration;
use Slendie\Foundation\Bootstrap\LoadEnvironment;
use Slendie\Foundation\Bootstrap\RegisterProviders;
use Slendie\Foundation\Exceptions\Handler;
use Slendie\Http\Request;

/** Uma app web (não "consola") com HandleExceptions instalado; o chamador tem de fazer HandleExceptions::flush(). */
function webAppWithHandlers(): Application
{
    $app = new class (fixturePath('http')) extends Application {
        public function runningInConsole(): bool
        {
            return false;
        }
    };
    $app->useStoragePath(sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-he-'.bin2hex(random_bytes(4)));
    $app->bootstrapWith([LoadEnvironment::class, LoadConfiguration::class, HandleExceptions::class, RegisterProviders::class, BootProviders::class]);

    return $app;
}

it('converte erros PHP em ErrorException e ignora deprecações e níveis desligados', function (): void {
    $handler = new HandleExceptions();
    $before = error_reporting(E_ALL);

    try {
        expect(fn () => $handler->handleError(E_WARNING, 'aviso', 'f.php', 3))->toThrow(ErrorException::class, 'aviso')
            ->and($handler->handleError(E_DEPRECATED, 'velho'))->toBeTrue()
            ->and($handler->handleError(E_USER_DEPRECATED, 'velho'))->toBeTrue();

        error_reporting(E_ALL & ~E_NOTICE);

        expect($handler->handleError(E_NOTICE, 'ignorado'))->toBeFalse();
    } finally {
        error_reporting($before);
    }
});

it('o bootstrap instala os handlers e regista o Handler; flush() repõe-nos', function (): void {
    $app = webAppWithHandlers();

    try {
        expect($app->bound(Handler::class))->toBeTrue()
            ->and(set_error_handler(static fn (): bool => false))->toBeInstanceOf(Closure::class);

        restore_error_handler();
    } finally {
        HandleExceptions::flush();
    }
});

it('excepções não apanhadas vão para o Handler e a resposta é enviada', function (): void {
    $app = webAppWithHandlers();
    $app->instance('request', Request::create('/falha'));

    ob_start();

    try {
        (new HandleExceptions())->handleException(new RuntimeException('rebentou'));
    } finally {
        $out = (string) ob_get_clean();
        HandleExceptions::flush();
    }

    expect($out)->toContain('rebentou');
});

it('sem aplicação, a excepção é escrita tal e qual', function (): void {
    (new ReflectionProperty(HandleExceptions::class, 'app'))->setValue(null, null);

    ob_start();
    (new HandleExceptions())->handleException(new RuntimeException('sem app'));
    $out = (string) ob_get_clean();

    expect($out)->toContain('sem app');
});

it('se nem o Handler consegue responder, escreve a falha interna', function (): void {
    $app = webAppWithHandlers();
    $app->instance(Handler::class, new class ($app) extends Handler {
        public function report(Throwable $e): void
        {
            throw new LogicException('falha interna do handler');
        }
    });

    ob_start();

    try {
        (new HandleExceptions())->handleException(new RuntimeException('original'));
    } finally {
        $out = (string) ob_get_clean();
        HandleExceptions::flush();
    }

    expect($out)->toContain('falha interna do handler');
});

it('o shutdown sem erro fatal não faz nada', function (): void {
    expect((new HandleExceptions())->handleShutdown())->toBeNull();
});
