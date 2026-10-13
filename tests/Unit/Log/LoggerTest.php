<?php

declare(strict_types=1);

use Slendie\Foundation\Application;
use Slendie\Log\Logger;
use Slendie\Log\LogServiceProvider;
use Slendie\Support\Facades\Log;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie_log_'.bin2hex(random_bytes(6));
    mkdir($this->dir.'/logs', 0777, true);
});

afterEach(function (): void {
    foreach (glob($this->dir.'/logs/*') ?: [] as $file) {
        unlink($file);
    }

    @rmdir($this->dir.'/logs');
    @rmdir($this->dir);
});

function logger_for(string $dir, array $channels = [], string $default = 'single'): Logger
{
    return new Logger($dir, 'testing', ['default' => $default, 'channels' => $channels]);
}

it('a linha tem o formato [data.ms] env.NIVEL: mensagem {contexto}', function (): void {
    $logger = logger_for($this->dir);

    $logger->info('Lead criado', ['id' => 7]);

    $log = (string) file_get_contents($this->dir.'/logs/slendie.log');

    expect($log)->toMatch('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\] testing\.INFO: Lead criado \{"id":7\}\R$/');
});

it('sem contexto não acrescenta JSON', function (): void {
    logger_for($this->dir)->warning('simples');

    expect((string) file_get_contents($this->dir.'/logs/slendie.log'))->toMatch('/testing\.WARNING: simples\R$/');
});

it('interpola {chave} com o contexto', function (): void {
    $logger = logger_for($this->dir);

    $logger->notice('User {user} fez {action} (ok={ok}, x={x}, lista={lista})', ['user' => 'ana', 'action' => 'login', 'ok' => true, 'x' => null, 'lista' => [1]]);

    expect((string) file_get_contents($this->dir.'/logs/slendie.log'))
        ->toContain('User ana fez login (ok=true, x=null, lista={lista})');
});

it('excepções no contexto imprimem classe, mensagem, ficheiro:linha e trace', function (): void {
    $logger = logger_for($this->dir);

    $logger->error('Falhou', ['exception' => new RuntimeException('boom', 0, new LogicException('causa'))]);

    $log = (string) file_get_contents($this->dir.'/logs/slendie.log');

    expect($log)->toContain("testing.ERROR: Falhou\n[exception] RuntimeException: boom in ")
        ->and($log)->toContain('LoggerTest.php:')
        ->and($log)->toContain("[stacktrace]\n#0")
        ->and($log)->toContain('[previous] LogicException: causa')
        ->and($log)->not->toContain('"exception"');
});

it('todos os níveis PSR-3 existem e log() valida o nível', function (): void {
    $logger = logger_for($this->dir);

    foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
        $logger->{$level}("msg {$level}");
    }

    $logger->log('INFO', 'maiúsculas');

    $log = (string) file_get_contents($this->dir.'/logs/slendie.log');

    expect($log)->toContain('testing.EMERGENCY: msg emergency')->toContain('testing.DEBUG: msg debug')->toContain('testing.INFO: maiúsculas')
        ->and(fn () => $logger->log('verbose', 'x'))->toThrow(InvalidArgumentException::class);
});

it('o canal single respeita path e level mínimo', function (): void {
    $logger = logger_for($this->dir, ['single' => ['driver' => 'single', 'path' => $this->dir.'/logs/custom.log', 'level' => 'warning']]);

    $logger->info('ignorado');
    $logger->error('guardado');

    $log = (string) file_get_contents($this->dir.'/logs/custom.log');

    expect($log)->toContain('guardado')->not->toContain('ignorado');
});

it('o canal daily escreve em slendie-Y-m-d.log', function (): void {
    $logger = logger_for($this->dir, ['daily' => ['driver' => 'daily', 'path' => $this->dir.'/logs/slendie.log', 'days' => 7]], 'daily');

    $logger->info('hoje');

    $file = $this->dir.'/logs/slendie-'.date('Y-m-d').'.log';

    expect(is_file($file))->toBeTrue()->and((string) file_get_contents($file))->toContain('hoje');
});

it('o canal daily apaga os ficheiros mais antigos que "days"', function (): void {
    $old = $this->dir.'/logs/slendie-'.date('Y-m-d', strtotime('-30 days')).'.log';
    $recent = $this->dir.'/logs/slendie-'.date('Y-m-d', strtotime('-2 days')).'.log';
    $unrelated = $this->dir.'/logs/other.log';

    foreach ([$old, $recent, $unrelated] as $file) {
        file_put_contents($file, 'x');
    }

    logger_for($this->dir, ['daily' => ['driver' => 'daily', 'path' => $this->dir.'/logs/slendie.log', 'days' => 7]], 'daily')->info('gc');

    expect(is_file($old))->toBeFalse()->and(is_file($recent))->toBeTrue()->and(is_file($unrelated))->toBeTrue();
});

it('o canal null descarta tudo', function (): void {
    logger_for($this->dir, ['null' => ['driver' => 'null']], 'null')->error('nada');

    expect(glob($this->dir.'/logs/*'))->toBe([]);
});

it('o canal stderr escreve a linha no stderr e não toca em ficheiros', function (): void {
    $logger = new class ($this->dir, 'testing', ['default' => 'stderr', 'channels' => ['stderr' => ['driver' => 'stderr']]]) extends Logger {
        public string $captured = '';

        protected function writeToStderr(string $text): void
        {
            $this->captured .= $text;
        }
    };

    $logger->debug('x');

    expect($logger->captured)->toMatch('/testing\.DEBUG: x\R$/')
        ->and(glob($this->dir.'/logs/*'))->toBe([]);
});

