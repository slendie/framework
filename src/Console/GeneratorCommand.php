<?php

declare(strict_types=1);

namespace Slendie\Console;

use InvalidArgumentException;
use RuntimeException;
use Slendie\Foundation\Application as FoundationApplication;

/**
 * Base dos `make:*`: resolve o nome, escolhe o stub (o do projecto em `stubs/` tem prioridade), substitui os
 * marcadores `{{ namespace }}`, `{{ class }}`, `{{ rootNamespace }}` (e os de `replacements()`) e escreve o ficheiro.
 *
 * Subclasses definem `$type`, `stub()` e, se necessário, `defaultNamespace()`/`replacements()`.
 */
abstract class GeneratorCommand extends Command
{
    /** Nome legível para as mensagens: "Controller". */
    protected string $type = 'Class';

    /** Ficheiro do stub, ex.: `middleware.stub`. */
    abstract protected function stub(): string;

    /** Namespace de destino quando o nome não é qualificado. */
    protected function defaultNamespace(): string
    {
        return rtrim($this->rootNamespace(), '\\');
    }

    /**
     * Marcadores adicionais do stub.
     *
     * @return array<string, string>
     */
    protected function replacements(string $name): array
    {
        return [];
    }

    public function handle(): int
    {
        try {
            $name = $this->qualifyName((string) $this->argument('name'));
            $path = $this->getPath($name);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->alreadyExists($path) && $this->option('force') !== true) {
            $this->error("{$this->type} already exists: [{$this->relativePath($path)}].");

            return self::FAILURE;
        }

        $this->write($path, $this->buildClass($name));

        $this->badge('INFO', "{$this->type} [{$this->relativePath($path)}] created successfully.");

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Nomes e caminhos
    // -------------------------------------------------------------------------

    protected function rootNamespace(): string
    {
        return AppNamespace::resolve($this->basePath());
    }

    /**
     * Normaliza `Admin/Lead` em `Admin\Lead`, recusando qualquer coisa que não seja um identificador PHP.
     *
     * @throws InvalidArgumentException nome vazio ou com caracteres inválidos
     */
    protected function normalizeName(string $name): string
    {
        $name = trim(str_replace('/', '\\', $name), '\\');

        foreach (explode('\\', $name) as $segment) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
                throw new InvalidArgumentException("Nome inválido [{$name}]: usa só letras, números e _ (e / para sub-pastas).");
            }
        }

        return $name;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function qualifyName(string $name): string
    {
        $name = $this->normalizeName($name);
        $root = $this->rootNamespace();

        return str_starts_with($name.'\\', $root) ? $name : $this->defaultNamespace().'\\'.$name;
    }

    /** Caminho do ficheiro para o nome qualificado (`App\Http\X` → `app/Http/X.php`). */
    protected function getPath(string $name): string
    {
        $root = $this->rootNamespace();
        $relative = str_starts_with($name.'\\', $root) ? substr($name, strlen($root)) : $name;

        return $this->appInstance()->appPath(str_replace('\\', DIRECTORY_SEPARATOR, $relative).'.php');
    }

    protected function alreadyExists(string $path): bool
    {
        return file_exists($path);
    }

    protected function basePath(string $path = ''): string
    {
        return $this->appInstance()->basePath($path);
    }

    protected function appInstance(): FoundationApplication
    {
        $app = $this->container();

        if (! $app instanceof FoundationApplication) {
            throw new RuntimeException('Os comandos make:* precisam da Application da Slendie.');
        }

        return $app;
    }

    /** Caminho relativo à raiz do projecto, com `/`. */
    protected function relativePath(string $path): string
    {
        $base = rtrim($this->basePath(), '/\\').DIRECTORY_SEPARATOR;
        $relative = str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;

        return str_replace('\\', '/', $relative);
    }

    // -------------------------------------------------------------------------
    // Conteúdo
    // -------------------------------------------------------------------------

    protected function buildClass(string $name): string
    {
        $separator = strrpos($name, '\\');

        $replace = [
            'namespace' => $separator === false ? '' : substr($name, 0, $separator),
            'class' => $separator === false ? $name : substr($name, $separator + 1),
            'rootNamespace' => $this->rootNamespace(),
            ...$this->replacements($name),
        ];

        return $this->replaceMarkers($this->stubContents(), $replace);
    }

    /**
     * @param  array<string, string>  $replace
     */
    protected function replaceMarkers(string $contents, array $replace): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            static fn (array $m): string => $replace[$m[1]] ?? $m[0],
            $contents,
        );
    }

    /** Stub do projecto (`stubs/`) ou, na falta dele, o da framework. */
    protected function stubContents(): string
    {
        $stub = $this->stub();

        foreach ([$this->basePath('stubs/'.$stub), dirname(__DIR__, 2).'/stubs/'.$stub] as $file) {
            if (is_file($file)) {
                $contents = file_get_contents($file);

                if ($contents !== false) {
                    return $contents;
                }
            }
        }

        throw new RuntimeException("Stub não encontrado: [{$stub}].");
    }

    protected function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Não foi possível criar a pasta [{$directory}].");
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Não foi possível escrever [{$path}].");
        }
    }
}
