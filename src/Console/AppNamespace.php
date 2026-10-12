<?php

declare(strict_types=1);

namespace Slendie\Console;

/**
 * Namespace raiz da aplicação: a chave PSR-4 do `composer.json` que aponta para `app/` (por omissão `App\`).
 */
final class AppNamespace
{
    public static function resolve(string $basePath): string
    {
        $file = rtrim($basePath, '/\\').DIRECTORY_SEPARATOR.'composer.json';
        $json = is_file($file) ? file_get_contents($file) : false;
        $composer = $json === false ? null : json_decode($json, true);

        $psr4 = is_array($composer) && is_array($composer['autoload'] ?? null) && is_array($composer['autoload']['psr-4'] ?? null)
            ? $composer['autoload']['psr-4']
            : [];

        foreach ($psr4 as $namespace => $path) {
            if (is_string($path) && trim($path, '/\\') === 'app' && is_string($namespace) && $namespace !== '') {
                return rtrim($namespace, '\\').'\\';
            }
        }

        return 'App\\';
    }
}
