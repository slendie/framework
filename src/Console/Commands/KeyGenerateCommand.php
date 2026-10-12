<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Foundation\Application;
use Slendie\Support\Config;
use Slendie\Support\Env;

final class KeyGenerateCommand extends Command
{
    protected string $signature = 'key:generate
        {--show : Mostra a chave em vez de a escrever no .env}
        {--force : Substitui a chave existente (e corre em produção sem confirmar)}';

    protected string $description = 'Gera a chave da aplicação (APP_KEY)';

    public function handle(Application $app, Config $config): int
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        if ($this->option('show') === true) {
            $this->line($key);

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $file = $app->environmentFilePath();
        $contents = is_file($file) ? (string) file_get_contents($file) : '';

        if ($this->option('force') !== true && $this->currentKey($contents) !== '') {
            $this->error('Application key already set. Use --force to overwrite it.');

            return self::FAILURE;
        }

        $line = 'APP_KEY='.$key;

        if (preg_match('/^APP_KEY=.*$/m', $contents) === 1) {
            $contents = (string) preg_replace_callback('/^APP_KEY=.*$/m', static fn (): string => $line, $contents, 1);
        } else {
            $contents .= ($contents === '' || str_ends_with($contents, "\n") ? '' : "\n").$line."\n";
        }

        if (file_put_contents($file, $contents) === false) {
            $this->error("Could not write to [{$file}].");

            return self::FAILURE;
        }

        Env::set('APP_KEY', $key);
        $config->set('app.key', $key);

        $this->badge('INFO', 'Application key set successfully.');

        return self::SUCCESS;
    }

    private function currentKey(string $contents): string
    {
        return preg_match('/^APP_KEY=(.*)$/m', $contents, $m) === 1 ? trim($m[1], " \t\r\"'") : '';
    }
}
