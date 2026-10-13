<?php

declare(strict_types=1);

namespace Slendie\Testing\Concerns;

use Slendie\Console\Application as ConsoleApplication;
use Slendie\Testing\PendingCommand;

/**
 * @phpstan-require-extends \Slendie\Testing\TestCase
 */
trait InteractsWithConsole
{
    /**
     * Corre um comando da consola (o equivalente do `artisan()` do Laravel):
     *
     *     $this->console('make:model', ['name' => 'Lead', '--migration' => true])->assertSuccessful();
     *
     * @param  array<array-key, mixed>  $parameters
     */
    public function console(string $command, array $parameters = []): PendingCommand
    {
        return new PendingCommand($this->app->make(ConsoleApplication::class), $command, $parameters);
    }
}
