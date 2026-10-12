<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Console;

use Slendie\Console\Application as ConsoleApplication;
use Slendie\Console\BufferedOutput;
use Slendie\Database\DatabaseManager;
use Slendie\Foundation\Application;
use Slendie\Foundation\Bootstrap\HandleExceptions;
use Slendie\Support\Facade;

/**
 * Um projecto Slendie temporário (pastas, config, SQLite em ficheiro) com a aplicação de consola pronta a correr comandos.
 */
final class ConsoleProject
{
    /** @var list<string> */
    private static array $dirs = [];

    /** @var list<self> */
    private static array $projects = [];

    public readonly Application $app;

    public readonly ConsoleApplication $console;

    public readonly BufferedOutput $out;

    private function __construct(public readonly string $dir)
    {
        $this->app = new Application($dir);
        $this->out = new BufferedOutput();
        $this->console = $this->app->make(ConsoleApplication::class);
        $this->console->setOutput($this->out);
        // Os testes nunca podem ficar presos à espera do teclado, mesmo correndo num terminal.
        $this->console->setInteractive(false);

        self::$projects[] = $this;
    }

    /**
     * @param  array<string, string>  $files  caminho relativo => conteúdo (acrescentados ao esqueleto)
     */
    public static function make(array $files = [], string $database = 'file'): self
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie_console_'.bin2hex(random_bytes(6));
        self::$dirs[] = $dir;

        foreach (['app', 'bootstrap', 'config', 'database/migrations', 'database/seeders', 'routes', 'resources/views', 'storage/framework/views'] as $folder) {
            mkdir($dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $folder), 0777, true);
        }

        $sqlite = $database === 'memory' ? ':memory:' : $dir.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';

        $defaults = [
            '.env' => "APP_NAME=Teste\nAPP_KEY=\n",
            'config/app.php' => "<?php\n\nreturn ['name' => 'Teste', 'env' => 'local', 'debug' => false, 'url' => 'http://localhost', 'key' => null, 'timezone' => 'UTC', 'locale' => 'pt', 'providers' => []];\n",
            'config/database.php' => "<?php\n\nreturn ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ".var_export($sqlite, true).", 'foreign_key_constraints' => true]]];\n",
        ];

        foreach ([...$defaults, ...$files] as $path => $contents) {
            self::write($dir, $path, $contents);
        }

        return new self($dir);
    }

    public static function write(string $dir, string $path, string $contents): string
    {
        $file = $dir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        file_put_contents($file, $contents);

        return $file;
    }

    public function path(string $path = ''): string
    {
        return $this->dir.($path === '' ? '' : DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    public function put(string $path, string $contents): string
    {
        return self::write($this->dir, $path, $contents);
    }

    public function read(string $path): string
    {
        return (string) file_get_contents($this->path($path));
    }

    public function has(string $path): bool
    {
        return file_exists($this->path($path));
    }

    /**
     * Corre `php slendie <linha>` e devolve [código de saída, output].
     *
     * @return array{0: int, 1: string}
     */
    public function artisan(string $line): array
    {
        $this->out->fetch();

        $code = $this->console->run(['slendie', ...($line === '' ? [] : str_getcsv($line, ' ', '"', ''))]);

        return [$code, self::lf($this->out->fetch())];
    }

    /** Fins de linha normalizados (no Windows PHP_EOL tem retorno de carro). */
    public static function lf(string $text): string
    {
        return str_replace("\r\n", "\n", $text);
    }

    public static function cleanup(): void
    {
        foreach (self::$projects as $project) {
            if ($project->app->hasBeenBootstrapped()) {
                // Cada bootstrap instalou handlers de erro: repô-los uma vez por aplicação arrancada.
                HandleExceptions::flush();
            }

            self::closeDatabases($project->app);
        }

        self::$projects = [];
        Facade::clearResolvedInstances();
        gc_collect_cycles();

        foreach (self::$dirs as $dir) {
            self::delete($dir);
        }

        self::$dirs = [];
    }

    /** No Windows o ficheiro SQLite não se apaga com a ligação aberta. */
    private static function closeDatabases(Application $app): void
    {
        if (! $app->resolved(DatabaseManager::class)) {
            return;
        }

        $db = $app->make(DatabaseManager::class);

        foreach (array_keys($db->getConnections()) as $name) {
            $db->purge((string) $name);
        }
    }

    private static function delete(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        foreach (glob($path.DIRECTORY_SEPARATOR.'{,.}*', GLOB_BRACE) ?: [] as $item) {
            if (in_array(basename($item), ['.', '..'], true)) {
                continue;
            }

            self::delete($item);
        }

        @rmdir($path);
    }
}
