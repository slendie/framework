<?php

declare(strict_types=1);

namespace Slendie\Console\Commands;

use Slendie\Console\Command;
use Slendie\Database\Seeders\Seeder;
use Slendie\Foundation\Application;

final class DbSeedCommand extends Command
{
    protected string $signature = 'db:seed
        {--class=Database\\Seeders\\DatabaseSeeder : Classe do seeder}
        {--force : Corre em produção sem pedir confirmação}';

    protected string $description = 'Popula a base de dados com os seeders';

    public function handle(Application $app): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $class = ltrim((string) $this->option('class'), '\\');

        if (! class_exists($class)) {
            $this->error("Seeder class [{$class}] does not exist.");

            return self::FAILURE;
        }

        if (! is_subclass_of($class, Seeder::class)) {
            $this->error("[{$class}] is not a seeder (it must extend ".Seeder::class.').');

            return self::FAILURE;
        }

        $seeder = $app->make($class)->setContainer($app);

        $this->task("Seeding: {$class}", static function () use ($seeder): bool {
            $seeder();

            return true;
        });

        return self::SUCCESS;
    }
}
