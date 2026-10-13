<?php

declare(strict_types=1);

namespace Slendie\Support;

use Closure;
use Slendie\Exceptions\ViteException;
use Slendie\Exceptions\ViteManifestNotFoundException;

/**
 * Gera as tags `<script>`/`<link>` dos assets compilados pelo Vite.
 *
 * - Em desenvolvimento (existe o ficheiro `public/hot` com o URL do servidor do Vite) aponta para o dev server.
 * - Em produção lê `public/{build}/manifest.json` e emite os `<link rel="stylesheet">`, `<link rel="modulepreload">`
 *   e `<script type="module">` de cada entrada (com os seus imports, recursivamente).
 *
 * O `laravel-vite-plugin` escreve o ficheiro `public/hot` e o `manifest.json` neste formato; funciona com qualquer
 * backend, por isso é o plugin usado pelo `react-admin`.
 *
 * Directivas Blade: `@vite([...])` e `@viteReactRefresh`.
 */
class Vite
{
    protected string $manifestFilename = 'manifest.json';

    protected ?string $nonce = null;

    /** @var array<string, array<string, mixed>> manifestos já lidos (por caminho) */
    protected static array $manifests = [];

    /** @var (Closure(string): string)|null */
    protected ?Closure $assetUrl = null;

    /**
     * @param  string  $publicPath  pasta `public/`
     * @param  string  $buildDirectory  sub-pasta de `public/` com o resultado de `vite build`
     * @param  string|null  $hotFile  caminho absoluto do ficheiro `hot` (por omissão `{publicPath}/hot`)
     * @param  list<string>  $entryPoints  entradas usadas por `@vite` sem argumentos
     */
    public function __construct(
        protected string $publicPath,
        protected string $buildDirectory = 'build',
        protected ?string $hotFile = null,
        protected array $entryPoints = [],
    ) {
        $this->publicPath = rtrim($publicPath, '\/');
    }

    // -------------------------------------------------------------------------
    // Configuração
    // -------------------------------------------------------------------------

    public function useBuildDirectory(string $path): static
    {
        $this->buildDirectory = trim($path, '/\\');

        return $this;
    }

    public function useHotFile(string $path): static
    {
        $this->hotFile = $path;

        return $this;
    }

    public function useManifestFilename(string $filename): static
    {
        $this->manifestFilename = $filename;

        return $this;
    }

    /**
     * @param  list<string>  $entryPoints
     */
    public function withEntryPoints(array $entryPoints): static
    {
        $this->entryPoints = $entryPoints;

        return $this;
    }

    /**
     * Acrescenta `nonce="..."` a todas as tags (Content-Security-Policy). Sem argumento gera um nonce novo.
     */
    public function useCspNonce(?string $nonce = null): string
    {
        return $this->nonce = $nonce ?? base64_encode(random_bytes(16));
    }

    public function cspNonce(): ?string
    {
        return $this->nonce;
    }

    /**
     * Como converter `build/assets/app.js` num URL (por omissão `/build/assets/app.js`, relativo à raiz do site).
     *
     * @param  Closure(string): string  $resolver
     */
    public function resolveAssetUrlUsing(Closure $resolver): static
    {
        $this->assetUrl = $resolver;

        return $this;
    }

    public static function flushState(): void
    {
        static::$manifests = [];
    }

    // -------------------------------------------------------------------------
    // Estado
    // -------------------------------------------------------------------------

    public function hotFile(): string
    {
        return $this->hotFile ?? $this->publicPath.DIRECTORY_SEPARATOR.'hot';
    }

    public function isRunningHot(): bool
    {
        return is_file($this->hotFile());
    }

    /** URL do servidor de desenvolvimento (sem barra final). */
    protected function hotUrl(): string
    {
        return rtrim(trim((string) file_get_contents($this->hotFile())), '/');
    }

    // -------------------------------------------------------------------------
    // Tags
    // -------------------------------------------------------------------------

    /**
     * @param  string|list<string>  $entrypoints
     */
    public function __invoke(string|array $entrypoints = [], ?string $buildDirectory = null): string
    {
        $entrypoints = (array) $entrypoints;

        if ($entrypoints === []) {
            $entrypoints = $this->entryPoints;
        }

        $buildDirectory = $buildDirectory === null ? $this->buildDirectory : trim($buildDirectory, '/\\');

        if ($this->isRunningHot()) {
            return $this->hotTags($entrypoints);
        }

        return $this->buildTags($entrypoints, $buildDirectory);
    }

