<?php

declare(strict_types=1);

namespace Slendie\Log;

use Closure;
use InvalidArgumentException;
use Stringable;
use Throwable;

/**
 * Logger com a forma do PSR-3 (sem a dependência), vários canais e uma linha de log estável:
 *
 *     [2026-10-13 10:00:00.123] local.ERROR: mensagem {"id":1}
 *
 * Excepções no contexto (`['exception' => $e]`) acrescentam classe, mensagem, ficheiro:linha e trace.
 *
 * Canais (`config/logging.php`): `single`, `daily`, `stderr`, `null` e `stack` (lista de canais).
 * `Logger::extend('slack', fn (array $config) => ...)` acrescenta drivers; o Closure devolve um
 * `callable(string $line, string $level): void` (ou um objecto com `write(string $line, string $level)`).
 */
class Logger
{
    /** Gravidade de cada nível (quanto maior, mais grave). */
    public const array LEVELS = [
        'debug' => 100,
        'info' => 200,
        'notice' => 250,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
        'alert' => 550,
        'emergency' => 600,
    ];

    /** @var array<string, Closure(array<string, mixed>): mixed> */
    protected array $customDrivers = [];

    /** @var array<string, Closure(string, string): void> canais já criados */
    protected array $resolved = [];

    /** @var array<string, true> ficheiros de logs diários já limpos neste processo */
    protected array $collected = [];

    /**
     * @param  string  $storagePath  base de `storage/` (o canal `single` por omissão escreve em `logs/slendie.log`)
     * @param  string|Closure(): string  $environment
     * @param  array{default?: string, channels?: array<string, array<string, mixed>>}  $config
     */
    public function __construct(
        protected string $storagePath,
        protected string|Closure $environment = 'production',
        protected array $config = [],
        protected ?string $channel = null,
    ) {
    }

    // -------------------------------------------------------------------------
    // Canais
    // -------------------------------------------------------------------------

    /** Um logger que escreve apenas no canal indicado (ou no `default`). */
    public function channel(?string $name = null): static
    {
        $clone = clone $this;
        $clone->channel = $name;

        return $clone;
    }

    /** Regista um driver personalizado, usável em `config/logging.php` com `'driver' => $driver`. */
    public function extend(string $driver, Closure $factory): static
    {
        $this->customDrivers[$driver] = $factory;
        $this->resolved = [];

        return $this;
    }

