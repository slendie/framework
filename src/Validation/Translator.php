<?php

declare(strict_types=1);

namespace Slendie\Validation;

use Slendie\Support\Arr;
use Stringable;

/**
 * Tradutor mínimo: ficheiros `lang/{locale}/{grupo}.php`, fallback de locale, `:placeholders` e pluralização simples.
 *
 * Precedência (a primeira vence): linhas de addLines(), ficheiros do projecto (`{lang_path}/{locale}`),
 * paths extra (addPath) e, por fim, os da framework.
 */
class Translator
{
    /** @var list<string> directórios `lang` com menor precedência que o do projecto */
    protected array $paths = [];

    /** @var array<string, string> namespace => directório `lang` do package */
    protected array $namespaces = [];

    /** @var array<string, array<string, array<string, array<array-key, mixed>>>> locale => namespace => grupo => linhas */
    protected array $loaded = [];

    /** @var array<string, array<string, array<string, array<array-key, mixed>>>> linhas acrescentadas em runtime */
    protected array $added = [];

    /**
     * @param  string  $frameworkPath  directório `lang` da framework
     * @param  string|null  $projectPath  directório `lang` do projecto (sobrepõe a framework)
     */
    public function __construct(
        protected string $frameworkPath,
        protected ?string $projectPath = null,
        protected string $locale = 'pt',
        protected string $fallback = 'en',
    ) {
    }

    // -------------------------------------------------------------------------
    // Configuração
    // -------------------------------------------------------------------------

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getFallback(): string
    {
        return $this->fallback;
    }

    public function setFallback(string $locale): static
    {
        $this->fallback = $locale;

        return $this;
    }

    /** Regista um directório `lang` extra (menor precedência que o do projecto). */
    public function addPath(string $path): static
    {
        $this->paths[] = rtrim($path, '\/');
        $this->loaded = [];

        return $this;
    }

    /** Regista as traduções de um package: `trans('meu-package::mensagens.ola')`. */
    public function addNamespace(string $namespace, string $path): static
    {
        $this->namespaces[$namespace] = rtrim($path, '\/');
        $this->loaded = [];

        return $this;
    }

    /**
     * Acrescenta linhas em runtime: chaves `grupo.item` (ou `namespace::grupo.item`).
     *
     * @param  array<string, mixed>  $lines
     */
    public function addLines(array $lines, string $locale): static
    {
        foreach ($lines as $key => $value) {
            [$namespace, $group, $item] = $this->parseKey($key);

            if ($item === null) {
                continue;
            }

            $bucket = $this->added[$locale][$namespace][$group] ?? [];
            Arr::set($bucket, $item, $value);
            $this->added[$locale][$namespace][$group] = $bucket;
        }

        $this->loaded = [];

        return $this;
    }

    // -------------------------------------------------------------------------
    // Leitura
    // -------------------------------------------------------------------------

    public function has(string $key, ?string $locale = null, bool $fallback = true): bool
    {
        return $this->lookup($key, $locale, $fallback) !== null;
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public function get(string $key, array $replace = [], ?string $locale = null, bool $fallback = true): string
    {
        $line = $this->lookup($key, $locale, $fallback);

        return is_string($line) ? $this->makeReplacements($line, $replace) : $key;
    }

    /** Linha crua (string ou array, como as mensagens de min/max por tipo). */
    public function raw(string $key, ?string $locale = null, bool $fallback = true): mixed
    {
        return $this->lookup($key, $locale, $fallback);
    }

    /**
     * Pluralização simples: `{0} nenhum|{1} um|[2,*] :count itens` ou `um|vários` (singular quando n = 1).
     *
     * @param  array<string, mixed>  $replace
     */
    public function choice(string $key, int|float $number, array $replace = [], ?string $locale = null): string
    {
        $line = $this->lookup($key, $locale, true);
        $line = is_string($line) ? $line : $key;

        $replace += ['count' => $number];

        return $this->makeReplacements($this->choose($line, $number), $replace);
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    protected function lookup(string $key, ?string $locale, bool $fallback): mixed
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        if ($item === null) {
            return null;
        }

        $primary = $locale ?? $this->locale;
        $locales = $fallback ? array_values(array_unique([$primary, $this->fallback])) : [$primary];

        foreach ($locales as $candidate) {
            $value = Arr::get($this->load($candidate, $namespace, $group), $item);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string, 2: string|null} [namespace, grupo, item]
     */
    protected function parseKey(string $key): array
    {
        $namespace = '*';

        if (str_contains($key, '::')) {
            [$namespace, $key] = explode('::', $key, 2);
        }

        $segments = explode('.', $key, 2);

        return [$namespace, $segments[0], $segments[1] ?? null];
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function load(string $locale, string $namespace, string $group): array
    {
        if (isset($this->loaded[$locale][$namespace][$group])) {
            return $this->loaded[$locale][$namespace][$group];
        }

        if ($namespace === '*') {
            $directories = [$this->frameworkPath, ...$this->paths];

            if ($this->projectPath !== null) {
                $directories[] = $this->projectPath;
            }
        } else {
            $directories = isset($this->namespaces[$namespace]) ? [$this->namespaces[$namespace]] : [];

            if ($this->projectPath !== null) {
                // O projecto sobrepõe um package em `lang/vendor/{namespace}`.
                $directories[] = $this->projectPath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.$namespace;
            }
        }

        $lines = [];

        foreach ($directories as $directory) {
            $lines = array_replace_recursive($lines, $this->requireFile($directory.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$group.'.php'));
        }

        $lines = array_replace_recursive($lines, $this->added[$locale][$namespace][$group] ?? []);

        return $this->loaded[$locale][$namespace][$group] = $lines;
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function requireFile(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $lines = require $file;

        return is_array($lines) ? $lines : [];
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public function makeReplacements(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        $pairs = [];

        foreach ($replace as $name => $value) {
            $value = is_scalar($value) || $value instanceof Stringable ? (string) $value : '';
            $name = (string) $name;

            $pairs[':'.strtoupper($name)] = strtoupper($value);
            $pairs[':'.ucfirst($name)] = ucfirst($value);
            $pairs[':'.$name] = $value;
        }

        // Os mais longos primeiro, para que :attributes não seja apanhado por :attribute.
        uksort($pairs, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return strtr($line, $pairs);
    }

    protected function choose(string $line, int|float $number): string
    {
        $segments = explode('|', $line);

        foreach ($segments as $segment) {
            if (preg_match('/^(?:\{(-?\d+(?:\.\d+)?)\}|\[(-?\d+|\*),\s*(-?\d+|\*)\])\s*(.*)$/s', trim($segment), $m) !== 1) {
                continue;
            }

            if ($m[1] !== '') {
                if ((float) $m[1] === (float) $number) {
                    return $m[4];
                }

                continue;
            }

            $min = $m[2] === '*' ? null : (float) $m[2];
            $max = $m[3] === '*' ? null : (float) $m[3];

            if (($min === null || $number >= $min) && ($max === null || $number <= $max)) {
                return $m[4];
            }
        }

        // Sem intervalos: "singular|plural".
        $plain = array_values(array_filter(
            array_map('trim', $segments),
            static fn (string $s): bool => preg_match('/^[\{\[]/', $s) !== 1,
        ));

        if ($plain === []) {
            return $line;
        }

        return (float) $number === 1.0 || count($plain) === 1 ? $plain[0] : $plain[1];
    }
}