it('o canal stack escreve em todos os canais da lista e detecta ciclos', function (): void {
    $channels = [
        'a' => ['driver' => 'single', 'path' => $this->dir.'/logs/a.log'],
        'b' => ['driver' => 'single', 'path' => $this->dir.'/logs/b.log', 'level' => 'error'],
        'both' => ['driver' => 'stack', 'channels' => ['a', 'b']],
        'loop' => ['driver' => 'stack', 'channels' => ['loop']],
    ];
    $logger = logger_for($this->dir, $channels, 'both');

    $logger->info('só no a');
    $logger->error('nos dois');

    expect((string) file_get_contents($this->dir.'/logs/a.log'))->toContain('só no a')->toContain('nos dois')
        ->and((string) file_get_contents($this->dir.'/logs/b.log'))->not->toContain('só no a')->toContain('nos dois')
        ->and(fn () => $logger->channel('loop')->info('x'))->toThrow(InvalidArgumentException::class, 'refere-se a si próprio');
});

it('channel() escolhe o canal sem alterar o logger original; canal desconhecido lança', function (): void {
    $channels = ['a' => ['driver' => 'single', 'path' => $this->dir.'/logs/a.log'], 'b' => ['driver' => 'single', 'path' => $this->dir.'/logs/b.log']];
    $logger = logger_for($this->dir, $channels, 'a');

    $logger->channel('b')->info('para b');
    $logger->info('para a');

    expect((string) file_get_contents($this->dir.'/logs/b.log'))->toContain('para b')->not->toContain('para a')
        ->and((string) file_get_contents($this->dir.'/logs/a.log'))->toContain('para a')
        ->and(fn () => $logger->channel('nope')->info('x'))->toThrow(InvalidArgumentException::class, 'nope');
});

it('extend() regista drivers personalizados (callable ou objecto com write)', function (): void {
    $lines = [];
    $logger = logger_for($this->dir, [
        'slack' => ['driver' => 'slack', 'webhook' => 'https://x'],
        'obj' => ['driver' => 'obj'],
    ], 'slack');

    $logger->extend('slack', function (array $config) use (&$lines): callable {
        return function (string $line, string $level) use (&$lines, $config): void {
            $lines[] = [$config['webhook'], $level, $line];
        };
    });
    $logger->extend('obj', fn (): object => new class () {
        public string $last = '';

        public function write(string $line, string $level): void
        {
            $this->last = $level;
        }
    });

    $logger->critical('alerta');

    expect($lines)->toHaveCount(1)
        ->and($lines[0][0])->toBe('https://x')
        ->and($lines[0][1])->toBe('critical')
        ->and($lines[0][2])->toContain('testing.CRITICAL: alerta');

    $logger->channel('obj')->info('x');
    expect(fn () => $logger->extend('bad', fn (): int => 1)->channel('bad')->info('x'))->toThrow(InvalidArgumentException::class);
});

it('driver não suportado lança', function (): void {
    expect(fn () => logger_for($this->dir, ['x' => ['driver' => 'syslog']], 'x')->info('a'))
        ->toThrow(InvalidArgumentException::class, 'syslog');
});

it('o LogServiceProvider regista Logger/log com a config logging e a facade Log', function (): void {
    $app = new Application($this->dir);
    $app->singleton(Slendie\Support\Config::class, fn () => new Slendie\Support\Config([
        'app' => ['env' => 'testing'],
        'logging' => ['default' => 'single', 'channels' => ['single' => ['driver' => 'single', 'path' => $this->dir.'/logs/facade.log']]],
    ]));
    $app->register(LogServiceProvider::class);

    Log::info('via facade', ['a' => 1]);
    Log::channel('single')->warning('canal');

    $log = (string) file_get_contents($this->dir.'/logs/facade.log');

    expect($app->make('log'))->toBeInstanceOf(Logger::class)
        ->and($app->make('log'))->toBe($app->make(Logger::class))
        ->and($log)->toContain('testing.INFO: via facade {"a":1}')->toContain('testing.WARNING: canal');

    $app->flush();
});

it('a config da framework dá canais single/daily/stderr/null/stack', function (): void {
    new Application($this->dir);
    $config = require dirname(__DIR__, 3).'/config/logging.php';

    expect($config['channels'])->toHaveKeys(['single', 'daily', 'stderr', 'null', 'stack'])
        ->and($config['default'])->toBe('single')
        ->and(str_replace('\\', '/', $config['channels']['single']['path']))->toBe(str_replace('\\', '/', $this->dir).'/storage/logs/slendie.log');
});

it('logger() sem argumentos devolve o Logger; com mensagem regista em debug; report() usa o Handler', function (): void {
    $app = new Application($this->dir);
    $app->singleton(Slendie\Support\Config::class, fn () => new Slendie\Support\Config([
        'app' => ['env' => 'testing'],
        'logging' => ['default' => 'single', 'channels' => ['single' => ['driver' => 'single', 'path' => $this->dir.'/logs/helpers.log']]],
    ]));
    $app->register(LogServiceProvider::class);

    expect(logger())->toBeInstanceOf(Logger::class);

    logger('msg de debug', ['k' => 'v']);
    report(new RuntimeException('reportada'));

    $log = (string) file_get_contents($this->dir.'/logs/helpers.log');

    expect($log)->toContain('testing.DEBUG: msg de debug {"k":"v"}')
        ->and($log)->toContain('testing.ERROR: reportada')->toContain('[exception] RuntimeException: reportada');

    $app->flush();
});
