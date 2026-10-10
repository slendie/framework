<?php

declare(strict_types=1);

namespace Slendie\Support;

/**
 * Leitor de ficheiros .env sem dependências.
 *
 * Suporta: comentários (#), KEY=value, KEY="com espaços" (com escapes \n \" \\),
 * KEY='literal', export KEY=..., interpolação ${OUTRA} e conversão de true/false/null/empty.
 */
final class Env
{
    /** @var array<string, mixed> */
    private static array $vars = [];

    public static function load(string $path, bool $overwrite = false): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            return;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return;
        }

        foreach (self::parse($contents) as $key => $value) {
            if (! $overwrite && self::has($key)) {
                continue;
            }

            self::set($key, $value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function parse(string $contents): array
    {
        $result = [];
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $eq = strpos($line, '=');

            if ($eq === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eq));
            $raw = trim(substr($line, $eq + 1));

            if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $key) !== 1) {
                continue;
            }

            $result[$key] = self::parseValue($raw, $result);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function parseValue(string $raw, array $context): mixed
    {
        if ($raw === '') {
            return '';
        }

        $first = $raw[0];

        // Valor com aspas duplas: suporta escapes e interpolação.
        if ($first === '"') {
            $end = self::findClosingQuote($raw, '"');
            $inner = substr($raw, 1, $end - 1);
            $inner = str_replace(['\\n', '\\r', '\\t', '\\"', '\\\\'], ["\n", "\r", "\t", '"', '\\'], $inner);

            return self::interpolate($inner, $context);
        }

        // Valor com plicas: literal.
        if ($first === "'") {
            $end = self::findClosingQuote($raw, "'");

            return substr($raw, 1, $end - 1);
        }

        // Sem aspas: remove comentário inline e converte literais.
        $hash = strpos($raw, ' #');
        if ($hash !== false) {
            $raw = rtrim(substr($raw, 0, $hash));
        }

        $raw = self::interpolate($raw, $context);

        return match (strtolower($raw)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $raw,
        };
    }

    private static function findClosingQuote(string $raw, string $quote): int
    {
        $length = strlen($raw);

        for ($i = 1; $i < $length; $i++) {
            if ($raw[$i] === '\\' && $quote === '"') {
                $i++;

                continue;
            }

            if ($raw[$i] === $quote) {
                return $i;
            }
        }

        return $length;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function interpolate(string $value, array $context): string
    {
        if (! str_contains($value, '${')) {
            return $value;
        }

        return (string) preg_replace_callback(
            '/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static function (array $m) use ($context): string {
                $name = $m[1];

                if (array_key_exists($name, $context)) {
                    return self::stringify($context[$name]);
                }

                return self::stringify(self::get($name, ''));
            },
            $value,
        );
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === true => 'true',
            $value === false => 'false',
            $value === null => '',
            default => (string) $value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$vars[$key] = $value;
        $_ENV[$key] = $value;
        putenv($key.'='.self::stringify($value));
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$vars)
            || array_key_exists($key, $_ENV)
            || getenv($key) !== false;
    }

    public static function forget(string $key): void
    {
        unset(self::$vars[$key], $_ENV[$key]);
        putenv($key);
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$vars;
    }

    /**
     * Esquece tudo o que foi carregado via Env (útil em testes).
     */
    public static function flush(): void
    {
        foreach (array_keys(self::$vars) as $key) {
            self::forget($key);
        }

        self::$vars = [];
    }
}
