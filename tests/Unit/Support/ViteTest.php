<?php

declare(strict_types=1);

use Slendie\Exceptions\ViteException;
use Slendie\Exceptions\ViteManifestNotFoundException;
use Slendie\Support\Vite;
use Slendie\View\Compiler\BladeCompiler;

beforeEach(function (): void {
    Vite::flushState();
    $this->public = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie_vite_'.bin2hex(random_bytes(6));
    mkdir($this->public.'/build/assets', 0777, true);
});

afterEach(function (): void {
    $delete = static function (string $path) use (&$delete): void {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        foreach (glob($path.'/{,.}*', GLOB_BRACE) ?: [] as $item) {
            if (! in_array(basename($item), ['.', '..'], true)) {
                $delete($item);
            }
        }

        if (is_dir($path)) {
            rmdir($path);
        }
    };

    $delete($this->public);
});

function writeManifest(string $public, ?array $manifest = null, string $file = 'build/manifest.json'): void
{
    $manifest ??= [
        'resources/js/app.js' => [
            'file' => 'assets/app-abc.js',
            'src' => 'resources/js/app.js',
            'isEntry' => true,
            'imports' => ['_vendor-1.js'],
            'css' => ['assets/app-abc.css'],
        ],
        '_vendor-1.js' => ['file' => 'assets/vendor-1.js', 'imports' => ['_shared-2.js'], 'css' => ['assets/vendor-1.css']],
        '_shared-2.js' => ['file' => 'assets/shared-2.js'],
        'resources/css/app.css' => ['file' => 'assets/style-xyz.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
        'resources/img/logo.svg' => ['file' => 'assets/logo-123.svg'],
    ];

    if (! is_dir(dirname($public.'/'.$file))) {
        mkdir(dirname($public.'/'.$file), 0777, true);
    }

    file_put_contents($public.'/'.$file, json_encode($manifest));
}

it('em modo hot (public/hot) aponta para o dev server e trata CSS como link', function (): void {
    file_put_contents($this->public.'/hot', "http://localhost:5173/\n");

    $vite = new Vite($this->public);
    $html = $vite(['resources/css/app.css', 'resources/js/app.js']);

    expect($vite->isRunningHot())->toBeTrue()
        ->and($html)->toBe(implode("\n", [
            '<script type="module" src="http://localhost:5173/@vite/client"></script>',
            '<link rel="stylesheet" href="http://localhost:5173/resources/css/app.css">',
            '<script type="module" src="http://localhost:5173/resources/js/app.js"></script>',
        ]));

    unlink($this->public.'/hot');
});

it('reactRefresh() devolve o preamble em hot e nada em build', function (): void {
    $vite = new Vite($this->public);

    expect($vite->reactRefresh())->toBe('');

    file_put_contents($this->public.'/hot', 'http://localhost:5173');

    expect($vite->reactRefresh())
        ->toContain("import RefreshRuntime from 'http://localhost:5173/@react-refresh'")
        ->toContain('window.$RefreshReg$ = () => {}')
        ->toContain('__vite_plugin_react_preamble_installed__ = true');

    unlink($this->public.'/hot');
});

it('em build lê o manifest: stylesheets, modulepreload dos imports (recursivos) e o script', function (): void {
    writeManifest($this->public);

    $html = (new Vite($this->public))('resources/js/app.js');

    expect($html)->toBe(implode("\n", [
        '<link rel="modulepreload" href="/build/assets/vendor-1.js">',
        '<link rel="modulepreload" href="/build/assets/shared-2.js">',
        '<link rel="stylesheet" href="/build/assets/vendor-1.css">',
        '<link rel="stylesheet" href="/build/assets/app-abc.css">',
        '<script type="module" src="/build/assets/app-abc.js"></script>',
    ]));
});

it('uma entrada CSS gera só o link; várias entradas não repetem tags', function (): void {
    writeManifest($this->public);

    $html = (new Vite($this->public))(['resources/css/app.css', 'resources/js/app.js', 'resources/js/app.js']);

    expect(substr_count($html, 'vendor-1.js'))->toBe(1)
        ->and($html)->toContain('<link rel="stylesheet" href="/build/assets/style-xyz.css">')
        ->and($html)->toContain('<script type="module" src="/build/assets/app-abc.js"></script>');
});

it('sem argumentos usa withEntryPoints(); o directório de build é configurável', function (): void {
    writeManifest($this->public, file: 'dist/manifest.json');

    $vite = (new Vite($this->public))->useBuildDirectory('dist')->withEntryPoints(['resources/css/app.css']);

    expect($vite())->toBe('<link rel="stylesheet" href="/dist/assets/style-xyz.css">');

    $other = new Vite($this->public);
    expect($other('resources/css/app.css', 'dist'))->toBe('<link rel="stylesheet" href="/dist/assets/style-xyz.css">');
});

it('aceita o manifest em .vite/manifest.json e um nome de manifest personalizado', function (): void {
    writeManifest($this->public, file: 'build/.vite/manifest.json');
    expect((new Vite($this->public))('resources/css/app.css'))->toContain('style-xyz.css');

    Vite::flushState();
    writeManifest($this->public, file: 'build/custom.json');
    expect((new Vite($this->public))->useManifestFilename('custom.json')('resources/css/app.css'))->toContain('style-xyz.css');
});

it('manifest em falta lança com instrução para correr npm run build', function (): void {
    $vite = new Vite($this->public);

    expect(fn () => $vite('resources/js/app.js'))
        ->toThrow(ViteManifestNotFoundException::class, 'npm run build');
});

it('entrada que não está no manifest lança ViteException', function (): void {
    writeManifest($this->public);

    expect(fn () => (new Vite($this->public))('resources/js/nope.js'))
        ->toThrow(ViteException::class, 'resources/js/nope.js');
});

it('asset() devolve o URL de um asset do manifest (ou do dev server em hot)', function (): void {
    writeManifest($this->public);
    $vite = new Vite($this->public);

    expect($vite->asset('resources/img/logo.svg'))->toBe('/build/assets/logo-123.svg');

    file_put_contents($this->public.'/hot', 'http://localhost:5173');
    expect($vite->asset('resources/img/logo.svg'))->toBe('http://localhost:5173/resources/img/logo.svg');
    unlink($this->public.'/hot');
});

it('content() devolve o ficheiro compilado e manifestHash() muda com o manifest', function (): void {
    writeManifest($this->public);
    file_put_contents($this->public.'/build/assets/style-xyz.css', 'body{color:red}');
    $vite = new Vite($this->public);

    expect($vite->content('resources/css/app.css'))->toBe('body{color:red}');

    $first = $vite->manifestHash();
    writeManifest($this->public, ['resources/css/app.css' => ['file' => 'assets/other.css']]);

    expect($first)->toBeString()->and($vite->manifestHash())->not->toBe($first)
        ->and((new Vite($this->public))->manifestHash('nada'))->toBeNull();
});

it('useCspNonce() acrescenta nonce a todas as tags', function (): void {
    writeManifest($this->public);
    $vite = new Vite($this->public);

    expect($vite->useCspNonce('abc123'))->toBe('abc123');

    $html = $vite('resources/js/app.js');

    expect($html)->toContain('<script type="module" src="/build/assets/app-abc.js" nonce="abc123"></script>')
        ->and($html)->toContain('<link rel="stylesheet" href="/build/assets/app-abc.css" nonce="abc123">')
        ->and($html)->toContain('<link rel="modulepreload" href="/build/assets/vendor-1.js" nonce="abc123">');

    expect((new Vite($this->public))->useCspNonce())->not->toBe('')->and($vite->cspNonce())->toBe('abc123');
});

it('resolveAssetUrlUsing() permite prefixar os URLs (CDN, subpasta)', function (): void {
    writeManifest($this->public);

    $vite = (new Vite($this->public))->resolveAssetUrlUsing(static fn (string $path): string => 'https://cdn.test/'.$path);

    expect($vite('resources/css/app.css'))->toBe('<link rel="stylesheet" href="https://cdn.test/build/assets/style-xyz.css">');
});

it('escapa aspas nos URLs', function (): void {
    writeManifest($this->public, ['a.js' => ['file' => 'assets/a"b.js']]);

    expect((new Vite($this->public))('a.js'))->toContain('a&quot;b.js');
});

it('@vite e @viteReactRefresh compilam para a classe Vite', function (): void {
    $compiler = new BladeCompiler(sys_get_temp_dir(), false);

    $compiled = $compiler->compileString("@vite(['resources/js/app.js'])\n@viteReactRefresh");

    expect($compiled)->toContain("app(\\Slendie\\Support\\Vite::class)(['resources/js/app.js'])")
        ->and($compiled)->toContain('app(\\Slendie\\Support\\Vite::class)->reactRefresh()');
});
