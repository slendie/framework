<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\View;

use Slendie\Container\Container;
use Slendie\View\Compiler\BladeCompiler;
use Slendie\View\Engine;
use Slendie\View\Factory;
use Slendie\View\Finder;
use Slendie\View\ViewErrorBag;

/**
 * Monta uma Factory real sobre pastas temporárias, sem precisar de uma Application.
 */
final class ViewHelpers
{
    public static function tempDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-view-'.bin2hex(random_bytes(6));
        mkdir($dir.'/views', 0777, true);
        mkdir($dir.'/cache', 0777, true);

        return $dir;
    }

    public static function write(string $dir, string $name, string $contents): string
    {
        $file = $dir.'/views/'.str_replace('.', '/', $name).'.blade.php';

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        file_put_contents($file, $contents);

        return $file;
    }

    public static function factory(string $dir, bool $debug = false, ?Container $container = null): Factory
    {
        $compiler = new BladeCompiler($dir.'/cache', $debug);

        return new Factory(
            new Finder([$dir.'/views']),
            new Engine($compiler),
            ['errors' => new ViewErrorBag()],
            $container,
        );
    }

    public static function compiler(Factory $factory): BladeCompiler
    {
        return $factory->getEngine()->getCompiler();
    }

    /**
     * Renderiza um template (guardado como `main`) com dados e views auxiliares opcionais.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $files  nome da view => conteúdo
     */
    public static function render(string $blade, array $data = [], array $files = []): string
    {
        $dir = self::tempDir();

        foreach ($files as $name => $contents) {
            self::write($dir, $name, $contents);
        }

        self::write($dir, 'main', $blade);

        return self::factory($dir)->render('main', $data);
    }

    public static function compile(string $blade): string
    {
        return (new BladeCompiler(sys_get_temp_dir()))->compileString($blade);
    }
}
