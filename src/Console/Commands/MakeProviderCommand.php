<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\GeneratorCommand;

final class MakeProviderCommand extends GeneratorCommand
{
    protected string $signature = 'make:provider {name : Nome do provider} {--force : Substitui o ficheiro se já existir}';

    protected string $description = 'Cria um service provider e regista-o em bootstrap/providers.php';

    protected string $type = 'Provider';

    protected function stub(): string
    {
        return 'provider.stub';
    }

    protected function defaultNamespace(): string
    {
        return rtrim($this->rootNamespace(), '\\').'\\Providers';
    }

    public function handle(): int
    {
        $status = parent::handle();

        if ($status !== self::SUCCESS) {
            return $status;
        }

        $class = $this->qualifyName((string) $this->argument('name'));
        $file = $this->basePath('bootstrap'.DIRECTORY_SEPARATOR.'providers.php');

        if ($this->register($file, $class)) {
            $this->badge('INFO', "Provider registered in [{$this->relativePath($file)}].");
        } else {
            $this->warn("Não foi possível editar bootstrap/providers.php. Acrescenta manualmente: \\{$class}::class");
        }

        return self::SUCCESS;
    }

    /**
     * Acrescenta `\Classe::class,` ao array devolvido por bootstrap/providers.php (cria o ficheiro se não existir).
     */
    private function register(string $file, string $class): bool
    {
        $entry = '\\'.$class.'::class';

        if (! is_file($file)) {
            $directory = dirname($file);

            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                return false;
            }

            return file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    {$entry},\n];\n") !== false;
        }

        $source = file_get_contents($file);

        if ($source === false) {
            return false;
        }

        if (preg_match('/\\\\?'.preg_quote($class, '/').'::class/', $source) === 1) {
            return true; // já registado
        }

        $end = strrpos($source, '];');

        if ($end === false || preg_match('/return\s*\[/', $source, $m, PREG_OFFSET_CAPTURE) !== 1 || $m[0][1] > $end) {
            return false;
        }

        $before = rtrim(substr($source, 0, $end));
        $lines = explode("\n", $before);
        $last = trim((string) end($lines));
        $needsComma = ! (str_ends_with($last, '[') || str_ends_with($last, ',') || str_starts_with($last, '//') || str_starts_with($last, '#') || str_starts_with($last, '*'));

        $updated = $before.($needsComma ? ',' : '')."\n    {$entry},\n".substr($source, $end);

        return file_put_contents($file, $updated) !== false;
    }
}
