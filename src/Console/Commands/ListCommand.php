<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;

final class ListCommand extends Command
{
    protected string $signature = 'list {namespace? : Mostra só os comandos deste grupo (ex.: make)}';

    protected string $description = 'Lista os comandos disponíveis';

    public function handle(): int
    {
        $filter = $this->argument('namespace');
        $filter = is_string($filter) && $filter !== '' ? rtrim($filter, ':') : null;

        $groups = [];

        foreach ($this->console()->all() as $name => $command) {
            if ($command->isHidden()) {
                continue;
            }

            $group = str_contains($name, ':') ? strstr($name, ':', true) : '';

            if ($filter !== null && $group !== $filter) {
                continue;
            }

            $groups[(string) $group][$name] = $command->getDescription();
        }

        if ($groups === []) {
            $this->error($filter === null ? 'Não há comandos registados.' : "Não há comandos no grupo \"{$filter}\".");

            return self::FAILURE;
        }

        ksort($groups);

        if ($filter === null) {
            $this->line('Slendie Framework <info>'.$this->console()->version().'</info>');
            $this->newLine();
            $this->comment('Uso:');
            $this->line('  comando [opções] [argumentos]');
            $this->newLine();
            $this->comment('Opções:');
            $this->line('  <info>-h, --help</info>            Ajuda do comando');
            $this->line('  <info>-q, --quiet</info>           Sem output');
            $this->line('  <info>-v, -vv</info>               Output verboso');
            $this->line('  <info>-n, --no-interaction</info>  Sem perguntas (usa os valores por omissão)');
            $this->line('      <info>--ansi|--no-ansi</info>  Força ou desliga as cores');
            $this->newLine();
        }

        $width = max(array_map('strlen', array_merge(...array_values(array_map('array_keys', $groups))))) + 2;

        $this->comment('Comandos disponíveis:');

        foreach ($groups as $group => $commands) {
            if ($group !== '') {
                $this->comment(' '.$group);
            }

            ksort($commands);

            foreach ($commands as $name => $description) {
                $this->line('  <info>'.str_pad($name, $width).'</info>'.$description);
            }
        }

        return self::SUCCESS;
    }
}