    /**
     * @param  list<string>  $entrypoints
     */
    protected function hotTags(array $entrypoints): string
    {
        $hot = $this->hotUrl();
        $tags = [$this->scriptTag($hot.'/@vite/client')];

        foreach ($entrypoints as $entry) {
            $url = $hot.'/'.ltrim($entry, '/');
            $tags[] = $this->isCss($entry) ? $this->styleTag($url) : $this->scriptTag($url);
        }

        return implode("\n", $tags);
    }

    /**
     * @param  list<string>  $entrypoints
     */
    protected function buildTags(array $entrypoints, string $buildDirectory): string
    {
        $manifest = $this->manifest($buildDirectory);

        $preloads = [];
        $styles = [];
        $scripts = [];

        foreach ($entrypoints as $entry) {
            $chunk = $this->chunk($manifest, $entry, $buildDirectory);

            foreach ($this->importedChunks($manifest, $chunk, $buildDirectory) as $import) {
                $url = $this->url($buildDirectory, $this->fileOf($import));
                $preloads[$url] = $this->preloadTag($url);

                foreach ($this->cssOf($import) as $css) {
                    $styles[$this->url($buildDirectory, $css)] = true;
                }
            }

            $file = $this->fileOf($chunk);
            $url = $this->url($buildDirectory, $file);

            if ($this->isCss($file)) {
                $styles[$url] = true;
            } else {
                $scripts[$url] = $this->scriptTag($url);

                foreach ($this->cssOf($chunk) as $css) {
                    $styles[$this->url($buildDirectory, $css)] = true;
                }
            }
        }

        $tags = [...array_values($preloads)];

        foreach (array_keys($styles) as $url) {
            $tags[] = $this->styleTag($url);
        }

        return implode("\n", [...$tags, ...array_values($scripts)]);
    }

    /**
     * Preamble do React Fast Refresh (só em desenvolvimento): `@viteReactRefresh`.
     */
    public function reactRefresh(): string
    {
        if (! $this->isRunningHot()) {
            return '';
        }

        $hot = $this->hotUrl();
        $nonce = $this->nonceAttribute();

        return <<<HTML
            <script type="module"{$nonce}>
                import RefreshRuntime from '{$hot}/@react-refresh'
                RefreshRuntime.injectIntoGlobalHook(window)
                window.\$RefreshReg\$ = () => {}
                window.\$RefreshSig\$ = () => (type) => type
                window.__vite_plugin_react_preamble_installed__ = true
            </script>
            HTML;
    }

    // -------------------------------------------------------------------------
    // Assets individuais
    // -------------------------------------------------------------------------

    /**
     * URL de um asset (imagem, fonte...) processado pelo Vite: `resources/img/logo.svg` → `/build/assets/logo-abc.svg`.
     */
    public function asset(string $path, ?string $buildDirectory = null): string
    {
        $buildDirectory = $buildDirectory === null ? $this->buildDirectory : trim($buildDirectory, '/\\');

        if ($this->isRunningHot()) {
            return $this->hotUrl().'/'.ltrim($path, '/');
        }

        $chunk = $this->chunk($this->manifest($buildDirectory), $path, $buildDirectory);

        return $this->url($buildDirectory, $this->fileOf($chunk));
    }

    /** Conteúdo (inline) de um asset compilado. */
    public function content(string $path, ?string $buildDirectory = null): string
    {
        $buildDirectory = $buildDirectory === null ? $this->buildDirectory : trim($buildDirectory, '/\\');
        $chunk = $this->chunk($this->manifest($buildDirectory), $path, $buildDirectory);
        $file = $this->publicPath.DIRECTORY_SEPARATOR.$buildDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $this->fileOf($chunk));

        if (! is_file($file)) {
            throw new ViteException("Unable to locate Vite asset file: {$file}.");
        }

