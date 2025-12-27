<?php

declare(strict_types=1);

namespace Slendie\Framework;

/**
 * Custom Autoloader for controllers, models, and other non-namespaced classes
 *
 * This autoloader handles classes that are not covered by Composer's PSR-4 autoloader,
 * such as controllers and models in the app/ directory that may not have full namespace paths.
 */
final class Autoloader
{
    /**
     * Base path for the project
     * @var string
     */
    private static string $basePath;

    /**
     * Whether the autoloader has been registered
     * @var bool
     */
    private static bool $registered = false;

    /**
     * Register the autoloader
     *
     * @param string|null $basePath Optional base path. If not provided, uses BASE_PATH
     * @return void
     */
    public static function register(string|null $basePath = null): void
    {
        if (self::$registered) {
            return;
        }

        self::$basePath = $basePath ?? BASE_PATH;

        spl_autoload_register([self::class, 'load'], true, true);
        self::$registered = true;
    }

    /**
     * Unregister the autoloader
     *
     * @return void
     */
    public static function unregister(): void
    {
        if (self::$registered) {
            spl_autoload_unregister([self::class, 'load']);
            self::$registered = false;
        }
    }

    /**
     * Load a class file
     *
     * @param string $class The class name to load
     * @return bool True if the class was loaded, false otherwise
     */
    public static function load(string $class): bool
    {
        $base = self::$basePath ?? BASE_PATH;

        // Paths to search for class files
        $paths = [
            '/app/controllers/' . $class . '.php',
            '/app/controllers/middlewares/' . $class . '.php',
            '/app/models/' . $class . '.php',
            '/src/' . $class . '.php',
            '/src/Controllers/' . $class . '.php',
            '/src/Controllers/Middlewares/' . $class . '.php',
            '/src/Framework/' . $class . '.php',
            '/src/Models/' . $class . '.php',
        ];

        foreach ($paths as $rel) {
            $file = $base . $rel;
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }

        return false;
    }
}
