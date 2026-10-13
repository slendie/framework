<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Foundation\Application;
use Slendie\Support\Config;

/**
 * Escreve (ou regenera) o `AGENTS.md` da raiz do projecto a partir do stub `AGENTS.md.stub`
 * (o do projecto em `stubs/` tem prioridade sobre o da framework).
 */
final class AiAgentsFileCommand extends Command
{
    protected string $signature = 'ai:agents-file {--force : Substitui o AGENTS.md se já existir}';

    protected string $description = 'Gera o AGENTS.md com o que a framework tem e não tem, para agentes de IA';

    public function handle(Application $app, Config $config): int
    {
        $target = $app->basePath('AGENTS.md');

        if (is_file($target) && $this->option('force') !== true) {
            $this->error('AGENTS.md already exists. Use --force to overwrite it.');

            return self::FAILURE;
        }

        $stub = $this->stubPath($app);

        if ($stub === null) {
            $this->error('Stub AGENTS.md.stub not found.');

            return self::FAILURE;
        }

        $name = $config->get('app.name', 'Slendie');
        $contents = str_replace('{{ name }}', is_string($name) && $name !== '' ? $name : 'Slendie', (string) file_get_contents($stub));

        if (file_put_contents($target, $contents) === false) {
            $this->error('Could not write AGENTS.md.');

            return self::FAILURE;
        }

        $this->badge('INFO', 'AGENTS.md created successfully.');

        return self::SUCCESS;
    }

    private function stubPath(Application $app): ?string
    {
        foreach ([$app->basePath('stubs/AGENTS.md.stub'), dirname(__DIR__, 3).'/stubs/AGENTS.md.stub'] as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }
}
