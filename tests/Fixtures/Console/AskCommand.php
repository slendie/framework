<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Console;

use Slendie\Console\Command;

/** Percorre a interacção (ask, secret, confirm, choice) e os helpers de saída. */
final class AskCommand extends Command
{
    protected string $signature = 'ask';

    protected string $description = 'Interacção';

    public function handle(): int
    {
        $name = $this->ask('Nome?', 'anónimo');
        $pass = $this->secret('Password?');
        $sure = $this->confirm('Certeza?', true);
        $format = $this->choice('Formato?', ['pdf', 'csv'], 'pdf');
        $named = $this->choice('Nível?', ['a' => 'alto', 'b' => 'baixo'], 'b');

        $this->line('name='.$name.' pass='.($pass ?? 'null').' sure='.var_export($sure, true).' format='.$format.' level='.$named);

        return self::SUCCESS;
    }
}
