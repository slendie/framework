<?php

declare(strict_types=1);

namespace Slendie\View;

/**
 * Resolve o nome de uma view (`layouts.app`, `auth::login`) para o ficheiro `.blade.php`.
 */
class Finder
{
    public const EXTENSION = '.blade.php';

    /** @var array<string, string> */
    protected array $found = [];

    /**
     * @param  list<string>  $paths  pastas por ordem de prioridade
     * @param  array<string, list<string>>  $hints  namespace => pastas
     */
    public function __construct(protected array $paths = [], protected array $hints = [])
    {
    }

    /**
     * @throws ViewNotFoundException
     */
    public function find(string $name): string
    {
        if (isset($this->found[$name])) {
            return $this->found[$name];
        }

        [$namespace, $view] = $this->parse($name);

        if ($namespace === null) {
            $directories = $this->paths;
        } elseif (isset($this->hints[$namespace])) {
            $directories = $this->hints[$namespace];
        } else {
            throw new ViewNotFoundException("O namespace de views [{$namespace}] não está registado (view [{$name}]).");
        }

        $relative = str_replace('.', '/', $view).self::EXTENSION;
        $searched = [];

        foreach ($directories as $directory) {
            $file = rtrim($directory, '/\\').'/'.$relative;

            if (is_file($file)) {
                return $this->found[$name] = $file;
            }

            $searched[] = $file;
        }

        throw new ViewNotFoundException("View [{$name}] não encontrada.", $searched);
    }

    public function exists(string $name): bool
    {
        try {
            $this->find($name);

            return true;
        } catch (ViewNotFoundException) {
            return false;
        }
    }

    public function addLocation(string $path): void
    {
        $this->paths[] = $path;
        $this->flush();
    }

    public function prependLocation(string $path): void
    {
        array_unshift($this->paths, $path);
        $this->flush();
    }

    /**
     * @param  string|list<string>  $paths
     */
    public function addNamespace(string $namespace, string|array $paths): void
    {
        $this->hints[$namespace] = [...($this->hints[$namespace] ?? []), ...(array) $paths];
        $this->flush();
    }

    /**
     * As pastas indicadas passam a ter prioridade sobre as já registadas.
     *
     * @param  string|list<string>  $paths
     */
    public function prependNamespace(string $namespace, string|array $paths): void
    {
        $this->hints[$namespace] = [...(array) $paths, ...($this->hints[$namespace] ?? [])];
        $this->flush();
    }

    /** @return list<string> */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /** @return array<string, list<string>> */
    public function getHints(): array
    {
        return $this->hints;
    }

    public function flush(): void
    {
        $this->found = [];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    protected function parse(string $name): array
    {
        if (preg_match('/^(?:([A-Za-z0-9_\-]+)::)?([A-Za-z0-9_\-]+(?:\.[A-Za-z0-9_\-]+)*)$/', $name, $m) !== 1) {
            throw new ViewNotFoundException("Nome de view inválido: [{$name}].");
        }

        return [$m[1] !== '' ? $m[1] : null, $m[2]];
    }
}