    public function getDefaultChannel(): string
    {
        $default = $this->config['default'] ?? null;

        return is_string($default) && $default !== '' ? $default : 'single';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getChannels(): array
    {
        return $this->config['channels'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Níveis
    // -------------------------------------------------------------------------

    /** @param  array<string, mixed>  $context */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param  array<string, mixed>  $context */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $level, string|Stringable $message, array $context = []): void
    {
        $level = strtolower($level);

        if (! isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException("Nível de log desconhecido: [{$level}].");
        }

        $line = $this->format($level, (string) $message, $context);
        $write = $this->resolveChannel($this->channel ?? $this->getDefaultChannel());

        $write($line, $level);
    }

    // -------------------------------------------------------------------------
    // Formato
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $context
     */
    public function format(string $level, string $message, array $context = []): string
    {
        $exception = $context['exception'] ?? null;
        unset($context['exception']);

        $message = $this->interpolate($message, $context);

        $line = sprintf(
            '[%s] %s.%s: %s',
            (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            $this->environment(),
            strtoupper($level),
            $message,
        );

        if ($context !== []) {
            $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            $line .= ' '.($json === false ? '{}' : $json);
        }

        if ($exception instanceof Throwable) {
            $line .= "\n".$this->formatException($exception);
        }

        return $line;
    }

    /**
     * Substitui `{chave}` pelos valores escalares/Stringable do contexto.
     *
     * @param  array<string, mixed>  $context
     */
    protected function interpolate(string $message, array $context): string
    {
        if (! str_contains($message, '{')) {
            return $message;
        }

        $pairs = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null || $value instanceof Stringable) {
                $pairs['{'.$key.'}'] = match (true) {
                    $value === null => 'null',
                    is_bool($value) => $value ? 'true' : 'false',
                    default => (string) $value,
                };
            }
        }

        return strtr($message, $pairs);
    }

    protected function formatException(Throwable $e): string
    {
        $out = sprintf("[exception] %s: %s in %s:%d\n[stacktrace]\n%s", $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());

        $previous = $e->getPrevious();

        while ($previous !== null) {
            $out .= sprintf("\n[previous] %s: %s in %s:%d", $previous::class, $previous->getMessage(), $previous->getFile(), $previous->getLine());
            $previous = $previous->getPrevious();
        }

        return $out;
    }

    protected function environment(): string
    {
        return $this->environment instanceof Closure ? ($this->environment)() : $this->environment;
    }

    // -------------------------------------------------------------------------
    // Resolução de canais
    // -------------------------------------------------------------------------

    /**
     * @param  list<string>  $stack  canais em resolução (detecta ciclos em `stack`)
     * @return Closure(string, string): void
     */
    protected function resolveChannel(string $name, array $stack = []): Closure
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (in_array($name, $stack, true)) {
            throw new InvalidArgumentException("O canal de log [{$name}] refere-se a si próprio (stack).");
        }

        $config = $this->getChannels()[$name] ?? null;

        if ($config === null) {
            // Sem configuração, `single` é o canal implícito.
            if ($name !== 'single') {
                throw new InvalidArgumentException("O canal de log [{$name}] não está definido em config/logging.php.");
            }

            $config = ['driver' => 'single'];
        }

        $driver = $config['driver'] ?? $name;
        $driver = is_string($driver) ? $driver : $name;

        $writer = match (true) {
            isset($this->customDrivers[$driver]) => $this->customWriter($driver, $config),
            $driver === 'single' => $this->fileWriter($this->path($config), false, 0),
            $driver === 'daily' => $this->fileWriter($this->path($config), true, $this->intValue($config['days'] ?? 14, 14)),
            $driver === 'stderr' => $this->stderrWriter(),
            $driver === 'null' => static function (string $line, string $level): void {
            },
            $driver === 'stack' => $this->stackWriter($config, [...$stack, $name]),
            default => throw new InvalidArgumentException("O driver de log [{$driver}] não é suportado (canal [{$name}])."),
        };

        $configured = $config['level'] ?? 'debug';
        $minimum = self::LEVELS[strtolower(is_string($configured) ? $configured : 'debug')] ?? self::LEVELS['debug'];

        if ($minimum > self::LEVELS['debug'] && $driver !== 'stack') {
            $inner = $writer;
            $writer = static function (string $line, string $level) use ($inner, $minimum): void {
                if (self::LEVELS[$level] >= $minimum) {
                    $inner($line, $level);
                }
            };
        }

        return $this->resolved[$name] = $writer;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return Closure(string, string): void
     */
    protected function customWriter(string $driver, array $config): Closure
    {
        $made = ($this->customDrivers[$driver])($config);

        if (is_object($made) && method_exists($made, 'write')) {
            return static function (string $line, string $level) use ($made): void {
                $made->write($line, $level);
            };
        }

        if (is_callable($made)) {
            return static function (string $line, string $level) use ($made): void {
                $made($line, $level);
            };
        }

        throw new InvalidArgumentException("O driver de log [{$driver}] deve devolver um callable ou um objecto com write().");
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $stack
     * @return Closure(string, string): void
     */
    protected function stackWriter(array $config, array $stack): Closure
    {
        $channels = array_values(array_filter((array) ($config['channels'] ?? []), is_string(...)));
        $writers = array_map(fn (string $channel): Closure => $this->resolveChannel($channel, $stack), $channels);

        return static function (string $line, string $level) use ($writers): void {
            foreach ($writers as $writer) {
                $writer($line, $level);
            }
        };
    }

    /**
     * @return Closure(string, string): void
     */
    protected function stderrWriter(): Closure
    {
        return function (string $line, string $level): void {
            $this->writeToStderr($line.PHP_EOL);
        };
    }

    protected function writeToStderr(string $text): void
    {
        file_put_contents('php://stderr', $text);
    }

    /**
     * @return Closure(string, string): void
     */
    protected function fileWriter(string $path, bool $daily, int $days): Closure
    {
        return function (string $line, string $level) use ($path, $daily, $days): void {
            $file = $daily ? $this->dailyPath($path) : $path;
            $directory = dirname($file);

            if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
                return;
            }

            @file_put_contents($file, $line.PHP_EOL, FILE_APPEND | LOCK_EX);

            if ($daily && ! isset($this->collected[$path])) {
                $this->collected[$path] = true;
                $this->collectGarbage($path, $days);
            }
        };
    }

    /** `logs/slendie.log` → `logs/slendie-2026-10-13.log`. */
    protected function dailyPath(string $path, ?string $date = null): string
    {
        $info = pathinfo($path);
        $extension = isset($info['extension']) ? '.'.$info['extension'] : '';

        return dirname($path).DIRECTORY_SEPARATOR.$info['filename'].'-'.($date ?? date('Y-m-d')).$extension;
    }

    /** Apaga os ficheiros diários com mais de $days dias. */
    protected function collectGarbage(string $path, int $days): void
    {
        if ($days < 1) {
            return;
        }

        $info = pathinfo($path);
        $extension = isset($info['extension']) ? '.'.$info['extension'] : '';
        $pattern = dirname($path).DIRECTORY_SEPARATOR.$info['filename'].'-????-??-??'.$extension;
        $cutoff = date('Y-m-d', strtotime("-{$days} days") ?: time());

        foreach (glob($pattern) ?: [] as $file) {
            if (preg_match('/-(\d{4}-\d{2}-\d{2})'.preg_quote($extension, '/').'$/', $file, $m) === 1 && $m[1] < $cutoff) {
                @unlink($file);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function path(array $config): string
    {
        $path = $config['path'] ?? null;

        return is_string($path) && $path !== '' ? $path : rtrim($this->storagePath, '\/').DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'slendie.log';
    }

    private function intValue(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}
