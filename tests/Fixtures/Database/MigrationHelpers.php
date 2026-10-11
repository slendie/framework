<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Database;

use Slendie\Database\DatabaseManager;
use Slendie\Database\Migrations\MigrationRepository;
use Slendie\Database\Migrations\Migrator;
use Slendie\Foundation\Application;

/**
 * Pastas temporárias de migrações e um Migrator sobre SQLite :memory:.
 */
final class MigrationHelpers
{
    /** @var list<string> */
    private static array $dirs = [];

    public static function dir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie_mig_'.bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return self::$dirs[] = $dir;
    }

    public static function cleanup(): void
    {
        foreach (self::$dirs as $dir) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($dir);
        }

        self::$dirs = [];
    }

    /**
     * Migração anónima que cria e apaga uma tabela (`id` + `name`), via `$this->schema()`.
     */
    public static function createTable(string $dir, string $name, string $table, string $extra = ''): string
    {
        return self::write($dir, $name, <<<PHP
            <?php

            declare(strict_types=1);

            use Slendie\\Database\\Migrations\\Migration;
            use Slendie\\Database\\Schema\\Blueprint;

            return new class extends Migration
            {
                {$extra}

                public function up(): void
                {
                    \$this->schema()->create('{$table}', function (Blueprint \$table): void {
                        \$table->id();
                        \$table->string('name')->nullable();
                    });
                }

                public function down(): void
                {
                    \$this->schema()->dropIfExists('{$table}');
                }
            };
            PHP);
    }

    public static function write(string $dir, string $name, string $contents): string
    {
        $file = $dir.DIRECTORY_SEPARATOR.$name.'.php';
        file_put_contents($file, $contents);

        return $file;
    }

    /**
     * @param  array<string, array<string, mixed>>  $connections
     */
    public static function manager(array $connections = []): DatabaseManager
    {
        return new DatabaseManager(new Application(fixturePath('http')), [
            'default' => 'main',
            'connections' => $connections + [
                'main' => ['driver' => 'sqlite', 'database' => ':memory:'],
                'other' => ['driver' => 'sqlite', 'database' => ':memory:'],
            ],
        ]);
    }

    /**
     * @param  list<string>  $paths
     */
    public static function migrator(array $paths, ?DatabaseManager $manager = null): Migrator
    {
        $manager ??= self::manager();

        return new Migrator(new MigrationRepository($manager, 'migrations'), $manager, $paths);
    }
}
