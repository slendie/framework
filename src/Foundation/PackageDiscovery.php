<?php

declare(strict_types=1);

namespace Slendie\Foundation;

use LogicException;

/**
 * Descobre os packages Slendie instalados: lê `vendor/composer/installed.json` e, de cada package, `extra.slendie.providers`
 * e `extra.slendie.aliases`. O projecto pode excluir packages com `extra.slendie.dont-discover` no seu `composer.json`
 * (nomes de packages ou `*`).
 *
 * O resultado pode ser guardado em `bootstrap/cache/packages.php` (`package:discover`); sem cache, descobre em runtime
 * (custo: ler um JSON).
 *
 * @phpstan-type Manifest array{providers: list<string>, aliases: array<string, string>, dont-discover: list<string>, packages: array<string, list<string>>}
 */
final class PackageDiscovery
{
    /**
     * @return Manifest
     */
    public function discover(string $vendorPath): array
    {
        $vendorPath = rtrim($vendorPath, '\/');
        $ignored = $this->ignored(dirname($vendorPath).DIRECTORY_SEPARATOR.'composer.json');
        $ignoreAll = in_array('*', $ignored, true);

        $manifest = ['providers' => [], 'aliases' => [], 'dont-discover' => $ignored, 'packages' => []];

        foreach ($this->installedPackages($vendorPath.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'installed.json') as $package) {
            $name = $package['name'] ?? null;
            $extra = $package['extra']['slendie'] ?? null;

            if (! is_string($name) || ! is_array($extra) || $ignoreAll || in_array($name, $ignored, true)) {
                continue;
            }

            $providers = array_values(array_filter((array) ($extra['providers'] ?? []), is_string(...)));
            $aliases = is_array($extra['aliases'] ?? null) ? $extra['aliases'] : [];

            if ($providers === [] && $aliases === []) {
                continue;
            }

            $manifest['packages'][$name] = $providers;
            $manifest['providers'] = [...$manifest['providers'], ...array_map(static fn (string $p): string => ltrim($p, '\\'), $providers)];

            foreach ($aliases as $alias => $class) {
                if (is_string($alias) && is_string($class)) {
                    $manifest['aliases'][$alias] = ltrim($class, '\\');
                }
            }
        }

        $manifest['providers'] = array_values(array_unique($manifest['providers']));

        return $manifest;
    }

    /**
     * Devolve o cache se existir; senão descobre.
     *
     * @return Manifest
     */
    public function load(string $vendorPath, string $cachePath): array
    {
        if (is_file($cachePath)) {
            $cached = require $cachePath;

            if (is_array($cached) && isset($cached['providers']) && is_array($cached['providers'])) {
                /** @var Manifest $cached */
                return $cached + ['aliases' => [], 'dont-discover' => [], 'packages' => []];
            }
        }

        return $this->discover($vendorPath);
    }

    /**
     * @param  Manifest  $manifest
     */
    public function write(string $cachePath, array $manifest): void
    {
        $directory = dirname($cachePath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new LogicException("Não foi possível criar a pasta [{$directory}].");
        }

        $contents = "<?php\n\ndeclare(strict_types=1);\n\n// Gerado por `php slendie package:discover`. Não editar.\nreturn ".var_export($manifest, true).";\n";

        if (file_put_contents($cachePath, $contents, LOCK_EX) === false) {
            throw new LogicException("Não foi possível escrever [{$cachePath}].");
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($cachePath, true);
        }
    }

    public function clear(string $cachePath): void
    {
        if (is_file($cachePath)) {
            @unlink($cachePath);
        }
    }

    /**
     * Formato Composer 2 (`{"packages": [...]}`) e Composer 1 (lista).
     *
     * @return list<array<string, mixed>>
     */
    private function installedPackages(string $file): array
    {
        $data = $this->readJson($file);

        $packages = isset($data['packages']) && is_array($data['packages']) ? $data['packages'] : $data;

        return array_values(array_filter($packages, is_array(...)));
    }

    /**
     * @return list<string>
     */
    private function ignored(string $composerJson): array
    {
        $data = $this->readJson($composerJson);
        $ignored = $data['extra']['slendie']['dont-discover'] ?? [];

        return is_array($ignored) ? array_values(array_filter($ignored, is_string(...))) : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readJson(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }
}
