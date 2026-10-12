<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Console;

use Slendie\Console\Command;
use Slendie\Tests\Fixtures\GreeterContract;

/** Argumento obrigatório, opção booleana e opção com valor; o Greeter chega por injecção no handle(). */
final class GreetCommand extends Command
{
    protected string $signature = 'greet {name : Quem} {--shout : Em maiúsculas} {--times=1 : Repetições}';

    protected string $description = 'Cumprimenta alguém';

    protected array $aliases = ['hello'];

    public function handle(GreeterContract $greeter): int
    {
        $text = $greeter->greet((string) $this->argument('name'));

        for ($i = 0; $i < (int) $this->option('times'); $i++) {
            $this->line($this->option('shout') === true ? mb_strtoupper($text) : $text);
        }

        return self::SUCCESS;
    }
}