        return (string) file_get_contents($file);
    }

    /** Hash do manifest (muda quando muda o build); `null` sem manifest. */
    public function manifestHash(?string $buildDirectory = null): ?string
    {
        $buildDirectory = $buildDirectory === null ? $this->buildDirectory : trim($buildDirectory, '/\\');
        $file = $this->manifestPath($buildDirectory);

        return is_file($file) ? md5_file($file) ?: null : null;
    }

    // -------------------------------------------------------------------------
    // Manifest
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function manifest(string $buildDirectory): array
    {
        $path = $this->manifestPath($buildDirectory);

        if (isset(static::$manifests[$path])) {
            return static::$manifests[$path];
        }

        if (! is_file($path)) {
            throw new ViteManifestNotFoundException(
                "Vite manifest not found at [{$path}]. Run `npm run build` (or `npm run dev` for the development server).",
            );
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (! is_array($manifest)) {
            throw new ViteException("The Vite manifest [{$path}] is not valid JSON.");
        }

        /** @var array<string, array<string, mixed>> $manifest */
        return static::$manifests[$path] = $manifest;
    }

    protected function manifestPath(string $buildDirectory): string
    {
        $base = $this->publicPath.DIRECTORY_SEPARATOR.$buildDirectory.DIRECTORY_SEPARATOR;
        $path = $base.$this->manifestFilename;

        // O Vite ≥ 5 escreve em `.vite/manifest.json` quando não se configura outro caminho.
        if (! is_file($path) && is_file($base.'.vite'.DIRECTORY_SEPARATOR.$this->manifestFilename)) {
            return $base.'.vite'.DIRECTORY_SEPARATOR.$this->manifestFilename;
        }

        return $path;
    }

    /**
     * @param  array<string, array<string, mixed>>  $manifest
     * @return array<string, mixed>
     */
    protected function chunk(array $manifest, string $entry, string $buildDirectory): array
    {
        if (! isset($manifest[$entry])) {
            throw new ViteException("Unable to locate file in Vite manifest: {$entry} (build directory [{$buildDirectory}]).");
        }

        return $manifest[$entry];
    }

    /**
     * Imports de um chunk, recursivamente e sem repetições.
     *
     * @param  array<string, array<string, mixed>>  $manifest
     * @param  array<string, mixed>  $chunk
     * @param  array<string, true>  $seen
     * @return list<array<string, mixed>>
     */
    protected function importedChunks(array $manifest, array $chunk, string $buildDirectory, array &$seen = []): array
    {
        $found = [];

        foreach ((array) ($chunk['imports'] ?? []) as $key) {
            if (! is_string($key) || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $import = $this->chunk($manifest, $key, $buildDirectory);

            $found[] = $import;

            foreach ($this->importedChunks($manifest, $import, $buildDirectory, $seen) as $nested) {
                $found[] = $nested;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $chunk
     */
    protected function fileOf(array $chunk): string
    {
        $file = $chunk['file'] ?? '';

        return is_string($file) ? $file : '';
    }

    /**
     * @param  array<string, mixed>  $chunk
     * @return list<string>
     */
    protected function cssOf(array $chunk): array
    {
        return array_values(array_filter((array) ($chunk['css'] ?? []), is_string(...)));
    }

    // -------------------------------------------------------------------------
    // HTML
    // -------------------------------------------------------------------------

    protected function url(string $buildDirectory, string $file): string
    {
        $path = $buildDirectory.'/'.ltrim($file, '/');

        return $this->assetUrl !== null ? ($this->assetUrl)($path) : '/'.$path;
    }

    protected function isCss(string $path): bool
    {
        return preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)(\?.*)?$/i', $path) === 1;
    }

    protected function nonceAttribute(): string
    {
        return $this->nonce === null ? '' : ' nonce="'.htmlspecialchars($this->nonce, ENT_QUOTES).'"';
    }

    protected function scriptTag(string $url): string
    {
        return '<script type="module" src="'.htmlspecialchars($url, ENT_QUOTES).'"'.$this->nonceAttribute().'></script>';
    }

    protected function styleTag(string $url): string
    {
        return '<link rel="stylesheet" href="'.htmlspecialchars($url, ENT_QUOTES).'"'.$this->nonceAttribute().'>';
    }

    protected function preloadTag(string $url): string
    {
        return '<link rel="modulepreload" href="'.htmlspecialchars($url, ENT_QUOTES).'"'.$this->nonceAttribute().'>';
    }
}
